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
