<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/upload', function () {
    return view('template-upload');
});

// BuilderJS template endpoints
Route::get('/api/template/{templateName}', [App\Http\Controllers\TemplateController::class, 'serve']);
Route::post('/api/template/upload', [App\Http\Controllers\TemplateController::class, 'upload']);
Route::get('/api/templates', [App\Http\Controllers\TemplateController::class, 'list']);
