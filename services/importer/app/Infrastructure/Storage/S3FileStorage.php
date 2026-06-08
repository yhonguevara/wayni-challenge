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
    public function download(string $s3Key, string $localPath): void
    {
        $contents = Storage::disk('s3')->get($s3Key);

        if ($contents === null) {
            throw new \RuntimeException(
                sprintf('Failed to download file from S3: %s', $s3Key)
            );
        }

        file_put_contents($localPath, $contents);
    }

    public function delete(string $path): void
    {
        Storage::disk('s3')->delete($path);
    }
}
