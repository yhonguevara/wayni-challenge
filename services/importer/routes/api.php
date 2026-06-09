<?php

use App\Http\Controllers\UploadController;
use Aws\S3\PostObjectV4;
use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// Direct upload endpoint (multipart or JSON s3_key). Kept for CLI / small files.
Route::post('/upload', [UploadController::class, 'store'])->name('api.upload');

/*
 * Pre-signed POST endpoint.
 *
 * Returns a browser-facing pre-signed POST so the client can upload the file
 * (potentially several GB) DIRECTLY to S3 without it ever passing through PHP.
 * The browser-facing URL uses config('s3.url') (localhost:4566), while the
 * signature itself is computed against the same bucket the worker reads from.
 */
Route::post('/presign', function (Request $request) {
    $bucket = config('s3.bucket');
    $key = 'uploads/' . Str::uuid() . '-' . basename((string) $request->input('filename', 'file.txt'));

    $client = new S3Client([
        'endpoint' => config('s3.url'), // browser-facing host (localhost:4566)
        'region' => config('s3.region'),
        'version' => 'latest',
        'use_path_style_endpoint' => config('s3.use_path_style_endpoint'),
        'credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID', 'test'),
            'secret' => env('AWS_SECRET_ACCESS_KEY', 'test'),
        ],
    ]);

    $formInputs = ['key' => $key];
    $options = [
        ['bucket' => $bucket],
        ['starts-with', '$key', 'uploads/'],
        ['content-length-range', 0, 10 * 1024 * 1024 * 1024], // up to 10 GiB
    ];

    $postObject = new PostObjectV4(
        $client,
        $bucket,
        $formInputs,
        $options,
        '+30 minutes',
    );

    return response()->json([
        'upload_url' => $postObject->getFormAttributes()['action'],
        'fields' => $postObject->getFormInputs(),
        'key' => $key,
    ]);
})->name('api.presign');

/*
 * Notify endpoint — called after the browser finishes the direct-to-S3 upload.
 *
 * Creates the ImportLog and dispatches the processing job with the S3 key. The
 * worker streams the object down from S3 (never loading it fully into memory).
 */
Route::post('/notify-upload', [UploadController::class, 'notify'])
    ->name('api.notify-upload');
