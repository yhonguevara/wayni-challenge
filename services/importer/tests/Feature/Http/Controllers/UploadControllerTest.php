<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Jobs\ProcessBcraFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UploadControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeTestFile(): string
    {
        $dir = storage_path('app/uploads');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $testFile = $dir . '/test.txt';
        file_put_contents($testFile, 'test content');

        return $testFile;
    }

    public function test_post_upload_with_valid_local_path_returns_202(): void
    {
        // Arrange
        Queue::fake();
        $testFile = $this->makeTestFile();

        // Act
        $response = $this->postJson('/api/upload', ['path' => $testFile]);

        // Assert
        $response->assertStatus(202);
        $response->assertJsonStructure(['import_log_id', 'status', 'message']);
        $this->assertSame('queued', $response->json('status'));

        // Cleanup
        unlink($testFile);
    }

    public function test_post_upload_with_nonexistent_path_returns_404(): void
    {
        // Arrange
        Queue::fake();

        // Act
        $response = $this->postJson('/api/upload', ['path' => '/nonexistent/file.txt']);

        // Assert
        $response->assertStatus(404);
        $response->assertJsonStructure(['error', 'path']);
    }

    public function test_post_upload_without_path_returns_422(): void
    {
        // Act
        $response = $this->postJson('/api/upload', []);

        // Assert
        $response->assertStatus(422);
    }

    public function test_post_upload_creates_import_log_record(): void
    {
        // Arrange
        Queue::fake();
        $testFile = $this->makeTestFile();

        // Act
        $response = $this->postJson('/api/upload', ['path' => $testFile]);

        // Assert
        $importId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', [
            'id' => $importId,
            'status' => 'pending',
        ]);

        // Cleanup
        unlink($testFile);
    }

    public function test_post_upload_dispatches_process_bcra_file_job(): void
    {
        // Arrange
        Queue::fake();
        $testFile = $this->makeTestFile();

        // Act
        $this->postJson('/api/upload', ['path' => $testFile]);

        // Assert
        Queue::assertPushed(ProcessBcraFile::class);

        // Cleanup
        unlink($testFile);
    }

    public function test_post_notify_upload_with_s3_key_returns_202(): void
    {
        // Arrange
        Queue::fake();

        // Act — called by the browser after a direct-to-S3 pre-signed upload
        $response = $this->postJson('/api/notify-upload', [
            'key' => 'uploads/abc-deudores.txt',
        ]);

        // Assert
        $response->assertStatus(202);
        $response->assertJsonStructure(['import_log_id', 'status', 'message']);
        $this->assertSame('queued', $response->json('status'));
    }

    public function test_post_notify_upload_without_key_returns_422(): void
    {
        // Act
        $response = $this->postJson('/api/notify-upload', []);

        // Assert
        $response->assertStatus(422);
    }

    public function test_post_notify_upload_creates_import_log_and_dispatches_job(): void
    {
        // Arrange
        Queue::fake();

        // Act
        $response = $this->postJson('/api/notify-upload', [
            'key' => 'uploads/abc-deudores.txt',
        ]);

        // Assert
        $importId = $response->json('import_log_id');
        $this->assertDatabaseHas('import_logs', [
            'id' => $importId,
            'status' => 'pending',
        ]);
        Queue::assertPushed(ProcessBcraFile::class);
    }
}
