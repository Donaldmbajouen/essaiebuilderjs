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

// Route::get('/builder', function () {
//     $templateName = request()->get('template');
//     return view('builder.editor', compact('templateName'));
// })->name('builder.editor');

// Template management routes (web interface)
Route::get('/templates', [App\Http\Controllers\TemplateController::class, 'index'])->name('templates.index');
Route::get('/templates/create', [App\Http\Controllers\TemplateController::class, 'create'])->name('templates.create');
Route::get('/templates/{template}', [App\Http\Controllers\TemplateController::class, 'show'])->name('templates.show');
Route::delete('/templates/{template}', [App\Http\Controllers\TemplateController::class, 'destroy'])->name('templates.destroy');

// API routes have been moved to routes/api.php for better AJAX handling
