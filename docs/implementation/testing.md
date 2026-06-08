# Testing Strategy

## Unit Tests (High priority per evaluation criteria)

| Class under test | What to test |
|---|---|
| `BcraFileParser` | Correct field parsing; filter by tipo_id='11'; filter by invalid situation; amount parsing |
| `BcraDataTransformer` | situacion_maxima grouping (alphabetical MAX); total_sum grouping; multiple entities |
| `Situacion` (Value Object) | Valid code validation; comparison for MAX |
| `Monto` (Value Object) | BCRA format parsing (comma → period); conversion to float |
| `UpsertDeudorHandler` | Idempotency: same data twice = same state; situation update |
| `LogNotification` | Writes structured JSON with correct metrics |

---

## Feature Tests (Query API)

| Endpoint | Cases |
|---|---|
| `GET /deudores/{cuit}` | 200 with existing debtor; 404 if not found; 422 if invalid CUIT |
| `GET /entidades/{codigo}` | 200 with existing entity; 404 if not found |
| `GET /deudores/top/{n}` | Correct order by sum; respects n limit; 422 if n=0 |
| `GET /deudores?situacion=03` | Filters correctly; pagination works |

---

## Integration Tests

| Scenario | Description |
|---|---|
| End-to-end upload | POST /upload → processes file → events in SQS → query DB updated |
| Idempotency | Process same file twice → same final state |
| Error handling | File with invalid lines → error log, process continues |

---

## Fixtures

Create file `tests/Fixtures/sample_bcra.txt` with at least:
- 5 records of the same CUIT with different situations and amounts
- 3 records of different entities
- 1 record with tipo_identificacion ≠ '11' (should be ignored)
- 1 record with invalid situation (should be ignored)
- 1 record with amount in BCRA format (decimal comma)
