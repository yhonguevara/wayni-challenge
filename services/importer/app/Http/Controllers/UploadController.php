<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Jobs\ProcessBcraFile;
use App\Application\Ports\ImportLogRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Controller for file ingestion endpoints.
 *
 * Two modes:
 * 1. Local path: POST /api/v1/upload with JSON { "path": "/app/storage/app/uploads/file.txt" }
 * 2. Pre-signed URL: POST /api/v1/presign → browser uploads to S3 → POST /api/v1/notify-upload with S3 key
 *
 * Security invariants:
 * - store(): resolved path MUST be inside storage_path('app/uploads'). Out-of-base → 422. In-base but
 *   missing → 404. Traversal attempts are caught by realpath() comparison.
 * - notify(): key MUST start with 'uploads/'. Wrong prefix → 422. Prefix OK but not in S3 → 404.
 * - Both endpoints: a new import is rejected with 409 if any import_log row has status pending|processing.
 */
final class UploadController extends Controller
{
    /** Allowed base directory for local file uploads. */
    private const UPLOAD_BASE = 'app/uploads';

    public function __construct(
        private readonly ImportLogRepository $importLogRepository,
    ) {}

    /**
     * Process a file from local filesystem path.
     *
     * Accepts JSON: { "path": "/app/storage/app/uploads/deudores.txt" }
     * The path must resolve inside storage_path('app/uploads').
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
        ]);

        $filePath = $validated['path'];

        // Path-traversal guard (Item 1).
        // realpath() returns false for paths that do not exist OR cannot be resolved;
        // we must check prefix first using the raw string to distinguish traversal from missing.
        $allowedBase = storage_path(self::UPLOAD_BASE);
        $resolvedBase = realpath($allowedBase) ?: $allowedBase;

        // Attempt to resolve the submitted path.
        $resolvedPath = realpath($filePath);

        if ($resolvedPath === false) {
            // Path does not exist on the filesystem. Check if the raw path at least
            // starts with the allowed base so we can give the right error code:
            // - inside base but missing → 404
            // - outside base (or traversal) → 422
            $normalised = $this->normalisePath($filePath);
            if (!str_starts_with($normalised, $resolvedBase . '/')) {
                return response()->json([
                    'error' => 'Path is outside the allowed upload directory',
                ], 422);
            }

            return response()->json([
                'error' => 'File not found',
                'path'  => $filePath,
            ], 404);
        }

        // Resolved successfully — enforce prefix.
        if (!str_starts_with($resolvedPath, $resolvedBase . '/')) {
            return response()->json([
                'error' => 'Path is outside the allowed upload directory',
            ], 422);
        }

        // Single-active-import guard (Item 3).
        $activeId = $this->importLogRepository->hasActiveImport();
        if ($activeId !== null) {
            return response()->json([
                'error'         => 'An import is already in progress',
                'import_log_id' => $activeId,
            ], 409);
        }

        $importId = (string) Str::uuid();

        $this->importLogRepository->create([
            'id'       => $importId,
            'filename' => basename($resolvedPath),
            'status'   => 'pending',
        ]);

        ProcessBcraFile::dispatch($resolvedPath, $importId);

        return response()->json([
            'import_log_id' => $importId,
            'status'        => 'queued',
            'message'       => 'File processing started',
        ], 202);
    }

    /**
     * Handle the post-upload notification for direct-to-S3 uploads.
     *
     * Called after the browser has uploaded the file straight to S3 via a
     * pre-signed POST. Creates the ImportLog and dispatches the processing job
     * with the S3 key — the worker streams the object down, so multi-GB files
     * never pass through this PHP request.
     *
     * S3 key validation (Item 2):
     * - Key MUST start with 'uploads/' → 422 if not.
     * - Key MUST exist in S3 → 404 if not.
     */
    public function notify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string'],
        ]);

        $s3Key = $validated['key'];

        // Prefix guard (Item 2).
        if (!str_starts_with($s3Key, 'uploads/')) {
            return response()->json([
                'error' => 'S3 key must start with the "uploads/" prefix',
            ], 422);
        }

        // Existence check against S3 (Item 2).
        if (!Storage::disk('s3')->exists($s3Key)) {
            return response()->json([
                'error' => 'Object not found in S3',
                'key'   => $s3Key,
            ], 404);
        }

        // Single-active-import guard (Item 3).
        $activeId = $this->importLogRepository->hasActiveImport();
        if ($activeId !== null) {
            return response()->json([
                'error'         => 'An import is already in progress',
                'import_log_id' => $activeId,
            ], 409);
        }

        $importId = (string) Str::uuid();

        $this->importLogRepository->create([
            'id'       => $importId,
            'filename' => basename($s3Key),
            'status'   => 'pending',
        ]);

        ProcessBcraFile::dispatch($s3Key, $importId);

        return response()->json([
            'import_log_id' => $importId,
            'status'        => 'queued',
            'message'       => 'File queued for processing',
        ], 202);
    }

    /**
     * Normalise a raw path without relying on realpath() (for non-existent paths).
     *
     * Resolves '..' segments so that '/var/app/storage/app/uploads/../../etc/passwd'
     * → '/var/app/storage/etc/passwd', which does not start with the uploads base.
     */
    private function normalisePath(string $path): string
    {
        $parts = explode('/', $path);
        $resolved = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($resolved);
            } else {
                $resolved[] = $part;
            }
        }

        return '/' . implode('/', $resolved);
    }
}
