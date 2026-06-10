<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Debtor;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Debtor Eloquent model.
 *
 * Tests inspect model metadata only — no DB connection required.
 */
class DebtorModelTest extends TestCase
{
    private Debtor $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new Debtor();
    }

    public function test_table_is_debtors(): void
    {
        $this->assertSame('debtors', $this->model->getTable());
    }

    public function test_fillable_contains_identification_number(): void
    {
        $this->assertContains('identification_number', $this->model->getFillable());
    }

    public function test_fillable_contains_max_situation(): void
    {
        $this->assertContains('max_situation', $this->model->getFillable());
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
