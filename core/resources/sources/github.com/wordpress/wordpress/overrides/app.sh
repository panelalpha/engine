#!/bin/bash
cd ~/project
if [ "${1:-}" = 'info' ]; then
  echo '["users:list","users:add","users:delete","users:reset-password","users:sso","roles:list","install"]'
  exit 0
fi
if [ ! -f wp-content/mu-plugins/panelalpha-app.php ]; then
  echo 'MISSING_SNIPPET' >&2
  exit 1
fi
exec docker compose exec -T -w /var/www/html wordpress php wp-content/mu-plugins/panelalpha-app.php "$@"
