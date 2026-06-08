<?php

use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Frontend upload form
Route::get('/upload', function () {
    return view('upload');
})->name('upload.form');

// POST /upload endpoint (CSRF excluded in bootstrap/app.php)
Route::post('/upload', [UploadController::class, 'store'])->name('upload.store');
