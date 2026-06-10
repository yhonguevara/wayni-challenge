<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Entity;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Entity Eloquent model.
 *
 * Tests inspect model metadata only — no DB connection required.
 */
class EntityModelTest extends TestCase
{
    private Entity $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new Entity();
    }

    public function test_table_is_entities(): void
    {
        $this->assertSame('entities', $this->model->getTable());
    }

    public function test_fillable_contains_entity_code(): void
    {
        $this->assertContains('entity_code', $this->model->getFillable());
    }

    public function test_fillable_contains_total_loan_amount(): void
    {
        $this->assertContains('total_loan_amount', $this->model->getFillable());
    }

    public function test_total_loan_amount_is_cast_to_decimal(): void
    {
        $casts = $this->model->getCasts();
        $this->assertArrayHasKey('total_loan_amount', $casts);
    }
}
