# File Ingestion Specification

## Purpose

Define the dual-mode file ingestion strategy supporting S3 pre-signed URL uploads (production) and local file path processing (development), with memory-efficient streaming for large BCRA files.

## Requirements

### REQ-ING-001: Dual-Mode File Input

The system MUST support two file input modes: S3 pre-signed URL (production) and local filesystem path (development), toggled via environment configuration.

#### Scenario: Production mode with S3

- GIVEN `FILE_INGESTION_MODE=s3` is set in environment
- WHEN a file upload is initiated via pre-signed URL
- THEN the system MUST download the file from S3 for processing

#### Scenario: Development mode with local path

- GIVEN `FILE_INGESTION_MODE=local` is set in environment
- WHEN a local file path is provided
- THEN the system MUST read the file directly from the filesystem

#### Scenario: Mode toggle via environment

- GIVEN the application configuration is inspected
- WHEN `FILE_INGESTION_MODE` is read
- THEN it MUST accept values `s3` or `local`
- AND default to `local` when not specified

### REQ-ING-002: Pre-Signed URL Generation

The system MUST generate S3 pre-signed URLs for secure file upload from the frontend.

#### Scenario: URL generation

- GIVEN a client requests a pre-signed upload URL
- WHEN the URL generation endpoint is called
- THEN the system MUST return a time-limited pre-signed PUT URL
- AND the URL MUST be valid for the configured S3 bucket

#### Scenario: URL expiration

- GIVEN a pre-signed URL is generated
- WHEN the URL is used after the configured TTL
- THEN the upload request MUST be rejected by S3

#### Scenario: LocalStack compatibility

- GIVEN `AWS_ENDPOINT_URL` is configured for LocalStack
- WHEN a pre-signed URL is generated
- THEN the URL MUST use the LocalStack endpoint
- AND uploads to the URL MUST succeed against LocalStack

### REQ-ING-003: Memory-Efficient Streaming

The system MUST process files using streaming to handle files up to 6GB without loading entire content into memory.

#### Scenario: Stream processing

- GIVEN a BCRA file is being processed
- WHEN the file reader is invoked
- THEN it MUST use a streaming approach (line-by-line or chunked)
- AND memory usage MUST NOT exceed 512MB regardless of file size

#### Scenario: Large file handling

- GIVEN a 6GB BCRA file is provided
- WHEN processing begins
- THEN the system MUST NOT attempt to load the entire file into memory
- AND processing MUST complete without out-of-memory errors

### REQ-ING-004: ECS Task Dispatch

The system MUST dispatch file processing as an ECS task for production workloads.

#### Scenario: Task dispatch on upload

- GIVEN a file is uploaded to S3 via pre-signed URL
- WHEN the upload completes
- THEN the system MUST trigger an ECS task for processing
- AND pass the S3 object key to the task

#### Scenario: Task resource allocation

- GIVEN an ECS processing task is launched
- WHEN the task definition is inspected
- THEN it MUST allocate sufficient memory (minimum 8GB per SAM template)

### REQ-ING-005: S3 Configuration

The system MUST configure S3 client with environment-based endpoint override for LocalStack compatibility.

#### Scenario: Endpoint configuration

- GIVEN `AWS_ENDPOINT_URL` is set in environment
- WHEN the S3 client is initialized
- THEN it MUST use the specified endpoint URL
- AND `AWS_DEFAULT_REGION` MUST be applied

#### Scenario: Bucket configuration

- GIVEN the application configuration is inspected
- WHEN S3 settings are read
- THEN `S3_BUCKET_NAME` MUST be a required configuration value

### REQ-ING-006: SQS Configuration

The system MUST configure SQS client for event publishing with environment-based endpoint override.

#### Scenario: Queue configuration

- GIVEN the application configuration is inspected
- WHEN SQS settings are read
- THEN `SQS_QUEUE_URL` MUST be a required configuration value
- AND `AWS_ENDPOINT_URL` MUST be used when set

#### Scenario: Event publishing

- GIVEN a domain event is dispatched
- WHEN the SQS client sends a message
- THEN it MUST target the configured queue URL

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
