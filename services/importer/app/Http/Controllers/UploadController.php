<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Jobs\ProcessBcraFile;
use App\Application\Ports\FileStorage;
use App\Application\Ports\ImportLogRepository;
use App\Http\Requests\UploadFileRequest;
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
    public function __construct(
        private readonly ImportLogRepository $importLogRepository,
        private readonly FileStorage $fileStorage,
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
            $fileSource = $file->storeAs('uploads', $filename, 'local');
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
        ProcessBcraFile::dispatch($fileSource, $importId, $this->fileStorage, $this->importLogRepository);

        return response()->json([
            'import_log_id' => $importId,
            'status' => 'queued',
            'message' => 'File uploaded and processing started',
        ], 202);
    }
}
