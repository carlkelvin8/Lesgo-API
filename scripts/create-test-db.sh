#!/usr/bin/env bash
# Creates the lesgo_test database inside the running Docker PostgreSQL container.
# Run once before executing tests.

set -e

CONTAINER=${POSTGRES_CONTAINER:-lesgo-postgres}
DB_USER=${DB_USERNAME:-postgres}
DB_NAME=lesgo_test

echo "Creating test database '$DB_NAME' in container '$CONTAINER'..."

docker exec -it "$CONTAINER" psql -U "$DB_USER" -c "
  SELECT 'CREATE DATABASE $DB_NAME'
  WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '$DB_NAME')
" | grep -q "CREATE DATABASE" && \
  docker exec -it "$CONTAINER" psql -U "$DB_USER" -c "CREATE DATABASE $DB_NAME;" || \
  echo "Database '$DB_NAME' already exists."

echo "Done."
