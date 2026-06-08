<?php

declare(strict_types=1);

namespace App\Application\Ports;

/**
 * Port for file storage operations.
 *
 * Infrastructure layer implements this interface for different storage backends.
 * Application layer depends on this interface, not Storage facades.
 */
interface FileStorage
{
    /**
     * Download a file from storage to a local path.
     *
     * @param string $s3Key The S3 key or storage path
     * @param string $localPath The local filesystem path to save to
     *
     * @throws \RuntimeException if download fails
     */
    public function download(string $s3Key, string $localPath): void;

    /**
     * Delete a file from storage.
     *
     * @param string $path The storage path to delete
     */
    public function delete(string $path): void;
}
