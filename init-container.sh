#!/bin/bash
set -e

echo "🚀 Initializing BCRA Challenge environment..."

# Dependencies are already healthy (enforced by depends_on with healthchecks)
echo "✅ All dependencies are ready (DBs + LocalStack)"

# Run migrations
echo "🔄 Running importer migrations..."
cd /importer
php artisan migrate --force
echo "✅ Importer migrations complete"

echo "🔄 Running query migrations..."
cd /query
php artisan migrate --force
echo "✅ Query migrations complete"

# Create isolated test databases so the test suites never touch real data.
# Each phpunit.xml points DB_DATABASE at its *_test database on its own host.
echo "🔄 Ensuring test databases exist..."
php -r '
    $targets = [
        ["importer-db", "wayni_importer_test"],
        ["query-db", "wayni_query_test"],
    ];
    foreach ($targets as [$host, $db]) {
        $pdo = new PDO("pgsql:host=$host;port=5432;dbname=postgres", "wayni", "secret");
        $exists = $pdo->query("SELECT 1 FROM pg_database WHERE datname = " . $pdo->quote($db))->fetchColumn();
        if (!$exists) {
            $pdo->exec("CREATE DATABASE $db");
            echo "  $db: created\n";
        } else {
            echo "  $db: already exists\n";
        }
    }
'
echo "✅ Test databases ready"

# Setup LocalStack
echo "🔄 Setting up LocalStack (S3 bucket and SQS queues)..."
cd /importer
php artisan localstack:setup
echo "✅ LocalStack setup complete"

echo ""
echo "🎉 Initialization complete! Services are ready to use."
echo ""
echo "📍 Importer API: http://localhost:8001"
echo "📍 Query API: http://localhost:8000"
echo "📍 Upload UI: http://localhost:8001/upload"
