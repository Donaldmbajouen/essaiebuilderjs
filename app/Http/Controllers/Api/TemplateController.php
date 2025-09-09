<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Services\TemplateConverterService;
use App\Services\TemplateStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class TemplateController extends Controller
{
    private $converterService;
    private $storageService;

    public function __construct(TemplateConverterService $converterService, TemplateStorageService $storageService)
    {
        $this->converterService = $converterService;
        $this->storageService = $storageService;
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'template' => 'required|file|mimetypes:application/zip,application/x-zip-compressed|max:51200', // 50MB max
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'preview_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048' // 2MB max
        ]);

        try {
            $file = $request->file('template');
            $userId = auth()->id() ?? 1; // Fallback pour les tests

            // Valider le ZIP avant stockage
            $this->validateZipFile($file->getPathname());

            // Trouver le fichier d'entrée dans le ZIP
            $entryFile = $this->findEntryFileInZip($file->getPathname());
            if (!$entryFile) {
                throw new \Exception('Aucun fichier HTML trouvé à la racine du template');
            }

            // Gérer l'image de prévisualisation
            $previewImageName = null;
            if ($request->hasFile('preview_image')) {
                $previewFile = $request->file('preview_image');
                $previewImageName = 'preview_' . time() . '.' . $previewFile->getClientOriginalExtension();
                // L'image sera stockée lors de l'extraction du template
            }

            // Stocker le template avec le mode hybride
            $template = $this->storageService->storeZipTemplate($file->getPathname(), [
                'user_id' => $userId,
                'name' => $request->name,
                'description' => $request->description,
                'entry_file' => $entryFile,
                'preview_image' => $previewImageName
            ]);

            // Si on a une image de prévisualisation, la stocker
            if ($previewImageName && $request->hasFile('preview_image')) {
                $this->handlePreviewImage($template, $request->file('preview_image'), $previewImageName);
            }

            return response()->json([
                'success' => true,
                'message' => 'Template uploadé avec succès (mode hybride)',
                'template' => $template,
                'url' => route('templates.show', $template->id)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du traitement: ' . $e->getMessage()
            ], 500);
        }
    }

    public function createEmpty(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000'
        ]);

        try {
            $userId = auth()->id() ?? 1; // Fallback pour les tests

            // Créer un template vide avec contenu HTML de base
            $emptyHtmlContent = $this->generateEmptyTemplateHtml();

            // Créer un template avec le contenu HTML vide
            $template = Template::create([
                'user_id' => $userId,
                'name' => $request->name,
                'description' => $request->description,
                'entry_file' => 'index.html',
                'is_extracted' => false
            ]);

            // Stocker le contenu HTML vide dans la base de données
            $template->update([
                'zip_content' => base64_encode($emptyHtmlContent),
                'zip_size' => strlen($emptyHtmlContent)
            ]);

            Log::info("Nouveau template vide créé: {$template->id}");

            return response()->json([
                'success' => true,
                'message' => 'Nouveau template créé avec succès',
                'template' => $template,
                'url' => route('templates.show', $template->id)
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la création du template vide: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Génère le contenu HTML de base pour un nouveau template vide
     */
    private function generateEmptyTemplateHtml(): string
    {
        return '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau Template</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .welcome-text {
            text-align: center;
            color: #666;
            font-size: 18px;
            margin-bottom: 40px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="builder-content">Bienvenue sur votre nouveau template</h1>
        <p class="welcome-text builder-content">
            Commencez à construire votre site web en ajoutant du contenu avec les outils de drag & drop.
        </p>
    </div>
</body>
</html>';
    }

    public function update(Request $request, Template $template): JsonResponse
    {
        // TEST TEMPORAIRE - Écrire directement dans un fichier
        $debugFile = storage_path('debug_update.log');
        $debugData = [
            'timestamp' => now()->toISOString(),
            'template_id' => $template->id,
            'method' => $request->method(),
            'has_html_content' => $request->has('html_content'),
            'html_content_length' => $request->has('html_content') ? strlen($request->html_content) : 0,
            'has_content' => $request->has('content'),
            'content_length' => $request->has('content') ? strlen($request->input('content')) : 0,
            'content_type' => $request->header('Content-Type'),
            'all_keys' => array_keys($request->all()),
            'raw_length' => strlen($request->getContent())
        ];
        file_put_contents($debugFile, json_encode($debugData, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

        // LOG DE TEST TRÈS SIMPLE
        \Illuminate\Support\Facades\Log::info('=== TEST LOG: UPDATE METHOD CALLED ===', [
            'template_id' => $template->id,
            'timestamp' => now()->toISOString(),
            'request_method' => $request->method()
        ]);
        Log::info('Update request received', [
            'template_id' => $template->id,
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'all_data' => $request->all(),
            'raw_content' => $request->getContent()
        ]);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string|max:1000',
            'html_content' => 'sometimes|nullable|string',
            'content' => 'sometimes|nullable|string'
        ]);

        try {
            $updateData = $request->only(['name', 'description']);

            // Supporter plusieurs formats d'entrée: form-data (content/html_content) ou JSON brut
            $htmlContent = $request->input('html_content');
            if ($htmlContent === null) {
                $htmlContent = $request->input('content');
            }
            if ($htmlContent === null) {
                $raw = $request->getContent();
                if (!empty($raw)) {
                    try {
                        $json = json_decode($raw, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $htmlContent = $json['html_content'] ?? $json['content'] ?? null;
                        }
                    } catch (\Throwable $t) {
                        // ignore parse errors
                    }
                }
            }

            if ($htmlContent !== null) {
                Log::info('Saving template HTML content', [
                    'template_id' => $template->id,
                    'html_length' => strlen($htmlContent)
                ]);
                $this->saveTemplateHtml($template, $htmlContent);
                $updateData['updated_at' ] = now();
            } else {
                Log::warning('No HTML content found in request', [
                    'template_id' => $template->id,
                    'all_keys' => array_keys($request->all())
                ]);
            }

            $template->update($updateData);

            return response()->json([
                'success' => true,
                'message' => $request->has('html_content') ? 'Template modifié et sauvegardé avec succès' : 'Template mis à jour avec succès',
                'template' => $template
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }

    public function list(): JsonResponse
    {
        try {
            $templates = Template::with('user')
                               ->orderBy('created_at', 'desc')
                               ->get()
                               ->map(function ($template) {
                                   return [
                                       'id' => $template->id,
                                       'name' => $template->name,
                                       'description' => $template->description,
                                       'preview_image' => $template->preview_image,
                                       'url' => "/api/template/{$template->id}/" . ($template->entry_file ?: 'index.html'),
                                       'is_extracted' => $template->is_extracted,
                                       'has_zip_content' => $template->hasZipContent(),
                                       'created_at' => $template->created_at->format('Y-m-d H:i:s'),
                                       'user' => $template->user ? $template->user->name : 'Unknown'
                                   ];
                               });

            return response()->json([
                'success' => true,
                'templates' => $templates
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des templates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload d'image générique pour l'éditeur
     */
    public function uploadImage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|mimetypes:image/jpeg,image/png,image/gif,image/webp,image/svg+xml|max:5120'
            ]);

            $file = $request->file('file');

            // Stocker sur le disque public (storage/app/public/uploads)
            $path = $file->store('uploads', 'public');

            return response()->json([
                'success' => true,
                'url' => asset('storage/' . $path),
                'path' => 'storage/' . $path
            ]);

        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'success' => false,
                'message' => 'Fichier invalide',
                'errors' => $ve->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Upload image error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur lors de l\'upload'
            ], 500);
        }
    }

    /**
     * Sauvegarde le contenu HTML modifié d'un template
     */
    private function saveTemplateHtml(Template $template, string $htmlContent): void
    {
        try {
            Log::info('=== STARTING HTML SAVE PROCESS ===', [
                'template_id' => $template->id,
                'entry_file' => $template->entry_file,
                'html_length' => strlen($htmlContent),
                'html_preview' => substr($htmlContent, 0, 200) . '...',
                'has_zip_content' => $template->hasZipContent(),
                'is_extracted' => $template->is_extracted
            ]);

            // Assurer que le template est extrait
            if (!$this->storageService->ensureExtracted($template)) {
                throw new \Exception('Impossible d\'extraire le template');
            }

            // Rafraîchir le template après extraction potentielle
            $template->refresh();

            // Obtenir le chemin du fichier d'entrée
            $extractedPath = $template->getExtractedPath();
            $entryFilePath = $extractedPath . '/' . $template->entry_file;

            Log::info('FILE PATH DETAILS', [
                'extracted_path' => $extractedPath,
                'entry_file_path' => $entryFilePath,
                'extracted_path_exists' => is_dir($extractedPath),
                'entry_file_exists' => file_exists($entryFilePath),
                'directory_writable' => is_writable(dirname($entryFilePath)),
                'file_writable' => file_exists($entryFilePath) ? is_writable($entryFilePath) : 'N/A'
            ]);

            // Créer une sauvegarde du fichier original
            if (file_exists($entryFilePath)) {
                $backupPath = $entryFilePath . '.backup';
                copy($entryFilePath, $backupPath);
                Log::info('BACKUP CREATED', ['backup_path' => $backupPath]);
            }

            // Sauvegarder le contenu HTML modifié
            $writeResult = file_put_contents($entryFilePath, $htmlContent);

            if ($writeResult === false) {
                throw new \Exception('file_put_contents a retourné false');
            }

            Log::info('WRITE OPERATION RESULT', [
                'bytes_written' => $writeResult,
                'expected_bytes' => strlen($htmlContent),
                'write_success' => ($writeResult === strlen($htmlContent))
            ]);

            // Vérifier immédiatement que le fichier a bien été sauvegardé
            if (file_exists($entryFilePath)) {
                $savedContent = file_get_contents($entryFilePath);
                $savedLength = strlen($savedContent);
                $contentMatches = ($savedLength === strlen($htmlContent));

                Log::info('VERIFICATION AFTER SAVE', [
                    'saved_length' => $savedLength,
                    'original_length' => strlen($htmlContent),
                    'content_matches' => $contentMatches,
                    'saved_preview' => substr($savedContent, 0, 200) . '...',
                    'first_100_chars_match' => substr($savedContent, 0, 100) === substr($htmlContent, 0, 100)
                ]);

                if (!$contentMatches) {
                    Log::error('CONTENT MISMATCH - File not saved correctly!', [
                        'expected' => substr($htmlContent, 0, 500),
                        'actual' => substr($savedContent, 0, 500)
                    ]);
                }
            } else {
                throw new \Exception('Le fichier n\'existe pas après la sauvegarde');
            }

            // Test de lecture pour confirmer
            $testRead = file_get_contents($entryFilePath);
            Log::info('FINAL VERIFICATION', [
                'can_read_file' => ($testRead !== false),
                'read_length' => strlen($testRead),
                'content_identical' => ($testRead === $htmlContent)
            ]);

            Log::info("=== HTML SAVE PROCESS COMPLETED SUCCESSFULLY ===", [
                'template_id' => $template->id,
                'file_path' => $entryFilePath,
                'final_file_size' => filesize($entryFilePath)
            ]);

        } catch (\Exception $e) {
            Log::error("=== HTML SAVE PROCESS FAILED ===", [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Valide un fichier ZIP avant traitement
     */
    private function validateZipFile(string $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== TRUE) {
            throw new \Exception('Impossible d\'ouvrir le fichier ZIP');
        }

        // Vérifier la sécurité des fichiers
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            if (str_contains($filename, '..') || str_starts_with($filename, '/')) {
                $zip->close();
                throw new \Exception('Fichier avec chemin dangereux détecté: ' . $filename);
            }
        }

        $zip->close();
    }

    /**
     * Trouve le fichier HTML principal dans un ZIP (gère les dossiers imbriqués)
     */
    private function findEntryFileInZip(string $zipPath): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== TRUE) {
            return null;
        }

        $htmlFiles = [];
        $folders = [];

        // Analyser tous les fichiers du ZIP
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            // Séparer les dossiers des fichiers
            if (substr($filename, -1) === '/') {
                // C'est un dossier
                $folderName = trim($filename, '/');
                if (strpos($folderName, '/') === false) {
                    // Dossier racine dans le ZIP
                    $folders[] = $folderName;
                }
            } else {
                // C'est un fichier
                if (pathinfo($filename, PATHINFO_EXTENSION) === 'html') {
                    $htmlFiles[] = $filename;
                }
            }
        }

        $zip->close();

        // 1. Chercher les fichiers HTML à la racine du ZIP
        $rootHtmlFiles = array_filter($htmlFiles, function($file) {
            return strpos($file, '/') === false;
        });

        if (!empty($rootHtmlFiles)) {
            // Priorité aux noms courants
            $priorities = ['index.html', 'main.html', 'template.html'];
            foreach ($priorities as $priority) {
                if (in_array($priority, $rootHtmlFiles)) {
                    return $priority;
                }
            }
            return $rootHtmlFiles[0];
        }

        // 2. Si pas de HTML à la racine, chercher dans le premier dossier
        if (!empty($folders) && !empty($htmlFiles)) {
            $firstFolder = $folders[0] . '/';

            // Chercher les HTML dans ce dossier
            $folderHtmlFiles = array_filter($htmlFiles, function($file) use ($firstFolder) {
                return strpos($file, $firstFolder) === 0;
            });

            if (!empty($folderHtmlFiles)) {
                // Retirer le préfixe du dossier
                $relativeFiles = array_map(function($file) use ($firstFolder) {
                    return str_replace($firstFolder, '', $file);
                }, $folderHtmlFiles);

                // Priorité aux noms courants
                $priorities = ['index.html', 'main.html', 'template.html'];
                foreach ($priorities as $priority) {
                    if (in_array($priority, $relativeFiles)) {
                        return $firstFolder . $priority;
                    }
                }
                return $firstFolder . $relativeFiles[0];
            }
        }

        return null;
    }

    /**
     * Trouve une image de prévisualisation dans un ZIP
     */
    private function findPreviewImageInZip(string $zipPath): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== TRUE) {
            return null;
        }

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $previewNames = ['preview', 'thumbnail', 'thumb', 'screenshot'];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            // Chercher à la racine seulement
            if (str_contains($filename, '/')) {
                continue;
            }

            $name = pathinfo($filename, PATHINFO_FILENAME);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($name, $previewNames) && in_array($ext, $imageExtensions)) {
                $zip->close();
                return $filename;
            }
        }

        $zip->close();
        return null;
    }

    /**
     * Gère le stockage de l'image de prévisualisation
     */
    private function handlePreviewImage(Template $template, $previewFile, string $imageName): void
    {
        try {
            // Assurer que le template est extrait
            if (!$this->storageService->ensureExtracted($template)) {
                throw new \Exception('Impossible d\'extraire le template');
            }

            // Créer le dossier images s'il n'existe pas
            $imagesPath = $template->getExtractedPath() . '/images';
            if (!file_exists($imagesPath)) {
                mkdir($imagesPath, 0755, true);
            }

            // Déplacer l'image vers le dossier images du template
            $previewFile->move($imagesPath, $imageName);

            Log::info("Image de prévisualisation stockée pour le template {$template->id}: {$imageName}");

        } catch (\Exception $e) {
            Log::error("Erreur lors du stockage de l'image de prévisualisation pour le template {$template->id}: " . $e->getMessage());
            // Ne pas interrompre l'upload si l'image de prévisualisation échoue
        }
    }
}
