#!/bin/bash
# App management for the panel. Runs in the account container; everything it
# does happens inside the app container, where Magento and its vendor tree are.
set -uo pipefail
cd ~/project

# Answered locally: `info` is asked before there is anything running, and the
# answer does not depend on the store.
#
# No users:sso. Magento's admin session is a server-side session keyed to a
# form key and a session id, with no token endpoint to mint one from -- none of
# the three SSO patterns fits without forging a session, and a password
# manager's worth of admin access is the wrong place to guess.
if [ "${1:-}" = 'info' ]; then
  echo '["install","roles:list","users:list","users:add","users:delete","users:reset-password"]'
  exit 0
fi

if [ ! -f panelalpha/app.php ] || [ ! -f panelalpha/install.sh ]; then
  echo 'MISSING_SNIPPET' >&2
  exit 1
fi

php_app() {
  docker compose exec -T app php -d memory_limit=-1 /app/panelalpha/app.php "$@"
}

if [ "${1:-}" = 'install' ]; then
  url="${2:?install needs a url}"
  title="${3:-Magento}"
  admin_user="${4:?install needs an admin user}"
  admin_email="${5:?install needs an admin email}"
  admin_password="${6:?install needs an admin password}"

  docker compose exec -T \
    -e APP_URL="$url" \
    -e MAGENTO_ADMIN_USER="$admin_user" \
    -e MAGENTO_ADMIN_EMAIL="$admin_email" \
    -e MAGENTO_ADMIN_PASSWORD="$admin_password" \
    app bash /app/panelalpha/install.sh install >&2 || {
      echo '{"error":"setup:install failed; the deploy log has the output"}' >&2
      exit 1
    }

  # Best effort: the store name is a config row, not an install argument, and
  # failing to set it is not a failed install.
  docker compose exec -T app php -d memory_limit=-1 bin/magento \
    config:set general/store_information/name "$title" >/dev/null 2>&1 || true

  php_app users:id "$admin_user"
  exit $?
fi

exec docker compose exec -T app php -d memory_limit=-1 /app/panelalpha/app.php "$@"
