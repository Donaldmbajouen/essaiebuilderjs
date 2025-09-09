<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Services\TemplateStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\Services\TemplateConverterService;
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

    public function upload(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'template' => 'required|file|mimes:zip',
                'name' => 'required|string|max:255',
                'entry_point' => 'required|string|ends_with:.html,.htm'
            ]);

            // Vérifier les permissions des dossiers
            $tempDir = storage_path('app/temp');
            $publicTemplatesDir = public_path('templates');

            if (!is_writable($tempDir) || !is_writable($publicTemplatesDir)) {
                throw new \Exception("Permissions insuffisantes sur les dossiers de destination");
            }
            $zipFile = $request->file('template');
            $templateName = Str::slug($request->name) . '_' . time();
            $extractPath = storage_path('app/temp/' . $templateName);
            $destinationPath = public_path('templates/' . $templateName);

            // Créer le dossier de destination s'il n'existe pas
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            // Extraire le ZIP
            $zip = new ZipArchive;
            if ($zip->open($zipFile->getRealPath()) === true) {
                $zip->extractTo($extractPath);
                $zip->close();
            } else {
                throw new \Exception('Impossible d\'extraire le fichier ZIP');
            }

            // Vérifier s'il y a un seul dossier à la racine
            $extractedItems = array_diff(scandir($extractPath), array('..', '.'));
            $rootPath = $extractPath;

            // Si un seul dossier, on considère que c'est le dossier racine du template
            if (count($extractedItems) === 1 && is_dir($extractPath . '/' . $extractedItems[2])) {
                $rootPath = $extractPath . '/' . $extractedItems[2];
            }

            // Vérifier que le fichier d'entrée existe
            $entryPoint = $rootPath . '/' . ltrim($request->entry_point, '/');
            if (!file_exists($entryPoint)) {
                // Essayer de trouver un fichier index.html ou similaire
                $possibleEntries = ['index.html', 'index.htm', 'main.html', 'main.htm'];
                $found = false;

                foreach ($possibleEntries as $entry) {
                    if (file_exists($rootPath . '/' . $entry)) {
                        $entryPoint = $rootPath . '/' . $entry;
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    throw new \Exception('Le fichier d\'entrée spécifié n\'existe pas dans l\'archive et aucun fichier d\'entrée standard n\'a été trouvé');
                }
            }

            // Lire le contenu HTML
            $html = file_get_contents($entryPoint);

            // Valider le template
            $errors = $this->converterService->validateTemplate($html);
            if (!empty($errors)) {
                // Nettoyer les fichiers temporaires
                try {
                    if (is_dir($extractPath)) {
                        $this->rrmdir($extractPath);
                    }
                } catch (\Exception $cleanupError) {
                    Log::error('Erreur lors du nettoyage du dossier temporaire: ' . $cleanupError->getMessage());
                }
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation du template',
                    'errors' => $errors
                ], 422);
            }

            // Convertir le HTML pour BuilderJS
            $convertedHtml = $this->converterService->convertHtmlToBuilderJS($html);

            // Créer le dossier de destination dans public/templates
            $publicTemplatePath = public_path('templates/' . $templateName);
            if (!file_exists($publicTemplatePath)) {
                mkdir($publicTemplatePath, 0755, true);
            }

            // Copier tous les fichiers du template vers le dossier public
            $this->recurseCopy($rootPath, $publicTemplatePath);

            // Mettre à jour les chemins dans le HTML converti
            $convertedHtml = $this->updateAssetPaths($convertedHtml, $templateName);

            // Déterminer le nom du fichier d'entrée
            $entryFilename = basename($entryPoint);

            // Sauvegarder le fichier HTML principal avec le contenu converti
            file_put_contents($publicTemplatePath . '/' . $entryFilename, $convertedHtml);

            // Nettoyer les fichiers temporaires
            $this->rrmdir($extractPath);

            return response()->json([
                'success' => true,
                'message' => 'Template uploadé et converti avec succès',
                'template_name' => $templateName,
                'url' => '/api/template/' . $templateName . '/' . $entryFilename
            ]);

        } catch (\Exception $e) {
            // Journaliser l'erreur complète pour le débogage
            Log::error('Erreur lors de l\'upload du template: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            // Nettoyer en cas d'erreur
            if (isset($extractPath) && is_dir($extractPath)) {
                try {
                    $this->rrmdir($extractPath);
                } catch (\Exception $cleanupError) {
                    Log::error('Erreur lors du nettoyage après erreur: ' . $cleanupError->getMessage());
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors du traitement du template: ' . $e->getMessage()
            ], 500);
        }
    }

    public function serve(string $templateId, string $file = null)
    {
        try {
            $template = Template::findOrFail($templateId);

            // Ensure template is extracted (hybrid mode)
            if (!$this->storageService->ensureExtracted($template)) {
                abort(500, 'Impossible d\'extraire le template');
            }

            // If no file specified, use entry file
            if ($file === null) {
                $file = $template->entry_file;
            }

            // Get file content using hybrid storage
            $content = $this->storageService->getFileContent($template, $file);

            if ($content === null) {
                abort(404, 'Fichier non trouvé: ' . $file);
            }

            // Determine MIME type
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $mimeTypes = [
                'html' => 'text/html; charset=utf-8',
                'htm' => 'text/html; charset=utf-8',
                'css' => 'text/css; charset=utf-8',
                'js' => 'application/javascript; charset=utf-8',
                'json' => 'application/json; charset=utf-8',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'ttf' => 'font/ttf',
                'eot' => 'application/vnd.ms-fontobject',
                'otf' => 'font/otf',
                'ico' => 'image/x-icon'
            ];

            $mimeType = $mimeTypes[$extension] ?? 'text/plain';

            return response($content, 200, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'no-cache, private',
                'Content-Length' => strlen($content)
            ]);

        } catch (\Exception $e) {
            Log::error('Error serving template file: ' . $e->getMessage());
            abort(500, 'Erreur lors du service du fichier');
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
                                       'url' => route('templates.show', $template->id),
                                       'is_extracted' => $template->is_extracted,
                                       'has_zip_content' => $template->hasZipContent(),
                                       'created_at' => $template->created_at->format('d/m/Y H:i'),
                                       'user' => $template->user ? $template->user->name : 'Système'
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

    public function destroy(Template $template): JsonResponse
    {
        try {
            // Remove extracted files if they exist
            if ($template->is_extracted) {
                $this->storageService->removeExtractedFiles($template);
            }
            
            // Delete the template from database
            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Template supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Trouve le fichier d'entrée principal dans un dossier de template
     */
    private function findEntryFile($directory) {
        $possibleEntries = ['index.html', 'main.html', 'template.html'];

        foreach ($possibleEntries as $entry) {
            if (file_exists($directory . '/' . $entry)) {
                return $entry;
            }
        }

        // Si aucun fichier standard trouvé, chercher le premier fichier HTML
        $htmlFiles = glob($directory . '/*.{html,htm}', GLOB_BRACE);
        if (!empty($htmlFiles)) {
            return basename($htmlFiles[0]);
        }

        return null;
    }

    /**
     * Vérifie si le template contient des assets
     */
    private function hasAssets($directory, $entryFile) {
        $files = glob($directory . '/*');
        return count($files) > 1; // Plus qu'un seul fichier (le fichier d'entrée)
    }

    /**
     * Met à jour les chemins des assets dans le HTML
     */
    /**
     * Met à jour les chemins des assets dans le HTML pour qu'ils pointent vers les bonnes URLs
     *
     * @param string $html Le contenu HTML à traiter
     * @param string $templateName Le nom du template
     * @return string Le HTML avec les chemins mis à jour
     */
    private function updateAssetPaths($html, $templateName) {
        // Base de l'URL pour accéder aux assets du template
        $basePath = '/api/template/' . $templateName . '/';

        // Remplacer les attributs src, href et content contenant des chemins
        $html = preg_replace_callback('/(src|href|content)=["\']([^"\']+)["\']/',
            function($matches) use ($basePath, $templateName) {
                $attr = $matches[1];
                $url = $matches[2];

                // Ne pas modifier les URLs complètes (http, https, //)
                if (strpos($url, 'http') === 0 || strpos($url, '//') === 0) {
                    return $matches[0];
                }

                // Gérer les chemins absolus commençant par /images/
                if (strpos($url, '/images/') === 0) {
                    $newUrl = $basePath . 'images' . substr($url, 7);
                    return $attr . '="' . $newUrl . '"';
                }

                // Gérer les autres chemins absolus
                if (strpos($url, '/') === 0) {
                    // Vérifier si le fichier existe dans le dossier du template
                    $filePath = public_path('templates/' . $templateName . $url);
                    if (file_exists($filePath)) {
                        return $attr . '="' . $basePath . ltrim($url, '/') . '"';
                    }
                    return $matches[0];
                }

                // Pour les chemins relatifs, ajouter le chemin de base
                return $attr . '="' . $basePath . ltrim($url, './') . '"';
            },
            $html);

        return $html;
    }

    /**
     * Copie récursive d'un dossier
     */
    private function recurseCopy($src, $dst) {
        $dir = opendir($src);
        @mkdir($dst, 0755, true);

        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->recurseCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }

        closedir($dir);
    }

    /**
     * Supprime récursivement un dossier et son contenu
     */
    private function rrmdir($dir) {
        if (!file_exists($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }

        rmdir($dir);
    }

    public function show(Template $template)
    {
        // Ensure template is extracted for viewing
        $this->storageService->ensureExtracted($template);

        // Redirect to the template URL
        return redirect($this->storageService->getFileUrl($template, $template->entry_file));
    }

    public function index()
    {
        $templates = Template::with('user')
                           ->orderBy('created_at', 'desc')
                           ->paginate(12);

        return view('templates.index', compact('templates'));
    }

    public function create()
    {
        return view('templates.create');
    }
}
