<?php

namespace App\Services;

use App\Models\Template;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class TemplateStorageService
{
    /**
     * Store ZIP content in database and optionally extract
     */
    public function storeZipTemplate(string $zipPath, array $templateData): Template
    {
        $zipContent = file_get_contents($zipPath);
        $zipSize = filesize($zipPath);

        $template = Template::create(array_merge($templateData, [
            'zip_content' => base64_encode($zipContent),
            'zip_size' => $zipSize,
            'is_extracted' => false,
            'original_filename' => basename($zipPath)
        ]));

        Log::info("Template {$template->id} stored with ZIP content ({$zipSize} bytes)");

        return $template;
    }

    /**
     * Ensure template is extracted to public directory
     */
    public function ensureExtracted(Template $template): bool
    {
        if ($template->is_extracted && $this->isExtractedValid($template)) {
            return true;
        }

        if (!$template->hasZipContent()) {
            Log::warning("Template {$template->id} has no ZIP content to extract");
            return false;
        }

        return $this->extractTemplate($template);
    }

    /**
     * Extract template from ZIP content to public directory
     */
    public function extractTemplate(Template $template): bool
    {
        try {
            $extractPath = $template->getExtractedPath();

            // Create extraction directory
            if (!File::exists($extractPath)) {
                File::makeDirectory($extractPath, 0755, true);
            }

            // Create temporary ZIP file
            $tempZip = tempnam(sys_get_temp_dir(), 'template_');
            file_put_contents($tempZip, base64_decode($template->zip_content));

            // Extract ZIP
            $zip = new ZipArchive();
            if ($zip->open($tempZip) === TRUE) {
                // Security check: validate file paths
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    if ($this->isUnsafePath($filename)) {
                        $zip->close();
                        unlink($tempZip);
                        throw new \Exception("Unsafe file path detected: {$filename}");
                    }
                }

                $zip->extractTo($extractPath);
                $zip->close();

                // Update template status
                $template->update([
                    'is_extracted' => true,
                    'extracted_at' => now()
                ]);

                // Convert HTML to BuilderJS format
                $this->convertExtractedTemplate($template);

                Log::info("Template {$template->id} extracted successfully to {$extractPath}");

                unlink($tempZip);
                return true;
            } else {
                unlink($tempZip);
                throw new \Exception("Failed to open ZIP file");
            }

        } catch (\Exception $e) {
            Log::error("Failed to extract template {$template->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Convert extracted template to BuilderJS format
     */
    private function convertExtractedTemplate(Template $template): void
    {
        $entryFilePath = $template->getEntryFilePath();

        if (!file_exists($entryFilePath)) {
            Log::warning("Entry file not found for template {$template->id}: {$entryFilePath}");
            return;
        }

        $html = file_get_contents($entryFilePath);

        // Use TemplateConverterService to convert HTML with awareness of entry directory
        $converter = app(TemplateConverterService::class);
        $entryDir = trim(str_replace('\\', '/', dirname($template->entry_file)), '/.');
        if ($entryDir === '.' || $entryDir === '..') {
            $entryDir = '';
        }
        $convertedHtml = $converter->convertHtmlToBuilderJS($html, $template->id, $entryDir);

        // Save converted HTML
        file_put_contents($entryFilePath, $convertedHtml);

        Log::info("Template {$template->id} converted to BuilderJS format");
    }

    /**
     * Get file content from template (hybrid mode)
     */
    public function getFileContent(Template $template, string $filePath): ?string
    {
        // Ensure template is extracted
        if (!$this->ensureExtracted($template)) {
            return null;
        }

        $extractPath = $template->getExtractedPath();
        $normalizedFile = ltrim(str_replace('\\', '/', $filePath), '/');
        
        // Try multiple path combinations to find the file
        $pathsToTry = [
            // Direct path
            $extractPath . '/' . $normalizedFile,
            
            // Path within entry directory (for templates like test2/index.html)
            $extractPath . '/' . trim(dirname($template->entry_file), './') . '/' . $normalizedFile,
            
            // Path without entry directory prefix if it was included
            $extractPath . '/' . str_replace(trim(dirname($template->entry_file), './') . '/', '', $normalizedFile)
        ];

        foreach ($pathsToTry as $fullPath) {
            if (file_exists($fullPath) && $this->isPathSafe($fullPath, $extractPath)) {
                return file_get_contents($fullPath);
            }
        }

        return null;
    }

    /**
     * Check if extracted template is still valid
     */
    private function isExtractedValid(Template $template): bool
    {
        $extractPath = $template->getExtractedPath();
        $entryFile = $extractPath . '/' . $template->entry_file;

        return File::exists($extractPath) && File::exists($entryFile);
    }

    /**
     * Security check for unsafe file paths
     */
    private function isUnsafePath(string $path): bool
    {
        // Check for directory traversal attempts
        if (strpos($path, '..') !== false) {
            return true;
        }

        // Check for absolute paths
        if (strpos($path, '/') === 0 || strpos($path, '\\') === 0) {
            return true;
        }

        // Check for dangerous file extensions
        $dangerousExtensions = ['.php', '.exe', '.bat', '.sh', '.cmd'];
        foreach ($dangerousExtensions as $ext) {
            if (substr($path, -strlen($ext)) === $ext) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if path is safe (within template directory)
     */
    private function isPathSafe(string $path, string $basePath): bool
    {
        $realPath = realpath($path);
        $realBasePath = realpath($basePath);

        if (!$realPath || !$realBasePath) {
            return false;
        }

        return strpos($realPath, $realBasePath) === 0;
    }

    /**
     * Clean up old extracted templates
     */
    public function cleanupOldExtractions(int $daysOld = 30): int
    {
        $cutoffDate = now()->subDays($daysOld);
        $oldTemplates = Template::where('extracted_at', '<', $cutoffDate)
                              ->where('is_extracted', true)
                              ->get();

        $cleaned = 0;

        foreach ($oldTemplates as $template) {
            if ($this->removeExtractedFiles($template)) {
                $template->update([
                    'is_extracted' => false,
                    'extracted_at' => null
                ]);
                $cleaned++;
                Log::info("Cleaned up extracted files for template {$template->id}");
            }
        }

        return $cleaned;
    }

    /**
     * Remove extracted files for a template
     */
    public function removeExtractedFiles(Template $template): bool
    {
        $extractPath = $template->getExtractedPath();

        if (File::exists($extractPath)) {
            try {
                File::deleteDirectory($extractPath);
                return true;
            } catch (\Exception $e) {
                Log::error("Failed to remove extracted files for template {$template->id}: " . $e->getMessage());
                return false;
            }
        }

        return true;
    }

    /**
     * Get template file URL for serving
     */
    public function getFileUrl(Template $template, string $filePath): string
    {
        return "/api/template/{$template->id}/" . ltrim($filePath, '/');
    }

    /**
     * Migrate existing template to hybrid storage
     */
    public function migrateToHybrid(Template $template, string $zipPath): bool
    {
        if ($template->hasZipContent()) {
            Log::info("Template {$template->id} already has ZIP content");
            return true;
        }

        if (!file_exists($zipPath)) {
            Log::error("ZIP file not found for migration: {$zipPath}");
            return false;
        }

        try {
            $zipContent = file_get_contents($zipPath);
            $zipSize = filesize($zipPath);

            $template->update([
                'zip_content' => base64_encode($zipContent),
                'zip_size' => $zipSize,
                'original_filename' => basename($zipPath)
            ]);

            Log::info("Template {$template->id} migrated to hybrid storage");
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to migrate template {$template->id}: " . $e->getMessage());
            return false;
        }
    }
}
