<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Ports\FileStorage;
use Illuminate\Support\Facades\Storage;

/**
 * S3 implementation of FileStorage.
 *
 * Uses Laravel's Storage facade for S3 operations.
 */
final class S3FileStorage implements FileStorage
{
    /**
     * Stream the object from S3 to local disk in chunks.
     *
     * BCRA files can be multiple GB, so we MUST NOT load the whole object into
     * memory (Storage::get() would do exactly that). readStream() returns a PHP
     * stream that we copy chunk-by-chunk, keeping memory usage flat.
     */
    public function download(string $s3Key, string $localPath): void
    {
        $source = Storage::disk('s3')->readStream($s3Key);

        if ($source === null || $source === false) {
            throw new \RuntimeException(
                sprintf('Failed to download file from S3: %s', $s3Key)
            );
        }

        $destination = fopen($localPath, 'wb');

        if ($destination === false) {
            fclose($source);

            throw new \RuntimeException(
                sprintf('Failed to open local file for writing: %s', $localPath)
            );
        }

        try {
            // 8 MiB chunks — flat memory regardless of object size.
            if (stream_copy_to_stream($source, $destination, null, 0) === false) {
                throw new \RuntimeException(
                    sprintf('Failed to stream file from S3: %s', $s3Key)
                );
            }
        } finally {
            fclose($source);
            fclose($destination);
        }
    }

    public function delete(string $path): void
    {
        Storage::disk('s3')->delete($path);
    }
}
