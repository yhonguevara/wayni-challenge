<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Transformer;

use App\Application\DTOs\BcraRecordDTO;
use App\Application\Transformer\BcraDataTransformer;
use App\Domain\ValueObjects\Situation;
use PHPUnit\Framework\TestCase;

class BcraDataTransformerTest extends TestCase
{
    private function createRecord(
        string $entityCode = '00001',
        string $identificationNumber = '20345123458',
        string $situation = '01',
        float $loans = 1000.0,
    ): BcraRecordDTO {
        return new BcraRecordDTO(
            entityCode: $entityCode,
            infoDate: '202601',
            identificationType: '11',
            identificationNumber: $identificationNumber,
            activity: '001',
            situation: $situation,
            loans: $loans,
            unused: 0.0,
            guarantees: 0.0,
            otherConcepts: 0.0,
            preferredGuaranteesA: 0.0,
            preferredGuaranteesB: 0.0,
            noPreferredGuarantees: 0.0,
            counterGuaranteesA: 0.0,
            counterGuaranteesB: 0.0,
            noCounterGuarantees: 0.0,
            provisions: 0.0,
            debtCovered: '0',
            judicialProcess: '0',
            refinancing: '0',
            mandatoryRecat: '0',
            legalSituation: '0',
            technicalIrrecoverable: '0',
            daysOverdue: 0,
        );
    }

    public function test_transform_groups_debtors_by_identification_number(): void
    {
        // Arrange — 3 records for same debtor across different entities
        $records = collect([
            $this->createRecord(identificationNumber: '20345123458', situation: '01', loans: 1000.0),
            $this->createRecord(entityCode: '00002', identificationNumber: '20345123458', situation: '03', loans: 2000.0),
            $this->createRecord(entityCode: '00003', identificationNumber: '20345123458', situation: '05', loans: 3000.0),
        ]);

        $transformer = new BcraDataTransformer($records);

        // Act
        $result = $transformer->transform();

        // Assert — 1 debtor with MAX situation and SUM loans
        $this->assertCount(1, $result['debtors']);
        $debtor = $result['debtors']->first();
        $this->assertSame('20345123458', $debtor->identificationNumber->value());
        $this->assertSame(Situation::Unrecoverable, $debtor->situation); // '05' is worst
        $this->assertSame(6000.0, $debtor->loansAmount->toFloat()); // 1000+2000+3000
    }

    public function test_transform_max_situation_uses_severity_ordering(): void
    {
        // Arrange — records with situations that test severity ordering
        $records = collect([
            $this->createRecord(situation: '01', loans: 100.0),  // severity 0
            $this->createRecord(entityCode: '00002', situation: '23', loans: 200.0),  // severity 3
            $this->createRecord(entityCode: '00003', situation: '21', loans: 300.0),  // severity 2
        ]);

        $transformer = new BcraDataTransformer($records);

        // Act
        $result = $transformer->transform();

        // Assert — '23' (SpecialTreatment, severity 3) is worst
        $debtor = $result['debtors']->first();
        $this->assertSame(Situation::SpecialTreatment, $debtor->situation);
    }

    public function test_transform_groups_entities_by_entity_code(): void
    {
        // Arrange — multiple records for same entity
        $records = collect([
            $this->createRecord(entityCode: '00001', identificationNumber: '20345123458', loans: 1000.0),
            $this->createRecord(entityCode: '00001', identificationNumber: '20345123459', loans: 2000.0),
            $this->createRecord(entityCode: '00002', identificationNumber: '20345123460', loans: 500.0),
        ]);

        $transformer = new BcraDataTransformer($records);

        // Act
        $result = $transformer->transform();

        // Assert — 2 entities with correct sums
        $this->assertCount(2, $result['entities']);

        $entity1 = $result['entities']->firstWhere('entityCode', '00001');
        $this->assertSame(3000.0, $entity1->loansAmount->toFloat()); // 1000+2000

        $entity2 = $result['entities']->firstWhere('entityCode', '00002');
        $this->assertSame(500.0, $entity2->loansAmount->toFloat());
    }

    public function test_transform_sums_loans_per_debtor(): void
    {
        // Arrange
        $records = collect([
            $this->createRecord(loans: 100.5),
            $this->createRecord(entityCode: '00002', loans: 200.3),
            $this->createRecord(entityCode: '00003', loans: 300.2),
        ]);

        $transformer = new BcraDataTransformer($records);

        // Act
        $result = $transformer->transform();

        // Assert
        $debtor = $result['debtors']->first();
        $this->assertEqualsWithDelta(601.0, $debtor->loansAmount->toFloat(), 0.01);
    }

    public function test_transform_sums_loans_per_entity(): void
    {
        // Arrange
        $records = collect([
            $this->createRecord(entityCode: '00001', identificationNumber: '20345123458', loans: 100.0),
            $this->createRecord(entityCode: '00001', identificationNumber: '20345123459', loans: 200.0),
            $this->createRecord(entityCode: '00001', identificationNumber: '20345123460', loans: 300.0),
        ]);

        $transformer = new BcraDataTransformer($records);

        // Act
        $result = $transformer->transform();

        // Assert
        $entity = $result['entities']->first();
        $this->assertSame(600.0, $entity->loansAmount->toFloat());
    }

    public function test_transform_independent_debtor_and_entity_grouping(): void
    {
        // Arrange — 2 debtors across 2 entities
        $records = collect([
            $this->createRecord(entityCode: '00001', identificationNumber: '20345123458', situation: '01', loans: 100.0),
            $this->createRecord(entityCode: '00002', identificationNumber: '20345123458', situation: '03', loans: 200.0),
            $this->createRecord(entityCode: '00001', identificationNumber: '20345123459', situation: '05', loans: 500.0),
        ]);

        $transformer = new BcraDataTransformer($records);

        // Act
        $result = $transformer->transform();

        // Assert — 2 debtors, 2 entities
        $this->assertCount(2, $result['debtors']);
        $this->assertCount(2, $result['entities']);

        // Debtor 1: MAX situation = '03', SUM loans = 300
        $debtor1 = $result['debtors']->firstWhere(fn($d) => $d->identificationNumber->value() === '20345123458');
        $this->assertSame(Situation::MediumRisk, $debtor1->situation);
        $this->assertSame(300.0, $debtor1->loansAmount->toFloat());

        // Entity 1: SUM loans = 600 (100 + 500)
        $entity1 = $result['entities']->firstWhere('entityCode', '00001');
        $this->assertSame(600.0, $entity1->loansAmount->toFloat());
    }

    public function test_transform_empty_collection(): void
    {
        // Arrange
        $records = collect([]);
        $transformer = new BcraDataTransformer($records);

        // Act
        $result = $transformer->transform();

        // Assert
        $this->assertCount(0, $result['debtors']);
        $this->assertCount(0, $result['entities']);
    }

    public function test_transform_seven_situation_codes_all_handled(): void
    {
        // Arrange — one record per valid situation code
        $situations = ['01', '03', '04', '05', '11', '21', '23'];
        $records = collect();
        foreach ($situations as $i => $sit) {
            $records->push($this->createRecord(
                entityCode: sprintf('%05d', $i + 1),
                identificationNumber: sprintf('20345123%03d', $i),
                situation: $sit,
                loans: 100.0 * ($i + 1),
            ));
        }

        $transformer = new BcraDataTransformer($records);

        // Act
        $result = $transformer->transform();

        // Assert — 7 debtors, 7 entities
        $this->assertCount(7, $result['debtors']);
        $this->assertCount(7, $result['entities']);

        // Worst situation across all is '05' (Unrecoverable)
        $worstDebtor = $result['debtors']->firstWhere(fn($d) => $d->identificationNumber->value() === '20345123003');
        $this->assertSame(Situation::Unrecoverable, $worstDebtor->situation);
    }
}
