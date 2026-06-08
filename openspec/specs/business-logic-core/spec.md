# Business Logic Core Specification

## Purpose

Define the BCRA file parser, Value Objects, domain Entities, and data transformer that form the write-side processing pipeline.

## Requirements

### REQ-BIZ-001: BCRA File Parser

The system MUST parse BCRA `deudores.txt` files using fixed-position field extraction (171 characters per line, 24 fields).

#### Scenario: Valid line parsed

- GIVEN a 171-character line from `deudores.txt`
- WHEN `BcraFileParser::parseLine()` is called
- THEN all 24 fields MUST be extracted per positions defined in `leame-deudores.md`
- AND the result MUST be a `BcraRecordDTO` with typed properties

#### Scenario: Streaming via LazyCollection (RN-08)

- GIVEN a multi-line BCRA file
- WHEN `BcraFileParser::parse()` is called with a file path
- THEN it MUST return a `LazyCollection<BcraRecordDTO>`
- AND memory usage MUST NOT exceed 512MB for files up to 6GB

#### Scenario: ISO-8859-1 to UTF-8 conversion (RN-07)

- GIVEN a line encoded in ISO-8859-1
- WHEN parsed
- THEN `mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1')` MUST be applied before field extraction

### REQ-BIZ-002: Identification Type Filter (RN-03)

The parser MUST exclude records where Field 3 (identification type) is not `'11'`.

#### Scenario: Non-CUIT record filtered

- GIVEN a line with identification type `'05'`
- WHEN parsed
- THEN the record MUST NOT appear in the output collection

#### Scenario: CUIT record passes

- GIVEN a line with identification type `'11'`
- WHEN parsed
- THEN the record MUST appear in the output collection

### REQ-BIZ-003: Situation Validation (RN-04)

The parser MUST exclude records where Field 6 (situation) is not in the valid set.

Valid codes: `'01'`, `'03'`, `'04'`, `'05'`, `'11'`, `'21'`, `'23'`.

#### Scenario: Invalid situation filtered

- GIVEN a line with situation `'99'`
- WHEN parsed
- THEN the record MUST NOT appear in the output collection

#### Scenario: All valid codes accepted

- GIVEN lines with each valid situation code
- WHEN parsed
- THEN all 7 codes MUST produce records in the output

### REQ-BIZ-004: Situation Value Object

The `Situation` enum MUST represent all valid situation codes with severity ordering.

Severity (worst→best): `'05'` > `'04'` > `'03'` > `'23'` > `'21'` > `'11'` > `'01'`.

#### Scenario: Construction from valid code

- GIVEN `Situation::from('05')` is called
- WHEN evaluated
- THEN it MUST return `Situation::Irrecuperable` (or equivalent case)

#### Scenario: Invalid code rejected

- GIVEN `Situation::from('99')` is called
- WHEN evaluated
- THEN it MUST throw a `ValueError`

#### Scenario: Severity comparison

- GIVEN two `Situation` instances
- WHEN compared via a `severity()` method or ordering
- THEN `'05'` MUST rank higher than `'04'`, which ranks higher than `'01'`

### REQ-BIZ-005: Amount Value Object

The `Amount` Value Object MUST represent monetary values with parsing from BCRA format.

#### Scenario: Parse BCRA amount (RN-06)

- GIVEN the string `"000000011,1"` (11 integers + 1 decimal)
- WHEN `Amount::fromBcraString()` is called
- THEN the value MUST be `11.1` (float)

#### Scenario: Comma to period conversion

- GIVEN a BCRA amount with comma separator `"12345678901,5"`
- WHEN parsed
- THEN comma MUST be replaced with period before float conversion

#### Scenario: Addition

- GIVEN `Amount(10.5)` and `Amount(20.3)`
- WHEN `add()` is called
- THEN result MUST be `Amount(30.8)`

### REQ-BIZ-006: Cuit Value Object

The `Cuit` Value Object MUST validate and hold an 11-character identification number.

#### Scenario: Valid CUIT accepted

- GIVEN the string `"20345123458"`
- WHEN `new Cuit("20345123458")` is called
- THEN construction MUST succeed

#### Scenario: Whitespace trimmed

- GIVEN `"20345123458  "` (trailing spaces from fixed-width field)
- WHEN constructed
- THEN the stored value MUST be `"20345123458"` (trimmed)

#### Scenario: Empty value rejected

- GIVEN an empty or whitespace-only string
- WHEN constructed
- THEN it MUST throw `InvalidArgumentException`

### REQ-BIZ-007: DebtorRecord Entity

The `DebtorRecord` entity MUST represent an aggregated debtor with max situation and total loans.

#### Scenario: Construction with Value Objects

- GIVEN valid `Cuit`, `Situation`, and `Amount` instances
- WHEN `new DebtorRecord(...)` is called with named arguments
- THEN the entity MUST store all three as readonly properties

### REQ-BIZ-008: EntityRecord Entity

The `EntityRecord` entity MUST represent an aggregated financial entity with total loans.

#### Scenario: Construction

- GIVEN a valid entity code string and `Amount`
- WHEN `new EntityRecord(...)` is called
- THEN the entity MUST store `entityCode` and `totalLoans` as readonly properties

### REQ-BIZ-009: BcraDataTransformer — Debtor Aggregation (RN-01)

The transformer MUST group records by `identification_number`, computing MAX(situation) and SUM(loans).

#### Scenario: Single debtor, multiple entities

- GIVEN 3 records with the same `identification_number` but different entities and situations `'01'`, `'03'`, `'05'`
- WHEN `BcraDataTransformer::transformDebtors()` is called
- THEN output MUST contain 1 `DebtorRecord` with `maxSituation = '05'` and `totalLoans = SUM` of all 3

#### Scenario: Situation severity ordering (RN-05)

- GIVEN records with situations `'01'` and `'23'`
- WHEN MAX is computed
- THEN result MUST be `'23'` (higher severity per ordering: `'05'`>`'04'`>`'03'`>`'23'`>`'21'`>`'11'`>`'01'`)

### REQ-BIZ-010: BcraDataTransformer — Entity Aggregation (RN-02)

The transformer MUST group records by `entity_code`, computing SUM(loans).

#### Scenario: Single entity, multiple debtors

- GIVEN 5 records with the same `entity_code` and loans `10.0`, `20.0`, `30.0`, `40.0`, `50.0`
- WHEN `BcraDataTransformer::transformEntities()` is called
- THEN output MUST contain 1 `EntityRecord` with `totalLoans = 150.0`

#### Scenario: Multiple entities

- GIVEN records for entity codes `'00001'` and `'00002'`
- WHEN transformed
- THEN output MUST contain 2 `EntityRecord` instances with independent sums
