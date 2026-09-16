#!/bin/bash
set -e

# Generate secure credentials
SECRET_KEY_BASE=$(openssl rand -hex 64)
POSTGRES_PASSWORD=$(openssl rand -hex 16)
REDIS_PASSWORD=$(openssl rand -hex 16)

# Create .env from the example bundled in the repo
cp .env.example .env

# Apply production values to .env
sed -i "s|^SECRET_KEY_BASE=.*|SECRET_KEY_BASE=${SECRET_KEY_BASE}|" .env
sed -i "s|^POSTGRES_PASSWORD=.*|POSTGRES_PASSWORD=${POSTGRES_PASSWORD}|" .env
sed -i "s|^REDIS_PASSWORD=.*|REDIS_PASSWORD=${REDIS_PASSWORD}|" .env
sed -i "s|^REDIS_URL=.*|REDIS_URL=redis://:${REDIS_PASSWORD}@redis:6379|" .env
sed -i "s|^RAILS_ENV=.*|RAILS_ENV=production|" .env

# Copy production compose; patch empty POSTGRES_PASSWORD and loopback-only port bindings
cp docker-compose.production.yaml docker-compose.yml
sed -i "s/POSTGRES_PASSWORD=$/POSTGRES_PASSWORD=${POSTGRES_PASSWORD}/" docker-compose.yml
sed -i "s/127\.0\.0\.1:\([0-9]*\):\([0-9]*\)/\1:\2/g" docker-compose.yml
# Remove the obsolete top-level 'version:' field to silence docker compose warnings
sed -i '/^version:/d' docker-compose.yml

# Start database services and wait for postgres to be ready
docker compose --file docker-compose.yml up -d postgres redis
for i in $(seq 1 30); do
    docker compose --file docker-compose.yml exec -T postgres pg_isready -U postgres 2>/dev/null && break || sleep 3
done

# Prepare the database (creates schema and seeds initial data)
docker compose --file docker-compose.yml run --rm rails bundle exec rails db:chatwoot_prepare
