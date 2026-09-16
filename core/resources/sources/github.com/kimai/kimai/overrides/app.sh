#!/bin/bash
# App management for the panel: users and SSO.
#
# Everything runs inside the application container, where Kimai's framework
# and its console are. The two helper paths are the ones the engine's file
# snippets land at: `files/panelalpha/kimai-db.php` is copied into the
# repository as `panelalpha/kimai-db.php`, and docker-compose.yml bind-mounts
# the whole checkout at /opt/kimai (see the recipe's overrides file).
#
# `install` reports what Kimai's own entrypoint already did. Kimai installs
# itself — schema, migrations, fixtures and the first admin — on every boot,
# and `kimai:install` is idempotent, so there is nothing for the panel to
# trigger; re-running it would only migrate a database that is already current.
set -uo pipefail
cd ~/project

# Answered locally: `info` is asked before anything is running, and the answer
# is about what this script can do, not about the application.
if [ "${1:-}" = 'info' ]; then
  echo '["users:list","users:add","users:delete","users:reset-password","users:sso","roles:list","install"]'
  exit 0
fi

if [ ! -f panelalpha/kimai-db.php ]; then
  echo 'MISSING_SNIPPET' >&2
  exit 1
fi

# No -f: the account's own working directory is the project, and compose
# discovers the file the engine wrote there.
compose() {
  docker compose "$@"
}

console() {
  compose exec -T app php /opt/kimai/bin/console "$@"
}

# The PDO helper, which reads DATABASE_URL out of the container's own
# environment — compose injects it from the generated file.
#
# It is copied in rather than bind-mounted. The image has no mount point for
# it, so mounting would mean an override file this recipe does not otherwise
# need; the copy is idempotent and costs one exec.
dbhelper() {
  compose exec -T app mkdir -p /opt/kimai/panelalpha >&2
  compose cp panelalpha/kimai-db.php app:/opt/kimai/panelalpha/kimai-db.php >&2
  compose exec -T app php /opt/kimai/panelalpha/kimai-db.php "$@"
}

case "${1:-}" in
  install)
    # Captured into a variable rather than piped into `grep -q`. Under
    # `pipefail`, a `grep -q` that matches exits at once and closes the pipe
    # while `docker compose exec` is still writing, so the *pipeline* reports
    # 141 and an `if` around it is false however good the match was. Measured:
    # the same check against a file succeeds, against the exec it does not.
    commands="$(console list 2>/dev/null || true)"
    if [[ "$commands" == *'kimai:user:list'* ]]; then
      console kimai:user:list >&2 2>/dev/null || true
      echo '{"installed":true}'
      exit 0
    fi
    echo '{"error":"Kimai console is not reachable in the application container"}' >&2
    exit 1
    ;;

  roles:list)
    # Kimai's own hierarchy, which its security.yaml declares. The engine's
    # user form takes one role per user; the highest wins in Kimai's checks.
    echo '["ROLE_USER","ROLE_TEAMLEAD","ROLE_ADMIN","ROLE_SUPER_ADMIN"]'
    ;;

  users:list)
    dbhelper list
    ;;

  users:add)
    login="${2:?users:add needs a login}"
    email="${3:?users:add needs an email}"
    password="${4:?users:add needs a password}"
    role="${5:-ROLE_USER}"

    # --ignore-existing keeps a retried API call idempotent, the same way
    # Kimai's own entrypoint uses it for the admin account.
    console kimai:user:create --ignore-existing "$login" "$email" "$role" "$password" >&2
    dbhelper id "$login"
    ;;

  users:delete)
    dbhelper delete "${2:?users:delete needs a user id}"
    ;;

  users:reset-password)
    # Kimai's console takes a username; the panel holds ids from users:list.
    id="${2:?users:reset-password needs a user id}"
    password="${3:?users:reset-password needs a password}"

    # Parsed with the shell, not with a host PHP: this runs in the account
    # container, which is not guaranteed to have one, and the helper's output
    # is a flat JSON object by construction.
    login="$(dbhelper show "$id" | grep -o '"username":"[^"]*"' | head -1 | cut -d'"' -f4)"
    if [ -z "$login" ]; then
      echo '{"error":"Could not resolve that user id to a Kimai username"}' >&2
      exit 1
    fi
    console kimai:user:password "$login" "$password" >&2
    echo '{"password_reset":true}'
    ;;

  users:sso)
    # Kimai mints a real signed login link — the same mechanism its own
    # password-reset flow uses — so no session is forged and nothing is
    # stored. A path is returned and the engine prepends the user's domain,
    # which is exactly the origin the link was signed for.
    id="${2:?users:sso needs a user id}"
    email="$(dbhelper show "$id" | grep -o '"email":"[^"]*"' | head -1 | cut -d'"' -f4)"
    if [ -z "$email" ]; then
      echo '{"error":"Could not resolve that user id to a Kimai email"}' >&2
      exit 1
    fi
    link="$(console kimai:user:login-link "$email" 2>/dev/null | tail -1)"
    case "$link" in
      /*) echo "{\"path\":\"$link\"}" ;;
      *) echo '{"error":"Kimai returned no login link"}' >&2; exit 1 ;;
    esac
    ;;

  *)
    echo "{\"error\":\"Unknown command '${1:-}'\"}" >&2
    exit 1
    ;;
esac
