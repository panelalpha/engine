#!/bin/bash
set -e
cd ~/project

# Digiboard's base docker-compose.yml is read by the engine for its redis
# sidecar (the app service itself is dropped: it is what the generated
# compose builds). The repository also ships docker-compose.override.yml for
# local test — COOKIE_SECURE=0, DOMAIN=http://localhost:3000, app published on
# 3000, traefik behind a profile — and the engine merges any override still
# sitting in the tree over the compose file it generated. Its
# DOMAIN=http://localhost:3000 would then outrank the account's own
# DOMAIN, and every link and websocket the app generates would point at
# localhost. Stashing it leaves the engine's generated file as the only
# definition.
if [ -f docker-compose.override.yml ] && [ ! -f docker-compose.override.yml.panelalpha-local ]; then
    mv docker-compose.override.yml docker-compose.override.yml.panelalpha-local
fi