#!/bin/bash
# PanelAlpha app management for Chatwoot.
# All user operations run via `docker compose exec rails bundle exec rails runner`.
# Dynamic values are injected as -e PA_KEY=value overrides so nothing is
# interpolated into Ruby source code (prevents shell injection).
# SSO uses Chatwoot's built-in SsoAuthenticatable#generate_sso_link (Pattern A).
set -euo pipefail

# Always run from the project directory so .env and docker compose resolve correctly.
# panelalpha-app.sh lives in ~/project/ and is invoked with its full path.
cd "$(dirname "$0")"

COMMAND="${1:-}"
shift || true

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

run_runner() {
    # $1 = ruby code; remaining $@ = -e KEY=VALUE overrides
    # Capture stdout fully, then emit only the last line (our JSON print()).
    # RAILS_LOG_TO_STDOUT=true causes framework noise to appear on stdout before
    # our output; tail -1 discards it while preserving the original exit code.
    local code="$1"; shift || true
    local out rc
    out=$(docker compose --file docker-compose.yml exec -T -e LOG_LEVEL=error "$@" rails \
        bundle exec rails runner "$code")
    rc=$?
    printf '%s' "$out" | tail -1
    return $rc
}

stderr_json() { printf '%s' "$1" >&2; }

# Read FRONTEND_URL from .env (used by install and users:sso)
get_frontend_url() {
    grep -E '^FRONTEND_URL=' .env 2>/dev/null | head -1 | cut -d= -f2- | tr -d "'\""
}

# ---------------------------------------------------------------------------
# Commands
# ---------------------------------------------------------------------------

case "$COMMAND" in

  info)
    echo '["install","roles:list","users:list","users:add","users:delete","users:reset-password","users:sso"]'
    ;;

  roles:list)
    echo '["administrator","agent"]'
    ;;

  # install <url> <title> <admin_user> <admin_email> <admin_password>
  install)
    PA_URL="${1:-}"; PA_TITLE="${2:-}"; PA_ADMIN_USER="${3:-}"
    PA_ADMIN_EMAIL="${4:-}"; PA_ADMIN_PASSWORD="${5:-}"
    if [[ -z "$PA_URL" || -z "$PA_ADMIN_USER" || -z "$PA_ADMIN_EMAIL" || -z "$PA_ADMIN_PASSWORD" ]]; then
        stderr_json '{"error":"Usage: install <url> <title> <admin_user> <admin_email> <admin_password>"}'
        exit 1
    fi

    # Write FRONTEND_URL into .env so Chatwoot knows its public address
    if grep -qE '^FRONTEND_URL=' .env 2>/dev/null; then
        sed -i "s|^FRONTEND_URL=.*|FRONTEND_URL=${PA_URL}|" .env
    else
        printf '\nFRONTEND_URL=%s\n' "$PA_URL" >> .env
    fi

    # Restart web + worker so the new FRONTEND_URL is live in running containers
    docker compose --file docker-compose.yml up -d rails sidekiq

    # Wait for the Rails process to be ready (up to 120 s)
    for i in $(seq 1 40); do
        docker compose --file docker-compose.yml exec -T rails bundle exec rails runner 'puts "ready"' \
            >/dev/null 2>&1 && break || sleep 3
        if [[ $i -eq 40 ]]; then
            stderr_json '{"error":"Timed out waiting for Chatwoot Rails process to start"}'
            exit 1
        fi
    done

    # Create the account, admin user and link them; idempotent if already set up
    RESULT=$(run_runner '
begin
    # If an account already exists just return the existing admin user id
    existing = Account.first
    if existing
        au = existing.account_users.find_by(role: :administrator)
        print({ id: au&.user_id.to_s }.to_json)
    else
        account = Account.create!(name: ENV.fetch("PA_TITLE", "Chatwoot"))
        user = User.new(
            name:                  ENV["PA_ADMIN_USER"],
            email:                 ENV["PA_ADMIN_EMAIL"],
            password:              ENV["PA_ADMIN_PASSWORD"],
            password_confirmation: ENV["PA_ADMIN_PASSWORD"]
        )
        user.skip_confirmation!
        user.save!(validate: false)
        AccountUser.create!(account: account, user: user, role: :administrator)
        # Clear the onboarding flag so the setup wizard does not appear
        begin; Redis::Alfred.delete(Redis::Alfred::CHATWOOT_INSTALLATION_ONBOARDING); rescue; end
        print({ id: user.id.to_s }.to_json)
    end
rescue => e
    $stderr.print({ error: e.message }.to_json)
    exit 1
end
' \
        -e "PA_TITLE=${PA_TITLE}" \
        -e "PA_ADMIN_USER=${PA_ADMIN_USER}" \
        -e "PA_ADMIN_EMAIL=${PA_ADMIN_EMAIL}" \
        -e "PA_ADMIN_PASSWORD=${PA_ADMIN_PASSWORD}")
    echo "$RESULT"
    ;;

  # users:list
  users:list)
    run_runner '
begin
    account = Account.first
    raise "No account found" unless account
    users = account.account_users.includes(:user).map do |au|
        u = au.user
        { id: u.id.to_s, username: u.name, email: u.email.to_s, role: au.role }
    end
    print users.to_json
rescue => e
    $stderr.print({ error: e.message }.to_json)
    exit 1
end
'
    ;;

  # users:add <login> <email> <password> <role>
  users:add)
    PA_LOGIN="${1:-}"; PA_EMAIL="${2:-}"; PA_PASSWORD="${3:-}"; PA_ROLE="${4:-}"
    if [[ -z "$PA_EMAIL" || -z "$PA_PASSWORD" || -z "$PA_ROLE" ]]; then
        stderr_json '{"error":"Usage: users:add <login> <email> <password> <role>"}'
        exit 1
    fi
    run_runner '
begin
    account = Account.first
    raise "No account found" unless account
    valid_roles = %w[administrator agent]
    role = ENV["PA_ROLE"].downcase
    raise "Invalid role \"#{role}\". Valid: #{valid_roles.join(", ")}" unless valid_roles.include?(role)

    user = User.find_by(email: ENV["PA_EMAIL"])
    unless user
        user = User.new(
            name:                  ENV["PA_LOGIN"].presence || ENV["PA_EMAIL"].split("@").first,
            email:                 ENV["PA_EMAIL"],
            password:              ENV["PA_PASSWORD"],
            password_confirmation: ENV["PA_PASSWORD"]
        )
        user.skip_confirmation!
        user.save!(validate: false)
    end

    au = AccountUser.find_or_initialize_by(account: account, user: user)
    au.role = role
    au.save!

    print({ id: user.id.to_s }.to_json)
rescue => e
    $stderr.print({ error: e.message }.to_json)
    exit 1
end
' \
        -e "PA_LOGIN=${PA_LOGIN}" \
        -e "PA_EMAIL=${PA_EMAIL}" \
        -e "PA_PASSWORD=${PA_PASSWORD}" \
        -e "PA_ROLE=${PA_ROLE}"
    ;;

  # users:delete <userId>
  users:delete)
    PA_USER_ID="${1:-}"
    if [[ -z "$PA_USER_ID" ]]; then
        stderr_json '{"error":"Usage: users:delete <userId>"}'
        exit 1
    fi
    run_runner '
begin
    account = Account.first
    raise "No account found" unless account
    user = User.find(ENV["PA_USER_ID"].to_i)
    account.account_users.find_by!(user: user).destroy!
    # Remove the user entirely if they no longer belong to any account
    user.destroy! if user.account_users.count.zero?
    print({ success: true }.to_json)
rescue ActiveRecord::RecordNotFound
    $stderr.print({ error: "User not found" }.to_json)
    exit 1
rescue => e
    $stderr.print({ error: e.message }.to_json)
    exit 1
end
' \
        -e "PA_USER_ID=${PA_USER_ID}"
    ;;

  # users:reset-password <userId> <newPassword>
  users:reset-password)
    PA_USER_ID="${1:-}"; PA_PASSWORD="${2:-}"
    if [[ -z "$PA_USER_ID" || -z "$PA_PASSWORD" ]]; then
        stderr_json '{"error":"Usage: users:reset-password <userId> <newPassword>"}'
        exit 1
    fi
    run_runner '
begin
    user = User.find(ENV["PA_USER_ID"].to_i)
    user.password              = ENV["PA_PASSWORD"]
    user.password_confirmation = ENV["PA_PASSWORD"]
    user.save!(validate: false)
    print({ success: true }.to_json)
rescue ActiveRecord::RecordNotFound
    $stderr.print({ error: "User not found" }.to_json)
    exit 1
rescue => e
    $stderr.print({ error: e.message }.to_json)
    exit 1
end
' \
        -e "PA_USER_ID=${PA_USER_ID}" \
        -e "PA_PASSWORD=${PA_PASSWORD}"
    ;;

  # users:sso <userId>
  users:sso)
    PA_USER_ID="${1:-}"
    if [[ -z "$PA_USER_ID" ]]; then
        stderr_json '{"error":"Usage: users:sso <userId>"}'
        exit 1
    fi
    # Pass FRONTEND_URL explicitly so generate_sso_link returns the correct domain
    # even if the container was not restarted after install wrote it to .env
    PA_FRONTEND_URL="$(get_frontend_url)"
    run_runner '
begin
    user = User.find(ENV["PA_USER_ID"].to_i)
    # Override FRONTEND_URL in process env so generate_sso_link uses the right domain
    ENV["FRONTEND_URL"] = ENV["PA_FRONTEND_URL"] if ENV["PA_FRONTEND_URL"].present?
    url = user.generate_sso_link
    print({ url: url }.to_json)
rescue ActiveRecord::RecordNotFound
    $stderr.print({ error: "User not found" }.to_json)
    exit 1
rescue => e
    $stderr.print({ error: e.message }.to_json)
    exit 1
end
' \
        -e "PA_USER_ID=${PA_USER_ID}" \
        -e "PA_FRONTEND_URL=${PA_FRONTEND_URL}"
    ;;

  *)
    stderr_json "{\"error\":\"Unknown command: ${COMMAND}\"}"
    exit 1
    ;;

esac
