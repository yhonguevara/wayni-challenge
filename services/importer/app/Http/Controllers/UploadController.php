<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Jobs\ProcessBcraFile;
use App\Application\Ports\ImportLogRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Controller for file ingestion endpoints.
 *
 * Two modes:
 * 1. Local path: POST /api/upload with JSON { "path": "/app/storage/samples/file.txt" }
 * 2. Pre-signed URL: POST /api/presign → browser uploads to S3 → POST /api/notify-upload with S3 key
 */
final class UploadController extends Controller
{
    public function __construct(
        private readonly ImportLogRepository $importLogRepository,
    ) {}

    /**
     * Process a file from local filesystem path.
     *
     * Accepts JSON: { "path": "/app/storage/samples/deudores_sample_10k.txt" }
     * The path must be accessible from within the Docker container.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
        ]);

        $filePath = $validated['path'];

        if (!file_exists($filePath)) {
            return response()->json([
                'error' => 'File not found',
                'path' => $filePath,
            ], 404);
        }

        $importId = (string) Str::uuid();

        $this->importLogRepository->create([
            'id' => $importId,
            'filename' => basename($filePath),
            'status' => 'pending',
        ]);

        ProcessBcraFile::dispatch($filePath, $importId);

        return response()->json([
            'import_log_id' => $importId,
            'status' => 'queued',
            'message' => 'File processing started',
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
