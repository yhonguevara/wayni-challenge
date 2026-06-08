#!/usr/bin/env bash
set -e

echo "Waiting for databases..."
until docker compose exec -T importer-db pg_isready -U wayni; do sleep 1; done
until docker compose exec -T query-db pg_isready -U wayni; do sleep 1; done

echo "Waiting for LocalStack..."
until curl -s http://localhost:4566/_localstack/health | grep -q '"s3"'; do sleep 1; done
until curl -s http://localhost:4566/_localstack/health | grep -q '"sqs"'; do sleep 1; done

echo "Running importer migrations..."
docker compose exec -T importer php artisan migrate --force

echo "Running query migrations..."
docker compose exec -T query php artisan migrate --force

echo "Setting up LocalStack (S3 + SQS)..."
docker compose exec -T importer php artisan localstack:setup

echo "✅ Setup complete!"
