#!/bin/bash
set -e
cd ~/project

APP_SECRET=$(openssl rand -hex 32)
POSTGRES_PASSWORD=$(openssl rand -hex 16)

cat > .env <<EOF
APP_SECRET=${APP_SECRET}
POSTGRES_PASSWORD=${POSTGRES_PASSWORD}
DATABASE_URL=postgresql://umami:${POSTGRES_PASSWORD}@db:5432/umami
EOF
