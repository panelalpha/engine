#!/bin/bash
set -e
cd ~/project

# The compose file's db and redis services read env_file: ./.env.docker, which
# is gitignored — a fresh checkout has no such file, and `docker compose up`
# refuses to start a service whose env_file is missing. The engine's runtime
# merge keeps that key, so the account's .env.docker has to exist at boot. It
# is also where the operator's variables are documented: write .env first
# (what the generated app service reads through env_file: .env) and copy it.
if [ ! -f .env ]; then
    SECRET_KEY=$(openssl rand -hex 32)

    cat > .env <<EOF
SECRET_KEY='${SECRET_KEY}'
SERVER_NAME='localhost'
DATABASE_URL=postgresql+psycopg2://piefed:${SECRET_KEY}@postgres:5432/piefed
POSTGRES_USER=piefed
POSTGRES_PASSWORD=${SECRET_KEY}
POSTGRES_DB=piefed
CACHE_TYPE='RedisCache'
CACHE_REDIS_DB=1
CACHE_REDIS_URL='redis://redis:6379/0'
CELERY_BROKER_URL='redis://redis:6379/1'
RESULT_BACKEND='redis://redis:6379/1'
FULL_AP_CONTEXT=0
ENABLE_ALPHA_API='true'
CORS_ALLOW_ORIGIN='*'
HTTP_PROTOCOL='https'
EOF
    cp .env .env.docker
fi

# The image builds app/translations at build time (pybabel compile) but the
# checkout has no compiled catalogues and gunicorn --preload reads them at
# import time; compile what Babel finds so the first request does not fail on
# a missing translations directory.
mkdir -p app/translations
find app/translations -name "*.mo" 2>/dev/null | grep -q . || pybabel compile -d app/translations 2>/dev/null || true

# Media and tmp are bind mounts in the stack this replaces (./media, ./tmp);
# gunicorn runs as root inside the image, but the account's own uid owns the
# checkout, so make sure the write targets exist.
mkdir -p app/static/media app/static/tmp logs