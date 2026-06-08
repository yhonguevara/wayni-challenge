# Environment Variables

## Root `.env.example`

```dotenv
# Importer DB
IMPORTER_DB_CONNECTION=pgsql
IMPORTER_DB_HOST=importer-db
IMPORTER_DB_PORT=5432
IMPORTER_DB_DATABASE=importer_db
IMPORTER_DB_USERNAME=postgres
IMPORTER_DB_PASSWORD=secret

# Query DB
QUERY_DB_CONNECTION=pgsql
QUERY_DB_HOST=query-db
QUERY_DB_PORT=5432
QUERY_DB_DATABASE=query_db
QUERY_DB_USERNAME=postgres
QUERY_DB_PASSWORD=secret

# AWS / LocalStack
AWS_DEFAULT_REGION=us-east-1
AWS_ACCESS_KEY_ID=test
AWS_SECRET_ACCESS_KEY=test
AWS_ENDPOINT=http://localstack:4566

# S3
S3_BUCKET=bcra-files
ENABLE_S3_UPLOAD=true

# SQS
SQS_DEUDOR_QUEUE_URL=http://localstack:4566/000000000000/deudor-events
SQS_ENTITY_QUEUE_URL=http://localstack:4566/000000000000/entity-events
SQS_NOTIFICATION_QUEUE_URL=http://localstack:4566/000000000000/import-notifications

# Notification
NOTIFICATION_DRIVER=log         # options: log | webhook | sqs
NOTIFICATION_WEBHOOK_URL=       # webhook URL if driver=webhook
```
