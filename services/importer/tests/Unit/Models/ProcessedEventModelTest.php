<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ProcessedEvent;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ProcessedEvent Eloquent model.
 *
 * Tests inspect model metadata only — no DB connection required.
 */
class ProcessedEventModelTest extends TestCase
{
    private ProcessedEvent $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new ProcessedEvent();
    }

    public function test_table_is_processed_events(): void
    {
        $this->assertSame('processed_events', $this->model->getTable());
    }

    public function test_fillable_contains_import_id(): void
    {
        $this->assertContains('import_id', $this->model->getFillable());
    }

    public function test_fillable_contains_event_id(): void
    {
        $this->assertContains('event_id', $this->model->getFillable());
    }

    public function test_model_has_no_updated_at_timestamp(): void
    {
        $this->assertFalse($this->model->usesTimestamps() && $this->model->getUpdatedAtColumn() !== null
            ? (bool) $this->model->getUpdatedAtColumn()
            : false
        );
    }
}
