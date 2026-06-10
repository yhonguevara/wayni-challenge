<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/upload');
});

// Frontend upload form
Route::get('/upload', function () {
    return view('upload');
})->name('upload.form');

Route::get('/panel', function () {
    return view('panel');
})->name('panel');
