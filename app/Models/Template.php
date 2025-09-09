<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Template extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'entry_file',
        'preview_image',
        'zip_content',
        'zip_size',
        'is_extracted',
        'extracted_at',
        'original_filename'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'extracted_at' => 'datetime',
        'is_extracted' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the full path to the template's entry file (hybrid mode)
     */
    public function getEntryFilePath(): string
    {
        if ($this->is_extracted) {
            return public_path("templates/{$this->id}/{$this->entry_file}");
        }
        
        // Fallback: generate a path based on template ID
        return public_path("templates/{$this->id}/{$this->entry_file}");
    }

    /**
     * Get the public path for extracted template
     */
    public function getExtractedPath(): string
    {
        return public_path("templates/{$this->id}");
    }

    /**
     * Check if template needs extraction
     */
    public function needsExtraction(): bool
    {
        return !$this->is_extracted && !empty($this->zip_content);
    }

    /**
     * Check if template has ZIP content
     */
    public function hasZipContent(): bool
    {
        return !empty($this->zip_content);
    }

    /**
     * Get the URL to access the template
     */
    public function getUrl(): string
    {
        return route('templates.show', $this->id);
    }

    /**
     * Get the content of the entry file (hybrid mode)
     */
    public function getEntryFileContent(): ?string
    {
        try {
            // ✅ 1. Essayer d'abord le mode extrait
            if ($this->is_extracted) {
                $path = public_path("templates/{$this->id}/{$this->entry_file}");
                if (file_exists($path)) {
                    $content = file_get_contents($path);
                    \Illuminate\Support\Facades\Log::info("Template {$this->id}: Retrieved content from extracted file", [
                        'path' => $path,
                        'content_length' => strlen($content),
                        'content_preview' => substr($content, 0, 200) . '...'
                    ]);
                    return $content;
                }
            }

            // ✅ 2. Si pas extrait mais ZIP en base, extraire automatiquement
            if (!$this->is_extracted && $this->hasZipContent()) {
                \Illuminate\Support\Facades\Log::info("Template {$this->id}: Not extracted but has ZIP content, extracting...");

                // Extraire le template automatiquement
                $storageService = app(\App\Services\TemplateStorageService::class);
                if ($storageService->ensureExtracted($this)) {
                    \Illuminate\Support\Facades\Log::info("Template {$this->id}: Extraction successful, retrying content retrieval");

                    // Rafraîchir le modèle depuis la base
                    $this->refresh();

                    // Réessayer après extraction
                    if ($this->is_extracted) {
                        $path = public_path("templates/{$this->id}/{$this->entry_file}");
                        if (file_exists($path)) {
                            $content = file_get_contents($path);
                            \Illuminate\Support\Facades\Log::info("Template {$this->id}: Content retrieved after extraction", [
                                'path' => $path,
                                'content_length' => strlen($content),
                                'content_preview' => substr($content, 0, 200) . '...'
                            ]);
                            return $content;
                        }
                    }
                } else {
                    \Illuminate\Support\Facades\Log::error("Template {$this->id}: Failed to extract template");
                }
            }

            Log::warning("Template {$this->id}: Could not retrieve entry file content", [
                'is_extracted' => $this->is_extracted,
                'has_zip_content' => $this->hasZipContent(),
                'entry_file' => $this->entry_file
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error("Template {$this->id}: Error retrieving entry file content: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if template directory exists (hybrid mode)
     */
    public function directoryExists(): bool
    {
        // Check extracted path first
        if ($this->is_extracted) {
            return is_dir(public_path("templates/{$this->id}"));
        }
        
        // Check if has ZIP content
        return $this->hasZipContent();
    }

    /**
     * Get all files in the template directory (hybrid mode)
     */
    public function getFiles(): array
    {
        // If extracted, scan the public directory
        if ($this->is_extracted) {
            $extractedPath = public_path("templates/{$this->id}");
            if (is_dir($extractedPath)) {
                return $this->scanDirectory($extractedPath);
            }
        }
        
        return [];
    }
    
    /**
     * Recursively scan directory for files
     */
    private function scanDirectory(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
}
