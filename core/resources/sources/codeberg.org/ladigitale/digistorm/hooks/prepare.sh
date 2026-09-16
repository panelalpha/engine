#!/bin/bash
set -e
cd ~/project

# Digistorm's compose file interpolates ${DB_PWD}, ${SESSION_KEY} and
# ${ACME_EMAIL} (the last one only for the traefik service this stack does not
# run). Compose treats an unset ${VAR} in a command as a blank, but the
# application itself throws at startup without SESSION_KEY and locks out every
# session and rate-limit store without DB_PWD, so real values are generated
# here — stable for the life of the account because they are only written when
# missing.
if [ ! -f .env ] || ! grep -q '^DB_PWD=' .env 2>/dev/null; then
    DB_PWD=$(openssl rand -hex 16)
    SESSION_KEY=$(openssl rand -hex 32)
    cat > .env <<EOF
DB_PWD=${DB_PWD}
SESSION_KEY=${SESSION_KEY}
COOKIE_SECURE=0
VITE_STORAGE=fs
EOF
fi

# The repository ships its own docker-compose.override.yml for local test
# (COOKIE_SECURE=0, DOMAIN=http://localhost:3000, traefik behind a profile).
# The engine runs the repo's base docker-compose.yml for its runtime services
# and then merges any override still sitting in the tree over the compose file
# it generated — traefik's profile gate then removes the service app still
# depends_on, and compose refuses the whole project. Stashing it leaves the
# engine's generated file as the only definition.
if [ -f docker-compose.override.yml ] && [ ! -f docker-compose.override.yml.panelalpha-local ]; then
    mv docker-compose.override.yml docker-compose.override.yml.panelalpha-local
fi