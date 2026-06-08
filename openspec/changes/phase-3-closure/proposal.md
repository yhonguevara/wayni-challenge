# Proposal: Phase 3 — Closure & Production Readiness

## Intent

Phase 0–2 delivered functional microservices (135 tests, Clean Architecture, SQS events, REST API). Missing: end-to-end verification, production-ready SAM template, startup script, complete README. Phase 3 closes these gaps for challenge submission.

## Scope

### In Scope
- **SAM template refinement**: review `template.yaml` + `samconfig.toml`, add ECR/ALB params, validate deployability, document `sam deploy`
- **Docker e2e verification**: start all services, verify POST /upload → SQS → query API, test `bcra:process` artisan
- **Add `query-worker`** to `docker-compose.yml` (documented but missing)
- **init.sh**: idempotent startup — migrations (both DBs), `localstack:setup`, queue worker
- **README.md**: overview, quick start, curl examples, testing, SAM guide, troubleshooting
- **Real file**: process `deudores_bcra.txt` end-to-end, verify endpoints return correct data

### Out of Scope
- AWS deployment, CI/CD, performance tuning, monitoring, auth

## Capabilities

### New Capabilities
None — Phase 3 validates and documents existing capabilities.

### Modified Capabilities
- `aws-infrastructure`: SAM refinement — ECR param, validate deployability, deployment guide
- `development-environment`: add `query-worker`, `init.sh`, CMD override for serve vs queue:work

## Approach

**Verification-first**: run `docker-compose up`, fix issues as they surface. Add query-worker + init.sh. Test with real file. Write README last reflecting verified reality. SAM: review against best practices, flag VpcId/SubnetIds require existing VPC.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `docker-compose.yml` | Modified | Add `query-worker` |
| `infrastructure/template.yaml` | Modified | Refine for deployability |
| `infrastructure/samconfig.toml` | Modified | Validate params |
| `services/*/Dockerfile` | Modified | CMD override |
| `init.sh` | New | Startup script |
| `README.md` | New | Root-level README |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| docker-compose fails on first run | Med | Iterative fixes; atomic commits |
| query-worker missing blocks events | High | First deliverable |
| SAM refs non-existent resources | Med | ECR as parameter; document prereqs |
| Real file fails unexpectedly | Low | Tested with 10K-line fixtures |

## Rollback Plan

All changes additive. Revert commits individually. No schema changes.

## Dependencies

- Phases 0–2 complete and archived
- Docker + Docker Compose, `deudores_bcra.txt` in root

## Success Criteria

- [ ] `docker-compose up` starts 6 services without errors
- [ ] `init.sh` runs migrations + localstack setup idempotently
- [ ] `deudores_bcra.txt` processes fully via upload or artisan
- [ ] All 4 query endpoints return correct data from real file
- [ ] README covers overview, quick start, API docs, SAM guide, troubleshooting
- [ ] `sam validate --lint` passes on template.yaml
