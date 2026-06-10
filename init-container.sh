#!/bin/bash
set -e

echo "🚀 Initializing BCRA Challenge environment..."

# Wait for importer database
echo "⏳ Waiting for importer-db..."
cd /importer
until php artisan db:show --connection=pgsql > /dev/null 2>&1; do
  sleep 1
done
echo "✅ importer-db is ready"

# Wait for query database
echo "⏳ Waiting for query-db..."
cd /query
until php artisan db:show --connection=pgsql > /dev/null 2>&1; do
  sleep 1
done
echo "✅ query-db is ready"

# Wait for LocalStack
echo "⏳ Waiting for LocalStack..."
until curl -f http://localstack:4566/_localstack/health > /dev/null 2>&1; do
  sleep 1
done
echo "✅ LocalStack is ready"

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
