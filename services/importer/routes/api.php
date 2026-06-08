<?php

use App\Http\Controllers\UploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// File upload endpoint
Route::post('/upload', [UploadController::class, 'store'])->name('api.upload');

// Pre-signed URL endpoint (future implementation)
Route::post('/presign', function (Request $request) {
    // Stub: Returns pre-signed URL for S3 upload
    // Implementation will use S3::createPresignedPost() or S3::temporaryUrl()
    return response()->json([
        'upload_url' => config('s3.url') . '/' . config('s3.bucket'),
        'fields' => [
            'key' => 'uploads/' . $request->input('filename', 'file.txt'),
        ],
    ]);
})->name('api.presign');

Route::post('/notify-upload', function (Request $request) {
    // Stub: Notifies backend that file has been uploaded
    // Implementation will dispatch ECS task or queue job
    return response()->json([
        'message' => 'File queued for processing',
        'import_id' => \Illuminate\Support\Str::uuid()->toString(),
    ]);
})->name('api.notify-upload');
