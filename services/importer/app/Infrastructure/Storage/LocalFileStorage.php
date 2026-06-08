<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Ports\FileStorage;
use Illuminate\Support\Facades\Storage;

/**
 * Local filesystem implementation of FileStorage.
 *
 * Uses Laravel's Storage facade for local disk operations.
 */
final class LocalFileStorage implements FileStorage
{
    public function download(string $s3Key, string $localPath): void
    {
        $contents = Storage::disk('local')->get($s3Key);

        if ($contents === null) {
            throw new \RuntimeException(
                sprintf('Failed to download file from local storage: %s', $s3Key)
            );
        }

        file_put_contents($localPath, $contents);
    }

    public function delete(string $path): void
    {
        Storage::disk('local')->delete($path);
    }
}
