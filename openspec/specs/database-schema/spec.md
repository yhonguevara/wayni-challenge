# Database Schema Specification

## Purpose

Define the database migrations and Eloquent models for both services' databases.

## Requirements

### REQ-DB-001: Import Logs Migration (Importer DB)

The importer service MUST define a migration for the `import_logs` table.

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | `bigIncrements` | PK |
| `filename` | `string(255)` | NOT NULL |
| `status` | `string(20)` | NOT NULL, default `'pending'` |
| `total_lines` | `integer` | nullable |
| `total_debtors` | `integer` | nullable |
| `total_entities` | `integer` | nullable |
| `duration_ms` | `integer` | nullable |
| `error_message` | `text` | nullable |
| `started_at` | `timestampTz` | nullable |
| `finished_at` | `timestampTz` | nullable |
| `created_at` | `timestampTz` | NOT NULL |
| `updated_at` | `timestampTz` | NOT NULL |

#### Scenario: Migration runs successfully

- GIVEN the importer database is accessible
- WHEN `php artisan migrate` is executed
- THEN the `import_logs` table MUST be created with all columns

#### Scenario: Down migration drops table

- GIVEN the `import_logs` table exists
- WHEN `php artisan migrate:rollback` is executed
- THEN the table MUST be dropped

### REQ-DB-002: Debtors Migration (Query DB)

The query service MUST define a migration for the `debtors` table.

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | `bigIncrements` | PK |
| `identification_number` | `string(11)` | NOT NULL, UNIQUE |
| `max_situation` | `string(2)` | NOT NULL |
| `total_loan_amount` | `decimal(18,2)` | NOT NULL, default `0` |
| `created_at` | `timestampTz` | NOT NULL |
| `updated_at` | `timestampTz` | NOT NULL |

Indexes: `idx_debtors_situation` on `max_situation`, `idx_debtors_loan_amount` on `total_loan_amount DESC`.

#### Scenario: Unique constraint on identification_number

- GIVEN a debtor with `identification_number = '20345123458'` exists
- WHEN an insert with the same `identification_number` is attempted
- THEN a unique constraint violation MUST occur (enables upsert via RN-03)

#### Scenario: Indexes created

- GIVEN the migration has run
- WHEN indexes are inspected
- THEN `idx_debtors_situation` and `idx_debtors_loan_amount` MUST exist

### REQ-DB-003: Entities Migration (Query DB)

The query service MUST define a migration for the `entities` table.

| Column | Type | Constraints |
|--------|------|-------------|
| `id` | `bigIncrements` | PK |
| `entity_code` | `string(5)` | NOT NULL, UNIQUE |
| `total_loan_amount` | `decimal(18,2)` | NOT NULL, default `0` |
| `created_at` | `timestampTz` | NOT NULL |
| `updated_at` | `timestampTz` | NOT NULL |

Index: `idx_entities_loan_amount` on `total_loan_amount DESC`.

#### Scenario: Unique constraint on entity_code

- GIVEN an entity with `entity_code = '00001'` exists
- WHEN an insert with the same `entity_code` is attempted
- THEN a unique constraint violation MUST occur

### REQ-DB-004: ImportLog Eloquent Model

The importer service MUST define an `ImportLog` Eloquent model.

#### Scenario: Model maps to import_logs

- GIVEN the `ImportLog` model
- WHEN its `$table` property is inspected
- THEN it MUST be `'import_logs'`

#### Scenario: Casts defined

- GIVEN the model's `$casts` property
- WHEN inspected
- THEN `started_at` and `finished_at` MUST be cast to `datetime`
- AND `total_lines`, `total_debtors`, `total_entities`, `duration_ms` MUST be cast to `integer`

### REQ-DB-005: Debtor Eloquent Model

The query service MUST define a `Debtor` Eloquent model.

#### Scenario: Model maps to debtors

- GIVEN the `Debtor` model
- WHEN its `$table` property is inspected
- THEN it MUST be `'debtors'`

#### Scenario: Casts defined

- GIVEN the model's `$casts` property
- WHEN inspected
- THEN `total_loan_amount` MUST be cast to `decimal:2`

### REQ-DB-006: Entity Eloquent Model

The query service MUST define an `Entity` Eloquent model.

#### Scenario: Model maps to entities

- GIVEN the `Entity` model
- WHEN its `$table` property is inspected
- THEN it MUST be `'entities'`

#### Scenario: Casts defined

- GIVEN the model's `$casts` property
- WHEN inspected
- THEN `total_loan_amount` MUST be cast to `decimal:2`
