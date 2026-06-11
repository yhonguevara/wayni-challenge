<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Jobs\ProcessBcraFile;
use App\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

    // -----------------------------------------------------------------------
    // ITEM 4 — /api/v1 versioned routes
    // -----------------------------------------------------------------------

    public function test_post_upload_with_valid_local_path_returns_202(): void
    {
        // Arrange
        Queue::fake();
        $testFile = $this->makeTestFile();

        // Act
        $response = $this->postJson('/api/v1/upload', ['path' => $testFile]);

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

        // Act — path is inside uploads/ but does not exist → 404
        $response = $this->postJson('/api/v1/upload', [
            'path' => storage_path('app/uploads/does-not-exist.txt'),
        ]);

        // Assert
        $response->assertStatus(404);
        $response->assertJsonStructure(['error', 'path']);
    }

    public function test_post_upload_without_path_returns_422(): void
    {
        // Act
        $response = $this->postJson('/api/v1/upload', []);

        // Assert
        $response->assertStatus(422);
    }

    public function test_post_upload_creates_import_log_record(): void
    {
        // Arrange
        Queue::fake();
        $testFile = $this->makeTestFile();

        // Act
        $response = $this->postJson('/api/v1/upload', ['path' => $testFile]);

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
        $this->postJson('/api/v1/upload', ['path' => $testFile]);

        // Assert
        Queue::assertPushed(ProcessBcraFile::class);

        // Cleanup
        unlink($testFile);
    }

    // -----------------------------------------------------------------------
    // ITEM 1 — Path-traversal guard on POST /api/v1/upload
    // -----------------------------------------------------------------------

    public function test_post_upload_with_path_outside_allowed_base_returns_422(): void
    {
        // Arrange
        Queue::fake();

        // Act — /etc/passwd is outside storage/app/uploads → 422
        $response = $this->postJson('/api/v1/upload', ['path' => '/etc/passwd']);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    public function test_post_upload_with_traversal_path_returns_422(): void
    {
        // Arrange
        Queue::fake();
        // A path that resolves outside the uploads base via symlink traversal attempt
        $response = $this->postJson('/api/v1/upload', [
            'path' => storage_path('app/uploads/../../../../../../etc/passwd'),
        ]);

        // Assert — traversal detected even with naive string, resolved path is /etc/passwd
        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    public function test_post_upload_with_path_inside_uploads_but_not_found_returns_404(): void
    {
        // Arrange
        Queue::fake();

        // Act — path IS inside the allowed base but file does not exist → 404
        $response = $this->postJson('/api/v1/upload', [
            'path' => storage_path('app/uploads/nonexistent-file.txt'),
        ]);

        // Assert — not a traversal, but file missing → 404
        $response->assertStatus(404);
        $response->assertJsonStructure(['error', 'path']);
    }

    // -----------------------------------------------------------------------
    // ITEM 2 — notify-upload validates S3 key prefix + existence
    // -----------------------------------------------------------------------

    public function test_post_notify_upload_with_s3_key_returns_202(): void
    {
        // Arrange
        Queue::fake();
        Storage::fake('s3');
        Storage::disk('s3')->put('uploads/abc-deudores.txt', 'data');

        // Act — called by the browser after a direct-to-S3 pre-signed upload
        $response = $this->postJson('/api/v1/notify-upload', [
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
        $response = $this->postJson('/api/v1/notify-upload', []);

        // Assert
        $response->assertStatus(422);
    }

    public function test_post_notify_upload_creates_import_log_and_dispatches_job(): void
    {
        // Arrange
        Queue::fake();
        Storage::fake('s3');
        Storage::disk('s3')->put('uploads/abc-deudores.txt', 'data');

        // Act
        $response = $this->postJson('/api/v1/notify-upload', [
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

    public function test_post_notify_upload_with_key_not_starting_with_uploads_returns_422(): void
    {
        // Arrange
        Queue::fake();
        Storage::fake('s3');

        // Act — key does not start with 'uploads/'
        $response = $this->postJson('/api/v1/notify-upload', [
            'key' => 'other-prefix/deudores.txt',
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    public function test_post_notify_upload_with_key_not_existing_in_s3_returns_404(): void
    {
        // Arrange
        Queue::fake();
        Storage::fake('s3');
        // Do NOT put anything in the fake S3 — key should be missing

        // Act — key has correct prefix but does not exist in S3
        $response = $this->postJson('/api/v1/notify-upload', [
            'key' => 'uploads/missing-file.txt',
        ]);

        // Assert
        $response->assertStatus(404);
        $response->assertJsonStructure(['error']);
    }

    // -----------------------------------------------------------------------
    // ITEM 3 — Single-active-import guard (409 Conflict)
    // -----------------------------------------------------------------------

    public function test_post_upload_returns_409_when_import_already_in_progress(): void
    {
        // Arrange
        Queue::fake();
        $testFile = $this->makeTestFile();

        // Seed an active import log with status 'processing'
        ImportLog::create([
            'id'       => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'filename' => 'active.txt',
            'status'   => 'processing',
        ]);

        // Act
        $response = $this->postJson('/api/v1/upload', ['path' => $testFile]);

        // Assert
        $response->assertStatus(409);
        $response->assertJsonStructure(['error', 'import_log_id']);
        $this->assertSame('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', $response->json('import_log_id'));

        // Cleanup
        unlink($testFile);
    }

    public function test_post_upload_returns_409_when_import_is_pending(): void
    {
        // Arrange
        Queue::fake();
        $testFile = $this->makeTestFile();

        ImportLog::create([
            'id'       => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            'filename' => 'pending.txt',
            'status'   => 'pending',
        ]);

        // Act
        $response = $this->postJson('/api/v1/upload', ['path' => $testFile]);

        // Assert
        $response->assertStatus(409);
        $response->assertJsonStructure(['error', 'import_log_id']);

        // Cleanup
        unlink($testFile);
    }

    public function test_post_upload_succeeds_when_previous_import_is_completed(): void
    {
        // Arrange
        Queue::fake();
        $testFile = $this->makeTestFile();

        ImportLog::create([
            'id'       => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
            'filename' => 'done.txt',
            'status'   => 'completed',
        ]);

        // Act — no active import, should proceed
        $response = $this->postJson('/api/v1/upload', ['path' => $testFile]);

        // Assert
        $response->assertStatus(202);

        // Cleanup
        unlink($testFile);
    }

    public function test_post_notify_upload_returns_409_when_import_already_in_progress(): void
    {
        // Arrange
        Queue::fake();
        Storage::fake('s3');
        Storage::disk('s3')->put('uploads/test.txt', 'data');

        ImportLog::create([
            'id'       => 'dddddddd-dddd-dddd-dddd-dddddddddddd',
            'filename' => 'active.txt',
            'status'   => 'processing',
        ]);

        // Act
        $response = $this->postJson('/api/v1/notify-upload', [
            'key' => 'uploads/test.txt',
        ]);

        // Assert
        $response->assertStatus(409);
        $response->assertJsonStructure(['error', 'import_log_id']);
        $this->assertSame('dddddddd-dddd-dddd-dddd-dddddddddddd', $response->json('import_log_id'));
    }
}
