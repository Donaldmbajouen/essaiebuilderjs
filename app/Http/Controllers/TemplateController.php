<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\TemplateConverterService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    private $converterService;

    public function __construct(TemplateConverterService $converterService)
    {
        $this->converterService = $converterService;
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'template' => 'required|file|mimes:html,htm|max:1024', // 1MB max
            'name' => 'required|string|max:255'
        ]);

        try {
            $file = $request->file('template');
            $html = file_get_contents($file->getPathname());
            
            // Validate template
            $errors = $this->converterService->validateTemplate($html);
            if (!empty($errors)) {
                return response()->json(['errors' => $errors], 422);
            }

            // Convert to BuilderJS compatible
            $convertedHtml = $this->converterService->convertHtmlToBuilderJS($html);
            
            // Generate unique filename
            $filename = Str::slug($request->name) . '_' . time() . '.html';
            
            // Save to public/templates
            $path = public_path('templates/' . $filename);
            file_put_contents($path, $convertedHtml);

            return response()->json([
                'success' => true,
                'message' => 'Template uploadé et converti avec succès',
                'filename' => $filename,
                'url' => '/api/template/' . pathinfo($filename, PATHINFO_FILENAME)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du traitement: ' . $e->getMessage()
            ], 500);
        }
    }

    public function serve(string $templateName)
    {
        $templatePath = public_path('templates/' . $templateName . '.html');
        
        if (!file_exists($templatePath)) {
            return response()->json(['error' => 'Template non trouvé'], 404);
        }
        
        $content = file_get_contents($templatePath);
        return response($content)->header('Content-Type', 'text/html');
    }

    public function list(): JsonResponse
    {
        $templatesPath = public_path('templates');
        
        if (!is_dir($templatesPath)) {
            return response()->json(['templates' => []]);
        }

        $templates = [];
        $files = glob($templatesPath . '/*.html');
        
        foreach ($files as $file) {
            $filename = basename($file, '.html');
            $templates[] = [
                'name' => $filename,
                'url' => '/api/template/' . $filename,
                'created_at' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }

        return response()->json(['templates' => $templates]);
    }
}
