#!/bin/bash
cd ~/project

# info and roles:list are answered locally — no running app needed
if [ "${1:-}" = 'info' ]; then
  echo '["install","roles:list","users:list","users:add","users:delete","users:reset-password"]'
  exit 0
fi

if [ "${1:-}" = 'roles:list' ]; then
  echo '["ADMIN","MEMBER"]'
  exit 0
fi

if [ ! -f ./docker-compose.override.yml ] || [ ! -f docker/panelalpha-cli.mjs ]; then
  echo 'MISSING_SNIPPET' >&2
  exit 1
fi

exec docker compose exec -T app node /usr/src/app/docker/panelalpha-cli.mjs "$@"
