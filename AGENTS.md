# Wayni BCRA Deudores Processor

## Project Overview

Sistema de procesamiento de padrón de deudores del BCRA (Banco Central de la República Argentina) con arquitectura de microservicios, event-driven y CQRS.

## Tech Stack

- **Backend:** PHP 8.5 · Laravel 13
- **Database:** PostgreSQL 18
- **Infrastructure:** Docker Compose · LocalStack 4.14 (SQS/S3)
- **Architecture:** Microservices · Event-Driven · CQRS · Clean Architecture · DDD

## Architecture

El sistema está compuesto por tres servicios independientes:

- **Importer Service** (Write Side): Procesa archivo TXT del BCRA, publica eventos a SQS
- **Query API** (Read Side): Consume eventos de SQS, mantiene read model, expone API de consulta
- **Query Worker**: Consume eventos de SQS y actualiza la base de datos del query service

Cada servicio tiene su propia base de datos PostgreSQL (database-per-service). La comunicación es asíncrona vía SQS (3 colas: `debtor-events`, `entity-events`, `import-completed`).

## Documentation Index

### Architecture
- [Overview](docs/architecture/overview.md) - Contexto del sistema y ADRs
- [Services](docs/architecture/services.md) - Descomposición de servicios
- [Data Model](docs/architecture/data-model.md) - Modelos de datos y esquemas
- [File Format](docs/architecture/file-format.md) - Formato del archivo BCRA
- [API Contracts](docs/architecture/api-contracts.md) - Contratos de API REST
- [Business Rules](docs/architecture/business-rules.md) - Reglas de negocio
- [Infrastructure](docs/architecture/infrastructure.md) - Infraestructura y Docker
- [Environment](docs/architecture/environment.md) - Variables de entorno

### Implementation
- [Checklist](docs/implementation/checklist.md) - Checklist de implementación por fases
- [Testing](docs/implementation/testing.md) - Estrategia de testing
- [Acceptance](docs/implementation/acceptance.md) - Criterios de aceptación
- [Quality](docs/implementation/quality.md) - Estándares de calidad de código
- [Bonus](docs/implementation/bonus.md) - Features bonus implementados

## Quick Start

```bash
# Clone repository
git clone <repo-url>
cd wayni-challenge

# Start all 6 services
docker compose up -d

# Run migrations and setup LocalStack
./init.sh

# Process file
docker compose exec importer php artisan bcra:process /path/to/deudores.txt
```

## API Endpoints

### Importer Service (puerto 8001)
- `POST /upload` - Subir archivo TXT para procesamiento

### Query API (puerto 8000)
- `GET /debtors/{cuit}` - Consultar deudor por CUIT
- `GET /entities/{code}` - Consultar entidad por código
- `GET /debtors/top/{n}` - Top N deudores por préstamos
- `GET /debtors?situation=X` - Listar deudores con filtros

## Development

### Run Tests
```bash
# Importer tests
docker-compose exec importer php artisan test

# Query API tests
docker-compose exec query php artisan test
```

### View Logs
```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f importer
docker-compose logs -f query-worker
```

## Key Conventions

- **Language:** All technical content (code, endpoints, DB fields, messages, comments) in English
- **Architecture:** Clean Architecture with DDD tactical patterns
- **Communication:** Event-driven via SQS
- **Database:** Database-per-service pattern
- **Idempotency:** All event handlers use upsert operations
- **Validation:** Value Objects encapsulate business rules

## Important Notes

- File format parser must follow exact positions from [File Format](docs/architecture/file-format.md)
- Situation codes are 2-character strings: '01', '03', '04', '05', '11', '21', '23'
- Amounts are in thousands of pesos with 1 decimal (format: "11,1" → parse to 11.1)
- File encoding is ISO-8859-1, must convert to UTF-8 during parsing
