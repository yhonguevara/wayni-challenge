<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Handlers;

use App\Application\DTOs\DebtorProcessedEvent;
use App\Infrastructure\Handlers\UpsertDebtorHandler;
use App\Models\Debtor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpsertDebtorHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_creates_new_debtor_record(): void
    {
        // Arrange
        $event = new DebtorProcessedEvent(
            identificationNumber: '20345123458',
            maxSituation: '05',
            totalLoans: 15000.50,
            importId: 'import-001',
        );
        $handler = new UpsertDebtorHandler();

        // Act
        $handler->handle($event);

        // Assert
        $this->assertDatabaseHas('debtors', [
            'identification_number' => '20345123458',
            'max_situation' => '05',
        ]);
    }

    public function test_handle_updates_existing_debtor_idempotent(): void
    {
        // Arrange
        Debtor::create([
            'identification_number' => '20345123458',
            'max_situation' => '01',
            'total_loan_amount' => 1000.00,
        ]);

        $event = new DebtorProcessedEvent(
            identificationNumber: '20345123458',
            maxSituation: '05',
            totalLoans: 15000.50,
            importId: 'import-001',
        );
        $handler = new UpsertDebtorHandler();

        // Act
        $handler->handle($event);

        // Assert — no duplicate rows
        $this->assertSame(1, Debtor::where('identification_number', '20345123458')->count());
        $debtor = Debtor::where('identification_number', '20345123458')->first();
        $this->assertSame('05', $debtor->max_situation);
    }

    public function test_handle_deserializes_event_correctly(): void
    {
        // Arrange
        $event = DebtorProcessedEvent::fromArray([
            'identificationNumber' => '20345123458',
            'maxSituation' => '03',
            'totalLoans' => 5000.0,
            'importId' => 'import-002',
        ]);
        $handler = new UpsertDebtorHandler();

        // Act
        $handler->handle($event);

        // Assert
        $this->assertDatabaseHas('debtors', [
            'identification_number' => '20345123458',
            'max_situation' => '03',
        ]);
    }
}
