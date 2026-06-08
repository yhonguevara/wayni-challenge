<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Jobs\ProcessBcraFile;
use App\Http\Requests\UploadFileRequest;
use App\Models\ImportLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Controller for file upload endpoint.
 *
 * Handles POST /upload with dual-mode file handling:
 * - Multipart: saves file to storage/app/uploads/
 * - JSON: passes S3 key to job for download
 */
final class UploadController extends Controller
{
    /**
     * Handle file upload and dispatch processing job.
     */
    public function store(UploadFileRequest $request): JsonResponse
    {
        $importId = (string) Str::uuid();

        if ($request->hasUploadedFile()) {
            // Multipart upload — save to local storage
            $file = $request->file('file');
            $filename = $importId . '.txt';
            $fileSource = $file->storeAs('uploads', $filename, 'local');
            $fileSize = $file->getSize();
        } else {
            // JSON with S3 key — job will download it
            $fileSource = (string) $request->input('s3_key');
            $fileSize = null;
        }

        // Create ImportLog (status: pending)
        ImportLog::create([
            'id' => $importId,
            'filename' => $request->hasUploadedFile()
                ? $request->file('file')->getClientOriginalName()
                : basename($fileSource),
            'status' => 'pending',
        ]);

        // Dispatch ProcessBcraFile job
        ProcessBcraFile::dispatch($fileSource, $importId);

        return response()->json([
            'import_id' => $importId,
            'status' => 'queued',
            'message' => 'File uploaded and processing started',
        ], 202);
    }
}
