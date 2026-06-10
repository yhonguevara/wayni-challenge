<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/upload');
});

// Frontend upload form. The API query panel lives in the query service.
Route::get('/upload', function () {
    return view('upload');
})->name('upload.form');
