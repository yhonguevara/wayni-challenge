# Project Documentation Specification

## Purpose

Define the requirements for the root-level `README.md` that serves as the primary entry point for developers and evaluators, covering project overview, quick start, API documentation, testing, and deployment.

## Requirements

### REQ-DOC-001: Quick Start Guide

The README MUST include a quick start section that gets the system running in 3 commands or fewer.

#### Scenario: Minimal quick start

- GIVEN a developer has Docker and Docker Compose installed
- WHEN they follow the quick start section
- THEN the system MUST be fully operational in at most 3 shell commands
- AND the commands MUST include `docker-compose up -d` and `init.sh` execution

#### Scenario: Quick start prerequisites stated

- GIVEN the README is inspected
- WHEN the prerequisites section is read
- THEN it MUST list Docker, Docker Compose, and the BCRA data file as requirements

### REQ-DOC-002: API Endpoints with Curl Examples

The README MUST document all API endpoints with working curl examples.

#### Scenario: Importer endpoints documented

- GIVEN the README API section is inspected
- WHEN importer endpoints are read
- THEN `POST /upload` MUST be documented with a curl example using multipart form data

#### Scenario: Query endpoints documented

- GIVEN the README API section is inspected
- WHEN query endpoints are read
- THEN all 4 endpoints MUST be documented: `GET /debtors/{cuit}`, `GET /entities/{code}`, `GET /debtors/top/{n}`, `GET /debtors?situation=X`
- AND each MUST include a curl example with expected response format

### REQ-DOC-003: Testing Instructions

The README MUST include instructions for running the test suite.

#### Scenario: Test execution documented

- GIVEN the README testing section is inspected
- WHEN read
- THEN it MUST include commands to run tests for both `importer` and `query` services
- AND commands MUST use `docker-compose exec` to run tests inside containers

#### Scenario: Test scope described

- GIVEN the testing section is read
- WHEN inspected
- THEN it MUST mention the approximate test count and test types (unit, feature, integration)

### REQ-DOC-004: Architecture Overview

The README MUST include a high-level architecture overview.

#### Scenario: Architecture diagram

- GIVEN the README is inspected
- WHEN the architecture section is read
- THEN it MUST include an ASCII or text-based architecture diagram showing: importer service, query service, SQS queues, PostgreSQL databases, and S3 storage

#### Scenario: Technology stack listed

- GIVEN the architecture section is read
- WHEN inspected
- THEN it MUST list: PHP 8.5, Laravel 13, PostgreSQL 18, Docker Compose, LocalStack 4.14, AWS SAM

### REQ-DOC-005: SAM Deployment Section

The README MUST include a section for AWS production deployment using SAM.

#### Scenario: Deployment commands documented

- GIVEN the README SAM section is inspected
- WHEN read
- THEN it MUST include `sam validate --lint` and `sam deploy --guided` commands
- AND it MUST document required parameters (`Environment`, `VpcId`, `SubnetIds`, ECR image URIs)

#### Scenario: Prerequisites for deployment

- GIVEN the SAM section is read
- WHEN prerequisites are inspected
- THEN it MUST list: AWS CLI, SAM CLI, AWS account with appropriate permissions, existing VPC with subnets
