# API Contracts

## `POST /upload`

**Request:**
```
Content-Type: multipart/form-data
Body: file (required) — TXT file
```

**Response 202 Accepted:**
```json
{
  "message": "File received. Processing started.",
  "import_log_id": 42
}
```

**Response 422 Unprocessable Entity:**
```json
{
  "message": "The file is required and must be of type text/plain.",
  "errors": { "file": ["..."] }
}
```

---

## `GET /debtors/{cuit}`

**Path param:** `cuit` — 11 numeric digits, without dashes

**Response 200:**
```json
{
  "data": {
    "identificationNumber": "20123456789",
    "maxSituation": "03",
    "totalLoanAmount": "1250.00"
  }
}
```

**Response 404:**
```json
{ "message": "Debtor not found." }
```

---

## `GET /entities/{code}`

**Path param:** `code` — up to 5 numeric digits

**Response 200:**
```json
{
  "data": {
    "entityCode": "00011",
    "totalLoanAmount": "98430.00"
  }
}
```

**Response 404:**
```json
{ "message": "Entity not found." }
```

---

## `GET /debtors/top/{n}`

**Path param:** `n` — positive integer, maximum 1000

**Response 200:**
```json
{
  "data": [
    {
      "identificationNumber": "20999999993",
      "maxSituation": "01",
      "totalLoanAmount": "99999.00"
    }
  ],
  "meta": { "count": 10 }
}
```

**Response 422:**
```json
{ "message": "Parameter n must be an integer between 1 and 1000." }
```

---

## `GET /debtors` (with optional filters)

**Query params:**
- `situation` (optional) — string: '01', '03', '04', '05', '11', '21', '23'
- `per_page` (optional) — default 50, maximum 200
- `page` (optional) — default 1

**Response 200:**
```json
{
  "data": [...],
  "meta": { "current_page": 1, "per_page": 50, "total": 1240 }
}
```

---

## Pre-Signed URL Upload Flow

### `POST /api/presign`

**Request:**
```json
{ "filename": "deudores.txt" }
```

**Response 200:**
```json
{
  "upload_url": "http://localhost:4566/bcra-files",
  "fields": {
    "key": "uploads/deudores.txt"
  }
}
```

### `POST /api/notify-upload`

**Request:**
```json
{ "key": "uploads/deudores.txt", "size": 12345 }
```

**Response 200:**
```json
{
  "message": "File queued for processing",
  "import_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

---

*Last updated: Phase 0 SDD*
