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

# Create separate test databases so each suite has isolated schema.
# Importer tests use wayni_importer_test, query tests use wayni_query_test.
echo "Ensuring test databases exist..."
php -r '
    $host = "shared-db";
    $dbs  = ["wayni_importer_test", "wayni_query_test"];
    $pdo  = new PDO("pgsql:host=$host;port=5432;dbname=postgres", "wayni", "secret");
    foreach ($dbs as $db) {
        $exists = $pdo->query("SELECT 1 FROM pg_database WHERE datname = " . $pdo->quote($db))->fetchColumn();
        if (!$exists) {
            $pdo->exec("CREATE DATABASE $db");
            echo "  $db: created\n";
        } else {
            echo "  $db: already exists\n";
        }
    }
'
echo "Test databases ready"

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
