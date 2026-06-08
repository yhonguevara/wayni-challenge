# Naming Convention — English Only

## Purpose

All technical artifacts in this project MUST use English naming. This ensures consistency across code, database, API contracts, events, and documentation.

## Key Mappings

| Spanish (BCRA Source) | English (Code/DB/API) | Context |
|----------------------|----------------------|---------|
| Deudor | Debtor | Entity, table, variable |
| Entidad | Entity | Entity, table, variable |
| Situación | Situation | Field, variable |
| Préstamo | Loan / Loans | Field, variable |
| Monto | Amount | Field, variable |
| Código | Code | Field, variable |
| Número de identificación | Identification Number | Field |
| CUIT / CUIL | CUIT (kept as-is) | Business identifier |
| Fecha | Date | Field |
| Suma total | Total | Field prefix |

## Database Tables

| Table | Description |
|-------|-------------|
| `debtors` | Debtor records from BCRA padron |
| `entities` | Financial entities from BCRA padron |

## Column Naming

Use `snake_case` for all database columns:

- `identification_number` (not `nro_identificacion`)
- `max_situation` (not `situacion_maxima`)
- `total_loan_amount` (not `suma_total_prestamos`)
- `entity_code` (not `codigo_entidad`)
- `created_at`, `updated_at` (Laravel defaults)

## API Endpoints

| Endpoint | Description |
|----------|-------------|
| `GET /debtors/{cuit}` | Query debtor by CUIT |
| `GET /entities/{code}` | Query entity by code |
| `GET /debtors/top/{n}` | Top N debtors by loan sum |
| `GET /debtors?situation=X` | List debtors with filters |

## Domain Events

| Event Class | Description |
|-------------|-------------|
| `DebtorProcessed` | Published when a debtor is processed |
| `EntityProcessed` | Published when an entity is processed |
| `ImportCompleted` | Published when file processing completes |

## PHP Variables

```php
$debtor        // not $deudor
$entity        // not $entidad
$maxSituation  // not $situacionMaxima
$totalLoans    // not $prestamos
$amount        // not $monto
```

## Enforcement Rules

1. **Code Reviews**: Any Spanish identifier in code, database, or API is a blocking review comment
2. **Automated Check**: `grep -r "deudor\|entidad\|situacion\|prestamo\|monto" --include="*.php"` must return zero hits
3. **Database Migrations**: All table and column names must be in English
4. **API Responses**: All JSON keys must use camelCase English (`identificationNumber`, `maxSituation`)
5. **Domain Events**: All event class names must be PascalCase English

## Exceptions

- **CUIT/CUIL**: Retained as-is (official Argentine tax identifiers)
- **BCRA**: Retained as-is (Banco Central de la República Argentina)
- **Source File Format**: The input file format from BCRA uses Spanish field names — parser must translate during ingestion

---

*Last updated: Phase 0 SDD*
