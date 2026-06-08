<?php

declare(strict_types=1);

namespace App\Application\Transformer;

use App\Application\DTOs\BcraRecordDTO;
use App\Domain\Entities\DebtorRecord;
use App\Domain\Entities\EntityRecord;
use App\Domain\ValueObjects\Amount;
use App\Domain\ValueObjects\Cuit;
use App\Domain\ValueObjects\Situation;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Transforms parsed BCRA records into aggregated debtor and entity collections.
 *
 * Debtor aggregation (RN-01): Group by identification_number → MAX(situation) + SUM(loans)
 * Entity aggregation (RN-02): Group by entity_code → SUM(loans)
 *
 * Processes in batches of 500 for memory efficiency.
 */
final class BcraDataTransformer
{
    private const BATCH_SIZE = 500;

    /**
     * @param LazyCollection<int, BcraRecordDTO>|Collection<int, BcraRecordDTO> $records
     */
    public function __construct(
        private readonly LazyCollection|Collection $records,
    ) {}

    /**
     * Transform records into aggregated debtors and entities.
     *
     * @return array{debtors: Collection<int, DebtorRecord>, entities: Collection<int, EntityRecord>}
     */
    public function transform(): array
    {
        /** @var array<string, array{situation: Situation, loans: Amount, entityCode: string}> $debtorAccumulator */
        $debtorAccumulator = [];

        /** @var array<string, array{loans: Amount}> $entityAccumulator */
        $entityAccumulator = [];

        $batch = [];

        foreach ($this->records as $record) {
            $batch[] = $record;

            if (count($batch) >= self::BATCH_SIZE) {
                $this->processBatch($batch, $debtorAccumulator, $entityAccumulator);
                $batch = [];
            }
        }

        // Process remaining records
        if ($batch !== []) {
            $this->processBatch($batch, $debtorAccumulator, $entityAccumulator);
        }

        return [
            'debtors' => $this->buildDebtors($debtorAccumulator),
            'entities' => $this->buildEntities($entityAccumulator),
        ];
    }

    /**
     * Process a batch of records, accumulating into debtor and entity aggregators.
     *
     * @param array<int, BcraRecordDTO> $batch
     * @param array<string, array{situation: Situation, loans: Amount, entityCode: string}> &$debtorAccumulator
     * @param array<string, array{loans: Amount}> &$entityAccumulator
     */
    private function processBatch(
        array $batch,
        array &$debtorAccumulator,
        array &$entityAccumulator,
    ): void {
        foreach ($batch as $record) {
            $this->accumulateDebtor($record, $debtorAccumulator);
            $this->accumulateEntity($record, $entityAccumulator);
        }
    }

    /**
     * Accumulate debtor data: MAX(situation) + SUM(loans).
     *
     * @param array<string, array{situation: Situation, loans: Amount, entityCode: string}> &$accumulator
     */
    private function accumulateDebtor(
        BcraRecordDTO $record,
        array &$accumulator,
    ): void {
        $key = $record->identificationNumber;
        $currentSituation = Situation::from($record->situation);
        $currentLoans = new Amount($record->loans);

        if (!isset($accumulator[$key])) {
            $accumulator[$key] = [
                'situation' => $currentSituation,
                'loans' => $currentLoans,
                'entityCode' => $record->entityCode,
            ];

            return;
        }

        // MAX(situation) using severity comparison (RN-01)
        if ($currentSituation->isWorseThan($accumulator[$key]['situation'])) {
            $accumulator[$key]['situation'] = $currentSituation;
        }

        // SUM(loans) (RN-01)
        $accumulator[$key]['loans'] = $accumulator[$key]['loans']->add($currentLoans);
    }

    /**
     * Accumulate entity data: SUM(loans).
     *
     * @param array<string, array{loans: Amount}> &$accumulator
     */
    private function accumulateEntity(
        BcraRecordDTO $record,
        array &$accumulator,
    ): void {
        $key = $record->entityCode;
        $currentLoans = new Amount($record->loans);

        if (!isset($accumulator[$key])) {
            $accumulator[$key] = [
                'loans' => $currentLoans,
            ];

            return;
        }

        // SUM(loans) (RN-02)
        $accumulator[$key]['loans'] = $accumulator[$key]['loans']->add($currentLoans);
    }

    /**
     * Build DebtorRecord collection from accumulator.
     *
     * @param array<string, array{situation: Situation, loans: Amount, entityCode: string}> $accumulator
     * @return Collection<int, DebtorRecord>
     */
    private function buildDebtors(array $accumulator): Collection
    {
        $debtors = [];

        foreach ($accumulator as $identificationNumber => $data) {
            $debtors[] = new DebtorRecord(
                identificationNumber: Cuit::fromString((string) $identificationNumber),
                maxSituation: $data['situation'],
                totalLoans: $data['loans'],
                entityCode: $data['entityCode'],
            );
        }

        return collect($debtors);
    }

    /**
     * Build EntityRecord collection from accumulator.
     *
     * @param array<string, array{loans: Amount}> $accumulator
     * @return Collection<int, EntityRecord>
     */
    private function buildEntities(array $accumulator): Collection
    {
        $entities = [];

        foreach ($accumulator as $entityCode => $data) {
            $entities[] = new EntityRecord(
                entityCode: $entityCode,
                totalLoans: $data['loans'],
            );
        }

        return collect($entities);
    }
}
