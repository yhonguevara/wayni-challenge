<?php

declare(strict_types=1);

namespace App\Application\DTOs;

/**
 * Data Transfer Object for a single parsed BCRA deudores record.
 *
 * Maps the 24 fixed-width fields from a 171-character BCRA line.
 * All positions are 1-indexed per leame-deudores.md specification.
 */
final readonly class BcraRecordDTO
{
    /**
     * @param string $entityCode           Field 1: Código de entidad (pos 1-5, 5 chars)
     * @param string $infoDate             Field 2: Fecha de información (pos 6-11, 6 chars, AAAAMM)
     * @param string $identificationType   Field 3: Tipo de identificación (pos 12-13, 2 chars)
     * @param string $identificationNumber Field 4: N° de identificación (pos 14-24, 11 chars, CHARACTER)
     * @param string $activity             Field 5: Actividad (pos 25-27, 3 chars)
     * @param string $situation            Field 6: Situación (pos 28-29, 2 chars)
     * @param float  $loans                Field 7: Préstamos (pos 30-41, 12 chars, 11 int + 1 dec)
     * @param float  $unused               Field 8: Sin uso (pos 42-53, 12 chars)
     * @param float  $guarantees           Field 9: Garantías otorgadas (pos 54-65, 12 chars)
     * @param float  $otherConcepts        Field 10: Otros conceptos (pos 66-77, 12 chars)
     * @param float  $preferredGuaranteesA Field 11: Garantías preferidas "A" (pos 78-89, 12 chars)
     * @param float  $preferredGuaranteesB Field 12: Garantías preferidas "B" (pos 90-101, 12 chars)
     * @param float  $noPreferredGuarantees Field 13: Sin garantías preferidas (pos 102-113, 12 chars)
     * @param float  $counterGuaranteesA   Field 14: Contragarantías preferidas "A" (pos 114-125, 12 chars)
     * @param float  $counterGuaranteesB   Field 15: Contragarantías preferidas "B" (pos 126-137, 12 chars)
     * @param float  $noCounterGuarantees  Field 16: Sin contragarantías preferidas (pos 138-149, 12 chars)
     * @param float  $provisions           Field 17: Previsiones (pos 150-161, 12 chars)
     * @param string $debtCovered          Field 18: Deuda cubierta (pos 162, 1 char)
     * @param string $judicialProcess      Field 19: Proceso Judicial / Revisión (pos 163, 1 char)
     * @param string $refinancing          Field 20: Refinanciaciones (pos 164, 1 char)
     * @param string $mandatoryRecat       Field 21: Recategorización obligatoria (pos 165, 1 char)
     * @param string $legalSituation       Field 22: Situación jurídica (pos 166, 1 char)
     * @param string $technicalIrrecoverable Field 23: Irrecuperables por disposición técnica (pos 167, 1 char)
     * @param int    $daysOverdue          Field 24: Días de atraso (pos 168-171, 4 chars)
     */
    public function __construct(
        public string $entityCode,
        public string $infoDate,
        public string $identificationType,
        public string $identificationNumber,
        public string $activity,
        public string $situation,
        public float $loans,
        public float $unused,
        public float $guarantees,
        public float $otherConcepts,
        public float $preferredGuaranteesA,
        public float $preferredGuaranteesB,
        public float $noPreferredGuarantees,
        public float $counterGuaranteesA,
        public float $counterGuaranteesB,
        public float $noCounterGuarantees,
        public float $provisions,
        public string $debtCovered,
        public string $judicialProcess,
        public string $refinancing,
        public string $mandatoryRecat,
        public string $legalSituation,
        public string $technicalIrrecoverable,
        public int $daysOverdue,
    ) {}
}
