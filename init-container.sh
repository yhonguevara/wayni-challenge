#!/bin/bash
set -e

echo "Initializing BCRA Challenge environment..."

# Dependencies are already healthy (enforced by depends_on with healthchecks)
echo "All dependencies are ready (shared-db + LocalStack)"

# Run migrations — importer owns ALL tables (debtors, entities, import_logs, processed_events)
echo "Running importer migrations..."
cd /importer
php artisan migrate --force
echo "Importer migrations complete"

# Create a single shared test database, mirroring the unified production DB.
# The importer owns ALL schema, so it migrates wayni_test too. The query
# service has no migrations: its tests run read-only against this schema and
# wrap each test in a transaction (DatabaseTransactions) that rolls back.
echo "Ensuring test database exists..."
php -r '
    $host = "shared-db";
    $db   = "wayni_test";
    $pdo  = new PDO("pgsql:host=$host;port=5432;dbname=postgres", "wayni", "secret");
    $exists = $pdo->query("SELECT 1 FROM pg_database WHERE datname = " . $pdo->quote($db))->fetchColumn();
    if (!$exists) {
        $pdo->exec("CREATE DATABASE $db");
        echo "  $db: created\n";
    } else {
        echo "  $db: already exists\n";
    }
'

echo "Migrating test database schema (importer-owned)..."
cd /importer
DB_DATABASE=wayni_test php artisan migrate:fresh --force
echo "Test database ready"

# Setup LocalStack
echo "Setting up LocalStack (S3 bucket and SQS queues)..."
cd /importer
php artisan localstack:setup
echo "LocalStack setup complete"

echo ""
echo "Initialization complete! Services are ready to use."
echo ""
echo "Importer API: http://localhost:8001"
echo "Query API:    http://localhost:8000"
echo "Upload UI:    http://localhost:8001/upload"
