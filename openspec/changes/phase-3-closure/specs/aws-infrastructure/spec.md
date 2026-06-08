# Delta for AWS Infrastructure

## MODIFIED Requirements

### REQ-AWS-008: Parameterized Configuration

The template MUST use parameters for environment-specific values including ECR image URIs.

(Previously: Parameters included `Environment`, `VpcId`, `SubnetIds` but not separate ECR URIs per service)

#### Scenario: Required parameters

- GIVEN the template `Parameters` section is inspected
- WHEN read
- THEN it MUST include `Environment` (dev/staging/prod), `VpcId`, `SubnetIds`
- AND it MUST include `ImporterImageUri` and `QueryImageUri` for ECR image references

#### Scenario: ECR parameter usage in task definitions

- GIVEN the ECS task definitions are inspected
- WHEN the `Image` property is read
- THEN `ImporterTaskDef` MUST reference `!Ref ImporterImageUri`
- AND `QueryTaskDef` MUST reference `!Ref QueryImageUri`

### REQ-AWS-009: ECR Image URI Parameterization

The SAM template MUST accept ECR image URIs as parameters rather than constructing them from a single repository URI.

#### Scenario: Separate image parameters

- GIVEN the template `Parameters` section is inspected
- WHEN ECR-related parameters are read
- THEN `ImporterImageUri` MUST be defined with type `String`
- AND `QueryImageUri` MUST be defined with type `String`
- AND both MUST include a `Description` field

#### Scenario: Image URI in container definitions

- GIVEN the container definitions are inspected
- WHEN the `Image` field is read
- THEN it MUST use the parameterized URI (not a hardcoded or `!Sub`-constructed value)

### REQ-AWS-010: API Gateway Integration with ECS Services

The SAM template MUST include an API Gateway HTTP API with route integrations that forward traffic to the query ECS service.

#### Scenario: HTTP API definition

- GIVEN the SAM template is inspected
- WHEN the `QueryHttpApi` resource is read
- THEN it MUST define `Type: AWS::Serverless::HttpApi`
- AND it MUST include `StageName` referencing the `Environment` parameter

#### Scenario: API output

- GIVEN the template `Outputs` section is inspected
- WHEN read
- THEN `QueryHttpApiUrl` MUST output `!GetAtt QueryHttpApi.ApiEndpoint`

### REQ-AWS-011: CloudWatch Log Groups for All Services

The SAM template MUST define CloudWatch log groups for every ECS service including the query-worker.

#### Scenario: Log group coverage

- GIVEN the SAM template is inspected
- WHEN `AWS::Logs::LogGroup` resources are enumerated
- THEN log groups MUST exist for `importer`, `query`, and `query-worker`
- AND each MUST define `RetentionInDays` (minimum 14 days)

#### Scenario: Query worker log group

- GIVEN the template is inspected
- WHEN the query-worker log group is read
- THEN `LogGroupName` MUST follow the pattern `/ecs/wayni-query-worker-${Environment}`
- AND it MUST be referenced in the query-worker task definition's `LogConfiguration`

### REQ-AWS-012: Deployment Instructions

The project MUST include deployment instructions for the SAM template accessible to developers.

#### Scenario: README deployment section

- GIVEN the project `README.md` is inspected
- WHEN the deployment section is read
- THEN it MUST include `sam validate --lint` command
- AND it MUST include `sam deploy --guided` command
- AND it MUST document required prerequisites (AWS CLI, SAM CLI, existing VPC)

#### Scenario: samconfig.toml configuration

- GIVEN `infrastructure/samconfig.toml` is inspected
- WHEN deploy parameters are read
- THEN it MUST include `stack_name`, `region`, `capabilities`, and `parameter_overrides`
