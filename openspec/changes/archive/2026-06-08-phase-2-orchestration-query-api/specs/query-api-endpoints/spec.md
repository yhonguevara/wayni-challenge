# Query API Endpoints Specification

## Purpose

REST API on the Query service exposing debtor and entity lookups with validation, pagination, and JSON:API responses.

## Requirements

### REQ-API-001: Get Debtor by CUIT

The system MUST expose `GET /debtors/{cuit}` returning a single debtor.

#### Scenario: Valid CUIT found

- GIVEN a debtor exists with `identification_number` matching the CUIT
- WHEN `GET /debtors/{cuit}` is called with a valid 11-digit CUIT
- THEN the system MUST return `200` with a `DebtorResource`

#### Scenario: CUIT not found

- GIVEN no debtor matches the provided CUIT
- WHEN `GET /debtors/{cuit}` is called
- THEN the system MUST return `404`

#### Scenario: Invalid CUIT format

- GIVEN the CUIT is not exactly 11 numeric digits
- WHEN `GET /debtors/{cuit}` is called
- THEN the system MUST return `422` with validation error

### REQ-API-002: Top Debtors

The system MUST expose `GET /debtors/top/{n}` returning debtors ordered by `total_loan_amount` DESC.

#### Scenario: Valid top request

- GIVEN `n` is between 1 and 1000
- WHEN `GET /debtors/top/{n}` is called
- THEN the system MUST return `200` with an array of `DebtorResource` ordered by total loans descending
- AND include `meta.count` with the result count

#### Scenario: Invalid n value

- GIVEN `n` is 0, negative, or exceeds 1000
- WHEN `GET /debtors/top/{n}` is called
- THEN the system MUST return `422`

### REQ-API-003: List Debtors

The system MUST expose `GET /debtors` with optional situation filter and pagination.

#### Scenario: List all debtors

- GIVEN no filters are provided
- WHEN `GET /debtors` is called
- THEN the system MUST return paginated `DebtorResource` array with default `per_page=50`

#### Scenario: Filter by situation

- GIVEN `?situation=03` is provided and `03` is a valid situation code
- WHEN `GET /debtors` is called
- THEN the system MUST return only debtors matching that situation

#### Scenario: Invalid situation code

- GIVEN `?situation=99` is provided (not in valid codes)
- WHEN `GET /debtors` is called
- THEN the system MUST return `422`

#### Scenario: Pagination limits

- GIVEN `per_page` exceeds 200
- WHEN `GET /debtors` is called
- THEN the system MUST cap results at 200 per page

### REQ-API-004: Get Entity by Code

The system MUST expose `GET /entities/{code}` returning a single entity.

#### Scenario: Valid entity found

- GIVEN an entity exists matching the code (up to 5 characters)
- WHEN `GET /entities/{code}` is called
- THEN the system MUST return `200` with an `EntityResource`

#### Scenario: Entity not found

- GIVEN no entity matches the code
- WHEN `GET /entities/{code}` is called
- THEN the system MUST return `404`

#### Scenario: Invalid code format

- GIVEN the code exceeds 5 characters or is non-numeric
- WHEN `GET /entities/{code}` is called
- THEN the system MUST return `422`

### REQ-API-005: API Response Format

All endpoints MUST return JSON responses following the API contracts.

#### Scenario: Resource format

- GIVEN any successful endpoint response
- WHEN the response is serialized
- THEN it MUST wrap data in a `data` key using `DebtorResource` or `EntityResource`
