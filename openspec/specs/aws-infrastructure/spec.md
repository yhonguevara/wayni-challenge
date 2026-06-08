# AWS Infrastructure Specification

## Purpose

Define the AWS SAM template for production deployment including ECS Fargate compute, S3 storage, SQS messaging, API Gateway, IAM roles, and CloudWatch observability.

## Requirements

### REQ-AWS-001: SAM Template Structure

The system MUST define infrastructure as code in `infrastructure/template.yaml` using AWS SAM specification.

#### Scenario: Template validation

- GIVEN `infrastructure/template.yaml` exists
- WHEN `sam validate --lint` is executed
- THEN validation passes with zero errors

#### Scenario: Template format version

- GIVEN the template is inspected
- WHEN the `AWSTemplateFormatVersion` field is read
- THEN it MUST be `2010-09-09`
- AND `Transform` MUST include `AWS::Serverless-2016-10-31`

### REQ-AWS-002: ECS Fargate Service Definition

The system MUST define ECS Fargate services for both importer and query applications with 4 vCPU and 8GB RAM.

#### Scenario: Importer service resources

- GIVEN the SAM template is inspected
- WHEN the importer ECS task definition is read
- THEN `Cpu` MUST be `4096` (4 vCPU)
- AND `Memory` MUST be `8192` (8GB)
- AND `RequiresCompatibilities` MUST include `FARGATE`

#### Scenario: Query service resources

- GIVEN the SAM template is inspected
- WHEN the query ECS task definition is read
- THEN `Cpu` MUST be `4096`
- AND `Memory` MUST be `8192`

#### Scenario: Network mode

- GIVEN ECS task definitions exist
- WHEN inspected
- THEN `NetworkMode` MUST be `awsvpc` for Fargate compatibility

### REQ-AWS-003: S3 Bucket for File Storage

The system MUST define an S3 bucket for BCRA file storage with versioning and encryption.

#### Scenario: Bucket definition

- GIVEN the SAM template is inspected
- WHEN the S3 bucket resource is read
- THEN it MUST define `Type: AWS::S3::Bucket`
- AND `BucketEncryption` MUST be configured with SSE-S3 or SSE-KMS

#### Scenario: Bucket versioning

- GIVEN the S3 bucket resource is inspected
- WHEN `VersioningConfiguration` is read
- THEN `Status` MUST be `Enabled`

### REQ-AWS-004: SQS Queue for Event Processing

The system MUST define SQS queues for domain event communication between services.

#### Scenario: Queue definition

- GIVEN the SAM template is inspected
- WHEN SQS queue resources are read
- THEN at least one `AWS::SQS::Queue` MUST be defined
- AND `VisibilityTimeout` MUST be configured

#### Scenario: Dead letter queue

- GIVEN the primary SQS queue is inspected
- WHEN `RedrivePolicy` is read
- THEN it MUST reference a dead-letter queue
- AND `maxReceiveCount` MUST be defined

### REQ-AWS-005: API Gateway for Query Service

The system MUST define an API Gateway HTTP API for the query service REST endpoints.

#### Scenario: API definition

- GIVEN the SAM template is inspected
- WHEN the API Gateway resource is read
- THEN `Type` MUST be `AWS::Serverless::HttpApi` or `AWS::ApiGatewayV2::Api`

#### Scenario: API integration

- GIVEN the API Gateway is defined
- WHEN route integrations are inspected
- THEN routes MUST integrate with the query ECS service

### REQ-AWS-006: IAM Roles and Policies

The system MUST define least-privilege IAM roles for ECS task execution and application access.

#### Scenario: Task execution role

- GIVEN the SAM template is inspected
- WHEN the ECS task execution role is read
- THEN it MUST grant `ecr:GetAuthorizationToken`, `ecr:BatchGetImage`, `logs:CreateLogStream`, `logs:PutLogEvents`

#### Scenario: Application role

- GIVEN the ECS task role is inspected
- WHEN policies are read
- THEN it MUST grant `s3:GetObject`, `s3:PutObject` for the defined bucket
- AND `sqs:SendMessage`, `sqs:ReceiveMessage`, `sqs:DeleteMessage` for defined queues

### REQ-AWS-007: CloudWatch Log Groups

The system MUST define CloudWatch log groups for ECS service observability.

#### Scenario: Log group definition

- GIVEN the SAM template is inspected
- WHEN log group resources are read
- THEN `AWS::Logs::LogGroup` MUST be defined for each ECS service
- AND `RetentionInDays` MUST be specified

### REQ-AWS-008: Parameterized Configuration

The template MUST use parameters for environment-specific values.

#### Scenario: Required parameters

- GIVEN the template `Parameters` section is inspected
- WHEN read
- THEN it MUST include `Environment` (dev/staging/prod), `VpcId`, `SubnetIds`
