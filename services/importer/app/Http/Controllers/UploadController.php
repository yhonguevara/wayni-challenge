<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Jobs\ProcessBcraFile;
use App\Application\Ports\ImportLogRepository;
use App\Http\Requests\UploadFileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function __construct(
        private readonly ImportLogRepository $importLogRepository,
    ) {}

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
            $relativePath = $file->storeAs('uploads', $filename, 'local');
            $fileSource = storage_path('app/' . $relativePath);
            $fileSize = $file->getSize();
        } else {
            // JSON with S3 key — job will download it
            $fileSource = (string) $request->input('s3_key');
            $fileSize = null;
        }

        // Create ImportLog (status: pending)
        $this->importLogRepository->create([
            'id' => $importId,
            'filename' => $request->hasUploadedFile()
                ? $request->file('file')->getClientOriginalName()
                : basename($fileSource),
            'status' => 'pending',
        ]);

        // Dispatch ProcessBcraFile job
        ProcessBcraFile::dispatch($fileSource, $importId);

        return response()->json([
            'import_log_id' => $importId,
            'status' => 'queued',
            'message' => 'File uploaded and processing started',
        ], 202);
    }

    /**
     * Handle the post-upload notification for direct-to-S3 uploads.
     *
     * Called after the browser has uploaded the file straight to S3 via a
     * pre-signed POST. Creates the ImportLog and dispatches the processing job
     * with the S3 key — the worker streams the object down, so multi-GB files
     * never pass through this PHP request.
     */
    public function notify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string'],
        ]);

        $s3Key = $validated['key'];
        $importId = (string) Str::uuid();

        $this->importLogRepository->create([
            'id' => $importId,
            'filename' => basename($s3Key),
            'status' => 'pending',
        ]);

        ProcessBcraFile::dispatch($s3Key, $importId);

        return response()->json([
            'import_log_id' => $importId,
            'status' => 'queued',
            'message' => 'File queued for processing',
        ], 202);
    }
}
