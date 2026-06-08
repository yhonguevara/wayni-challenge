# Delta for File Ingestion

## ADDED Requirements

### REQ-ING-007: Direct File Upload Endpoint

The system MUST expose a `POST /upload` endpoint that accepts a BCRA TXT file via multipart/form-data, validates it, persists to storage, and dispatches async processing.

#### Scenario: Valid file accepted

- GIVEN a `.txt` file with `text/plain` MIME type under 6GB is uploaded
- WHEN `POST /upload` receives the file
- THEN the system MUST persist the file to configured storage (local or S3)
- AND create an `ImportLog` record with status `pending`
- AND dispatch a `ProcessBcraFile` job
- AND return `202 Accepted` with `import_log_id`

#### Scenario: File validation — wrong extension

- GIVEN a file with extension other than `.txt` is uploaded
- WHEN `POST /upload` receives the file
- THEN the system MUST return `422 Unprocessable Entity`

#### Scenario: File validation — wrong MIME type

- GIVEN a file with MIME type other than `text/plain` is uploaded
- WHEN `POST /upload` receives the file
- THEN the system MUST return `422 Unprocessable Entity`

#### Scenario: File validation — exceeds size limit

- GIVEN a file larger than 6GB is uploaded
- WHEN `POST /upload` receives the file
- THEN the system MUST return `422 Unprocessable Entity`

#### Scenario: File required

- GIVEN no file is included in the request
- WHEN `POST /upload` is called
- THEN the system MUST return `422 Unprocessable Entity`
