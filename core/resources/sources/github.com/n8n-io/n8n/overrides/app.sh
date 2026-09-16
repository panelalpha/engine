#!/bin/bash
cd ~/project

# info is answered locally — no running app needed
if [ "${1:-}" = 'info' ]; then
  echo '["install","roles:list","users:list","users:add","users:delete","users:reset-password","users:sso"]'
  exit 0
fi

if [ ! -f docker/panelalpha-cli.mjs ]; then
  echo 'MISSING_SNIPPET' >&2
  exit 1
fi

exec docker compose exec -T n8n node --no-warnings /home/node/panelalpha/panelalpha-cli.mjs "$@"
