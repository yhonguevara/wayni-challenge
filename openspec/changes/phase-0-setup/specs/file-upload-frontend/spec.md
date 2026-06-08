# File Upload Frontend Specification

## Purpose

Define the browser-based file upload interface for BCRA files, supporting pre-signed S3 URL uploads with progress tracking, compatible with both LocalStack (development) and AWS S3 (production).

## Requirements

### REQ-FE-001: File Picker Interface

The system MUST provide a browser-based UI with a file picker for selecting BCRA text files.

#### Scenario: File picker renders

- GIVEN the upload page is loaded in a browser
- WHEN the page renders
- THEN a file input element MUST be visible
- AND it MUST accept `.txt` files

#### Scenario: File selection

- GIVEN the file picker is rendered
- WHEN a user selects a BCRA `.txt` file
- THEN the selected filename MUST be displayed
- AND an upload button MUST become enabled

### REQ-FE-002: Upload Button and Trigger

The system MUST provide an upload button that initiates the pre-signed URL flow.

#### Scenario: Upload initiation

- GIVEN a file is selected
- WHEN the upload button is clicked
- THEN the system MUST request a pre-signed URL from the backend
- AND begin uploading the file to the returned URL

#### Scenario: Button disabled state

- GIVEN no file is selected
- WHEN the upload page is rendered
- THEN the upload button MUST be disabled

### REQ-FE-003: Pre-Signed URL Upload Flow

The system MUST upload files directly to S3 using pre-signed URLs obtained from the backend.

#### Scenario: URL request and upload

- GIVEN a file is selected and upload is triggered
- WHEN the frontend calls the pre-signed URL endpoint
- THEN it MUST receive a PUT URL in the response
- AND upload the file content to that URL via HTTP PUT

#### Scenario: LocalStack upload

- GIVEN the backend is configured with LocalStack
- WHEN the pre-signed URL is generated
- THEN the URL MUST point to the LocalStack endpoint
- AND the browser upload MUST succeed against LocalStack

#### Scenario: Production upload

- GIVEN the backend is configured with AWS S3
- WHEN the pre-signed URL is generated
- THEN the URL MUST point to the AWS S3 endpoint
- AND the browser upload MUST succeed against S3

### REQ-FE-004: Upload Progress Display

The system MUST display upload progress to the user during file transfer.

#### Scenario: Progress indicator

- GIVEN an upload is in progress
- WHEN data is being transferred
- THEN a progress bar or percentage indicator MUST be visible
- AND it MUST update as the upload progresses

#### Scenario: Progress completion

- GIVEN an upload reaches 100%
- WHEN the transfer completes
- THEN the progress indicator MUST show completion
- AND a success message MUST be displayed

### REQ-FE-005: Upload Status Display

The system MUST display the processing status after upload completion.

#### Scenario: Status after upload

- GIVEN a file upload completes successfully
- WHEN the backend confirms receipt
- THEN the UI MUST display a "Processing" status
- AND provide a mechanism to check processing progress

#### Scenario: Upload error handling

- GIVEN an upload fails (network error, expired URL, etc.)
- WHEN the error occurs
- THEN the UI MUST display an error message
- AND provide a retry option

### REQ-FE-006: Static File Serving

The upload frontend MUST be served as static HTML+JS files by the importer-service or a dedicated nginx container.

#### Scenario: Frontend accessibility

- GIVEN the importer-service is running
- WHEN a browser navigates to the upload page URL
- THEN the HTML page MUST load
- AND all JavaScript assets MUST be accessible

#### Scenario: No build step required

- GIVEN the frontend source files exist
- WHEN inspected
- THEN files MUST be plain HTML + vanilla JavaScript
- AND no Node.js build step or bundler MUST be required

### REQ-FE-007: CORS Configuration

The S3 bucket MUST be configured with CORS headers to allow browser-based uploads from the frontend origin.

#### Scenario: CORS headers present

- GIVEN a pre-signed URL is used for upload from the browser
- WHEN the browser sends the PUT request
- THEN S3 MUST respond with appropriate CORS headers
- AND the upload MUST not be blocked by browser CORS policy

#### Scenario: LocalStack CORS

- GIVEN LocalStack is configured
- WHEN a browser upload is attempted
- THEN LocalStack MUST return permissive CORS headers for development
