<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Template management API routes - moved from web.php for better AJAX handling
Route::get('/templates', [App\Http\Controllers\Api\TemplateController::class, 'list']);
Route::post('/templates', [App\Http\Controllers\Api\TemplateController::class, 'store']);
Route::post('/templates/create-empty', [App\Http\Controllers\Api\TemplateController::class, 'createEmpty']);
Route::post('/templatesupd/{template}', [App\Http\Controllers\Api\TemplateController::class, 'update']);
Route::put('/templatesupd/{template}', [App\Http\Controllers\Api\TemplateController::class, 'update']);

// Upload d'image pour l'éditeur/builder
Route::post('/upload-image', [App\Http\Controllers\Api\TemplateController::class, 'uploadImage'])->name('builderjs.upload-image');

// Hybrid mode file serving - moved from web.php
Route::get('/template/{templateId}/{file?}', [App\Http\Controllers\TemplateController::class, 'serve'])
    ->where('file', '.*')
    ->where('templateId', '[0-9]+');
