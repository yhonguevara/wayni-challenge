<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/upload', function () {
    return view('upload');
})->name('upload.form');
