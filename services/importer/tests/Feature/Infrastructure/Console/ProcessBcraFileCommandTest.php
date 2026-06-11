<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Console;

use App\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Feature tests for the ProcessBcraFileCommand Artisan command.
 *
 * Tests run against wayni_test DB. The command guard (Item 3) is the primary concern here:
 * a second import must not start while one is already pending or processing.
 */
class ProcessBcraFileCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeTempFile(): string
    {
        $dir = storage_path('app/uploads');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/cmd_test_' . uniqid() . '.txt';
        file_put_contents($path, 'dummy content');

        return $path;
    }

    // -----------------------------------------------------------------------
    // ITEM 3 — Single-active-import guard in CLI command
    // -----------------------------------------------------------------------

    public function test_command_fails_when_import_already_processing(): void
    {
        // Arrange
        Queue::fake();
        $path = $this->makeTempFile();

        ImportLog::create([
            'id'       => 'eeeeeeee-eeee-4eee-aeee-eeeeeeeeeeee',
            'filename' => 'running.txt',
            'status'   => 'processing',
        ]);

        // Act
        $exitCode = $this->artisan('bcra:process', ['path' => $path])->execute();

        // Assert — non-zero exit (FAILURE)
        $this->assertSame(1, $exitCode);

        // Cleanup
        unlink($path);
    }

    public function test_command_fails_when_import_already_pending(): void
    {
        // Arrange
        Queue::fake();
        $path = $this->makeTempFile();

        ImportLog::create([
            'id'       => 'ffffffff-ffff-4fff-afff-ffffffffffff',
            'filename' => 'queued.txt',
            'status'   => 'pending',
        ]);

        // Act
        $exitCode = $this->artisan('bcra:process', ['path' => $path])->execute();

        // Assert — non-zero exit
        $this->assertSame(1, $exitCode);

        // Cleanup
        unlink($path);
    }

    public function test_command_succeeds_when_no_active_import(): void
    {
        // Arrange
        Queue::fake();
        $path = $this->makeTempFile();

        // Completed import must NOT block a new one
        ImportLog::create([
            'id'       => 'a1a1a1a1-a1a1-4a1a-aa1a-a1a1a1a1a1a1',
            'filename' => 'old.txt',
            'status'   => 'completed',
        ]);

        // Act
        $exitCode = $this->artisan('bcra:process', ['path' => $path])->execute();

        // Assert — success
        $this->assertSame(0, $exitCode);

        // Cleanup
        unlink($path);
    }
}
