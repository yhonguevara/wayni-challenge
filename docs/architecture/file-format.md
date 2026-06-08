# BCRA File Format

> **✅ CORRECTION APPLIED:** All positions and types are aligned with `leame-deudores.md` (§180-192).

## General Characteristics

- **Plain text** file with **fixed-position** records
- Encoding: **ISO-8859-1** (Latin-1) → convert to UTF-8 when processing
- One record per line
- Total record length: **171 characters** (+ line terminator)
- **No header line** — all lines are detail records

---

## Detail Record Fields

| # | Name | Type | Length | Start Pos | End Pos | Notes |
|:---:|---|:---:|:---:|:---:|:---:|---|
| 1 | `codigo_entidad` | Numeric | 5 | 1 | 5 | Entity code |
| 2 | `periodo` | Numeric | 6 | 6 | 11 | YYYYMM |
| 3 | `tipo_identificacion` | Numeric | 2 | 12 | 13 | **`11` = CUIT/CUIL/CDI** (NOT `80`) |
| 4 | `nro_identificacion` | **Character** | 11 | 14 | 24 | CUIT/CUIL (may have leading spaces/zeros) |
| 5 | `actividad` | Numeric | 3 | 25 | 27 | Activity code |
| 6 | `situacion` | Numeric | **2** | **28** | **29** | **See situation codes table below** |
| 7 | `monto_prestamos` | Numeric | **12** | **30** | **41** | **Eleven integers and one decimal** (in thousands of pesos) |
| ... | other fields | ... | ... | ... | ... | Not required by the challenge |

---

## Situation Codes (Field 6)

| Code | Situation |
|:---:|---|
| `01` | Normal Situation |
| `21` | Low Risk |
| `23` | Special Treatment (from April 2020 information) |
| `03` | Medium Risk |
| `04` | High Risk |
| `05` | Irrecoverable |
| `11` | Fully covered with "A" preferred guarantees |

---

## Parsing Example

Example line:
```
0000720231111200039055280001 1,0         ,0          ,0          ,0          ,0          ,0          1,0         ,0          ,0          ,0          0           0000000
```

Parsing:
- `codigo_entidad` (pos 1-5): `00007`
- `periodo` (pos 6-11): `202311`
- `tipo_identificacion` (pos 12-13): `11` ← CUIT/CUIL
- `nro_identificacion` (pos 14-24): `20003905528` (trim spaces)
- `actividad` (pos 25-27): `000`
- `situacion` (pos 28-29): `01` ← Normal Situation
- `monto_prestamos` (pos 30-41): `1,0         ` → `1.0` (replace comma with period, trim)

---

## Important Notes

- Field 3 (tipo_identificacion) must be `11`, NOT `80` as some older docs suggest
- Field 6 (situacion) is 2 characters, NOT 1
- Field 4 (nro_identificacion) is Character type, may contain spaces or leading zeros
- Field 7 (monto_prestamos) is 12 characters (11 integers + 1 decimal), NOT 11
- Situation codes are 2-character strings: '01', '03', '04', '05', '11', '21', '23'
- Amounts are in thousands of pesos with 1 decimal place
