# Business Rules

## RN-01: Debtor Aggregation

The same CUIT/CUIL may appear in **multiple records** in the file (different entities).
The `Debtor` entity is built as follows:

```
nro_identificacion  = Field 4 (trim spaces, same for all records with the same CUIT)
situacion_maxima    = MAX(Field 6) over all records with the same Field 4
                      (alphabetical comparison: '23' > '21' > '11' > '05' > '04' > '03' > '01')
suma_total_prestamos = SUM(Field 7) over all records with the same Field 4
```

**Note on MAX(situation):** Situation codes are '01', '03', '04', '05', '11', '21', '23'. Alphabetical order coincides with severity order (from lowest to highest risk).

---

## RN-02: Entity Aggregation

The same entity may have multiple records in the file.

```
codigo_entidad       = Field 1
suma_total_prestamos = SUM(Field 7) over all records with the same Field 1
```

---

## RN-03: Idempotent Upsert

If the file is processed more than once (by error or re-processing):
- Existing records must be **updated**, not duplicated.
- Use `INSERT ... ON CONFLICT (nro_identificacion) DO UPDATE` / Eloquent's `upsert()`.

---

## RN-04: Identification Type Filter

Only process records where **Field 3 = '11'** (CUIT/CUIL/CDI). Ignore records with other identification types.

---

## RN-05: Situation Validation

Ignore records where Field 6 is empty or not in the list of valid codes.
Valid values: `'01'`, `'03'`, `'04'`, `'05'`, `'11'`, `'21'`, `'23'`.

---

## RN-06: File Encoding

Convert each line from ISO-8859-1 to UTF-8 using `mb_convert_encoding()` before parsing.

---

## RN-07: Amount Parsing

The `monto_prestamos` field (Field 7) has format `11,1` (eleven integers, comma, one decimal).
- Replace comma with period: `1,0` → `1.0`
- Convert to float: `1.0` → `1.0`
- Store as `NUMERIC(18, 2)` in the database

**Note:** Amounts are in **thousands of pesos**. Define in `.env` whether to store as-is or convert to pesos. Recommended: store as thousands (original unit) and document it.
