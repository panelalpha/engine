# Chatwoot for PanelAlpha Engine

Chatwoot's repository contains `docker-compose.yaml` (development) and `docker-compose.production.yaml`. The after-clone script copies the production compose file, generates secure credentials, and prepares the database.

App management is handled by `panelalpha-app.sh` using `docker compose exec rails bundle exec rails runner`. All dynamic values (emails, passwords) are passed as `-e PA_KEY=value` env overrides to avoid shell injection — Ruby reads them via `ENV["PA_KEY"]`. SSO uses Chatwoot's built-in `SsoAuthenticatable#generate_sso_link` (stores a short-lived token in Redis and returns a full login URL) — Pattern A, no custom endpoints required.

## PanelAlpha Engine snippets

- `panelalpha-before-clone-validation.sh` — [`hooks/precheck.sh`](hooks/precheck.sh)
- `panelalpha-after-clone.sh` — [`hooks/prepare.sh`](hooks/prepare.sh)
- `panelalpha-app.sh` — [`overrides/app.sh`](overrides/app.sh)