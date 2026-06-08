# Acceptance Criteria

The system is considered **complete** when:

- [ ] `docker-compose up` starts all services without manual intervention
- [ ] `POST /upload` with the `deudores.txt` file returns 202 and processes the file
- [ ] Events are published to SQS correctly
- [ ] The query-worker consumes events and updates the query DB
- [ ] Data persists correctly in PostgreSQL (verify with `psql`)
- [ ] `GET /deudores/{cuit}` returns correct data for a known CUIT from the file
- [ ] `GET /entidades/{codigo}` returns correct data for a known code
- [ ] `GET /deudores/top/10` returns the 10 debtors with highest loan in correct order
- [ ] Upon process completion, a notification is emitted (at least structured log in stdout)
- [ ] `php artisan test` runs without errors in both services
- [ ] No hardcoded credentials in the code (everything via `.env`)
- [ ] The `README.md` has sufficient instructions to start the project from scratch
