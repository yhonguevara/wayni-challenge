<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Jobs\ProcessBcraFile;
use App\Models\ImportLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_upload_with_valid_file_returns_202(): void
    {
        // Arrange
        Queue::fake();
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('deudores.txt', 'test content');

        // Act
        $response = $this->postJson('/api/upload', ['file' => $file]);

        // Assert
        $response->assertStatus(202);
        $response->assertJsonStructure(['import_id', 'status', 'message']);
        $this->assertSame('queued', $response->json('status'));
    }

    public function test_post_upload_with_valid_s3_key_returns_202(): void
    {
        // Arrange
        Queue::fake();

        // Act
        $response = $this->postJson('/api/upload', ['s3_key' => 'uploads/deudores.txt']);

        // Assert
        $response->assertStatus(202);
        $response->assertJsonStructure(['import_id', 'status', 'message']);
    }

    public function test_post_upload_with_both_file_and_s3_key_returns_202(): void
    {
        // Arrange — file takes precedence when both provided (required_without)
        Queue::fake();
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('deudores.txt', 'test content');

        // Act
        $response = $this->postJson('/api/upload', [
            'file' => $file,
            's3_key' => 'uploads/deudores.txt',
        ]);

        // Assert — file is present so validation passes
        $response->assertStatus(202);
    }

    public function test_post_upload_with_neither_file_nor_s3_key_returns_422(): void
    {
        // Act
        $response = $this->postJson('/api/upload', []);

        // Assert
        $response->assertStatus(422);
    }

    public function test_post_upload_with_invalid_file_type_returns_422(): void
    {
        // Arrange
        $file = UploadedFile::fake()->create('document.pdf', 100);

        // Act
        $response = $this->postJson('/api/upload', ['file' => $file]);

        // Assert
        $response->assertStatus(422);
    }

    public function test_post_upload_creates_import_log_record(): void
    {
        // Arrange
        Queue::fake();
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('deudores.txt', 'test content');

        // Act
        $response = $this->postJson('/api/upload', ['file' => $file]);

        // Assert
        $importId = $response->json('import_id');
        $this->assertDatabaseHas('import_logs', [
            'id' => $importId,
            'status' => 'pending',
        ]);
    }

    public function test_post_upload_dispatches_process_bcra_file_job(): void
    {
        // Arrange
        Queue::fake();
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('deudores.txt', 'test content');

        // Act
        $this->postJson('/api/upload', ['file' => $file]);

        // Assert
        Queue::assertPushed(ProcessBcraFile::class);
    }
}
