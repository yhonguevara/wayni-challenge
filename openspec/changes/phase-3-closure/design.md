# Design: Phase 3 — Closure & Production Readiness

## Technical Approach

Phase 3 is a **verification and closure phase** — no new business logic. The focus is making the existing system runnable end-to-end with a single command, documenting it for challenge reviewers, and refining the SAM template for production credibility. All changes are additive or corrective; no architectural shifts.

The approach follows the proposal's verification-first strategy: fix the missing `query-worker`, add `init.sh` for one-command setup, harden `docker-compose.yml`, refine the SAM template, and write a README that reflects verified reality.

## Architecture Decisions

| Decision | Options | Tradeoff | Choice |
|----------|---------|----------|--------|
| query-worker: same image vs separate | Same image + `command:` override vs separate Dockerfile | Same image = zero build overhead, single source of truth for deps | **Same image, `command:` override** |
| init.sh: shell script vs init container | Bash script at root vs Docker init service | Shell script is simpler, works outside Docker too, reviewers can read it | **Shell script at project root** |
| init service in compose | Separate `init` service with `profiles: [init]` vs manual execution | Profiles let `docker compose --profile init up` run it once; manual is simpler | **Both**: script for manual, optional `init` service with `profiles` |
| Healthcheck for Laravel | `curl /up` (built-in) vs custom `/health` endpoint | `/up` already configured in `bootstrap/app.php`, zero code change | **Use existing `/up` endpoint** |
| SAM: ALB vs API Gateway for ECS | ALB + ECS Service vs API Gateway HTTP API + VPC Link | ALB is standard for ECS Fargate; API Gateway HTTP API already in template but no integration | **Keep API Gateway HTTP API** (already defined), add ECS Service + ALB for importer |
| SAM: ECS Services | Add `AWS::ECS::Service` for importer, query-api, query-worker | Template has TaskDefs but no Services — incomplete without them | **Add 3 ECS Services** (importer, query-api, query-worker) |
| README format | Minimal quick-start vs comprehensive guide | Challenge reviewers need to run it fast but also understand architecture | **Comprehensive with Quick Start first** |

## Data Flow

End-to-end flow after Phase 3 (no changes to existing flow, just making it runnable):

```
deudores_bcra.txt
       │
       ▼
  ┌─────────────┐    SQS         ┌──────────────┐
  │  Importer    │───────────────▶│ query-worker  │
  │  (port 8001) │  3 queues      │ (queue:work)  │
  └──────┬──────┘                └──────┬────────┘
         │                              │
    importer-db                   query-db (upsert)
                                        │
                                        ▼
                                 ┌──────────────┐
                                 │  Query API    │
                                 │  (port 8000)  │
                                 └──────────────┘
```

`init.sh` orchestrates the bootstrap sequence:

```
init.sh
  ├─ wait for importer-db (pg_isready)
  ├─ wait for query-db (pg_isready)
  ├─ wait for localstack (curl healthcheck)
  ├─ docker compose exec importer php artisan migrate --force
  ├─ docker compose exec query php artisan migrate --force
  └─ docker compose exec importer php artisan localstack:setup
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `docker-compose.yml` | Modify | Add `query-worker` service, healthchecks for app services, optional `init` service with profile |
| `init.sh` | Create | Idempotent bootstrap script — waits for deps, runs migrations, sets up LocalStack |
| `README.md` | Create | Comprehensive project documentation |
| `infrastructure/template.yaml` | Modify | Add ECS Services, ALB, CloudWatch alarms, parameterize env vars, expand Outputs |
| `infrastructure/samconfig.toml` | Modify | Add parameter overrides for ECR URIs, VPC, subnets |

## Interfaces / Contracts

### query-worker service (docker-compose.yml)

```yaml
query-worker:
  build:
    context: ./services/query
    dockerfile: Dockerfile
  command: ["php", "artisan", "queue:work", "sqs", "--queue=debtor-events,entity-events,import-completed", "--tries=3", "--timeout=90", "--sleep=3"]
  env_file:
    - ./services/query/.env
  depends_on:
    query-db:
      condition: service_healthy
    localstack:
      condition: service_healthy
  restart: unless-stopped
```

### init.sh contract

- **Input**: None (reads `docker-compose.yml` from same directory)
- **Output**: Exit 0 on success, exit 1 on failure with error message
- **Idempotency**: `migrate --force` is idempotent (Laravel tracks migrations). `localstack:setup` checks existence before creating.
- **Timeout**: Each wait loop has a 60-second timeout with 2-second polling interval.

### SAM template additions

```yaml
# New Parameters
ImporterEcrImageUri:
  Type: String
  Description: Full ECR image URI for importer service

QueryEcrImageUri:
  Type: String
  Description: Full ECR image URI for query service

# New Resources: 3x AWS::ECS::Service, 1x ALB, 1x TargetGroup, 1x Listener
# New Outputs: ImporterServiceUrl, QueryServiceUrl, WorkerServiceName
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Manual E2E | `docker compose up` starts all 6 services | Run `init.sh`, verify all containers healthy |
| Manual E2E | Process real `deudores_bcra.txt` end-to-end | Copy file into container, run `bcra:process`, verify via query API |
| Manual E2E | All 4 query endpoints return correct data | `curl` each endpoint, verify response structure |
| SAM Validation | `sam validate --lint` passes | Run locally with SAM CLI |
| Script | `init.sh` is idempotent | Run twice, verify no errors on second run |

## Migration / Rollout

No data migration. All changes are infrastructure and documentation only.

Rollback: revert individual commits. No schema changes, no code changes to business logic.

## Open Questions

- [ ] Should the `init` service in docker-compose use `profiles: [init]` (opt-in) or run by default? Recommendation: `profiles: [init]` so `docker compose up` stays clean.
- [ ] SAM template: should we add `import-completed` queue to the template? It exists in `localstack:setup` but not in `template.yaml`. Recommendation: yes, add it for completeness.
- [ ] README language: English only (matching code convention) or bilingual? Recommendation: English only, consistent with AGENTS.md convention.
