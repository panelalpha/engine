# ActivePieces for PanelAlpha Engine

ActivePieces repository contains `docker-compose.yml`, but it requires a `.env` file that can be generated using the `tools/deploy.sh` script.

App management is handled by a Node.js CLI helper (`docker/panelalpha-cli.mjs`) that runs inside the
`app` container. Auth is bootstrapped by reading `AP_JWT_SECRET` from the container environment
(already present via `env_file: .env`) and querying PostgreSQL for the admin user's `tokenVersion`,
then minting a short-lived HS256 JWT to call the official `/v1/` REST API — no credentials stored
outside the `.env` file. Password reset goes directly to PostgreSQL because no admin REST endpoint
exists for that operation.

`users:add` uses the built-in invitation + sign-up flow: create a PENDING platform invitation via
the API, mint the invitation JWT locally (same `AP_JWT_SECRET`, audience `USER_INVITATION`) to avoid
any SMTP dependency, accept the invitation, then complete sign-up.

## PanelAlpha Engine snippets

- `panelalpha-after-clone.sh` — [`hooks/prepare.sh`](hooks/prepare.sh)
- `panelalpha-before-clone-validation.sh` — [`hooks/precheck.sh`](hooks/precheck.sh)
- `./docker-compose.override.yml` — [`overrides/docker-compose.override.yml`](overrides/docker-compose.override.yml)
- `docker/panelalpha-cli.mjs` — [`files/docker/panelalpha-cli.mjs`](files/docker/panelalpha-cli.mjs)
- `panelalpha-app.sh` — [`overrides/app.sh`](overrides/app.sh)