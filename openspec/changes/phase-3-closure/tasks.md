# Tasks: Phase 3 — Closure & Production Readiness

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~540 |
| 400-line budget risk | Medium |
| Chained PRs recommended | No |
| Suggested split | Single PR (user pre-authorized 800-line budget) |
| Delivery strategy | single-pr |
| Chain strategy | size-exception |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: size-exception
400-line budget risk: Medium

## Phase 1: Docker Compose Environment

- [x] 1.1 Add `query-worker` service to `docker-compose.yml` — same image as `query`, `command: ["php", "artisan", "queue:work", "sqs", "--sleep=3", "--tries=3", "--timeout=90"]`, no host ports, depends_on `query-db` + `localstack` (healthy)
- [x] 1.2 Add `healthcheck` blocks to `importer` and `query` services using `curl -f http://localhost:8000/up` (existing Laravel route)

## Phase 2: Init Script

- [x] 2.1 Create `init.sh` — wait for `importer-db` + `query-db` (pg_isready, 60s timeout), wait for `localstack` (curl health), exec `php artisan migrate --force` in both containers, exec `php artisan localstack:setup`, exit 0 on success / non-zero on failure
- [x] 2.2 Run `chmod +x init.sh` and verify shebang is `#!/usr/bin/env bash`

## Phase 3: SAM Template Refinement

- [x] 3.1 Replace `EcrRepositoryUri` param with separate `ImporterImageUri` + `QueryImageUri` params in `infrastructure/template.yaml`; update container `Image: !Ref ImporterImageUri` / `!Ref QueryImageUri`
- [x] 3.2 Add `ImportCompletedQueue` + `ImportCompletedDeadLetterQueue` SQS resources to `template.yaml`
- [x] 3.3 Add `QueryWorkerLogGroup` CloudWatch log group with 30-day retention
- [x] 3.4 Add 3 `AWS::ECS::Service` resources (ImporterService, QueryService, QueryWorkerService) referencing existing TaskDefs; add ALB + TargetGroup + Listener for importer
- [x] 3.5 Add SQS permissions for `ImportCompletedQueue` to `EcsTaskRole`
- [x] 3.6 Expand `Outputs` — add `ImporterServiceUrl`, `QueryWorkerServiceName`, `ImportCompletedQueueUrl`
- [x] 3.7 Update `infrastructure/samconfig.toml` — add `ImporterImageUri`, `QueryImageUri` to `parameter_overrides`, populate `image_repositories`
- [x] 3.8 Run `sam validate --lint` on `infrastructure/template.yaml`

## Phase 4: Documentation

- [x] 4.1 Create `README.md` — architecture diagram (ASCII), tech stack, quick start (≤3 commands), API endpoints with curl examples, file processing (upload + artisan), testing commands, SAM deployment guide with prerequisites, troubleshooting

## Phase 5: AGENTS.md Cleanup

- [x] 5.1 Update `AGENTS.md` — replace manual migrate steps with `init.sh`, update API endpoint paths to English (`debtors`/`entities`), add `query-worker` reference

## Phase 6: End-to-End Verification

- [x] 6.1 Run `docker-compose up -d` and verify all 6 services reach healthy/running state
- [x] 6.2 Run `bash init.sh` twice and verify idempotency (exit 0 both times)
- [ ] 6.3 Upload `deudores_bcra.txt` via `POST /upload` — verify processing and query API returns data within 30s
- [ ] 6.4 Run `php artisan bcra:process /app/storage/deudores_bcra.txt` inside importer container — verify exit 0 + summary output
- [ ] 6.5 Verify all 4 query endpoints return correct data from real file

### Verification Notes

**6.1 - PASS**: All 6 services start and reach healthy/running state:
- importer-db (healthy), query-db (healthy), localstack (healthy)
- importer (healthy), query (healthy), query-worker (running)

**6.2 - PASS**: `init.sh` runs successfully twice (idempotent):
- Migrations complete for both databases
- LocalStack S3 bucket and SQS queues created
- Second run: bucket already exists (skipped), queues recreated (idempotent)

**6.3/6.4/6.5 - BLOCKED (pre-existing code bugs)**:
- `POST /api/upload` works but rejects .txt files with CSV mime type (BCRA file is CSV-formatted)
- `php artisan bcra:process` fails with `ArgumentCountError` — `ProcessBcraFile::dispatch()` passes 2 args but constructor expects 4
- Query endpoints return 404 (no data processed due to above bugs)
- These are application code bugs, NOT infrastructure issues. Phase 3 infrastructure changes are correct.
