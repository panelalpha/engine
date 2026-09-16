# n8n for PanelAlpha Engine

n8n repository doesn't contain `docker-compose.yml`, but it does contain instructions for deploying it with Docker by using named volume `n8n_data` and exposing port `5678`, so PanelAlpha Engine will use the below snippet for the compose file.

App management is handled by a Node.js CLI helper (`docker/panelalpha-cli.mjs`) that runs inside
the `n8n` container.  All user management (list, add, delete, password reset) is done directly
through SQLite using `node:sqlite` (built into Node.js v22+) — no REST API calls needed.
The `install` command calls `POST /rest/owner/setup` with the real public `Host` header so n8n
stores the correct instance URL.  SSO mints a valid `n8n-auth` JWT by reading the signing secret
from SQLite and replicating n8n's own `JwtService` + `AuthService` hash logic — no credentials
stored outside the volume.

## PanelAlpha Engine snippets

- `docker-compose.yml` — [`overrides/docker-compose.yml`](overrides/docker-compose.yml)
- `docker/panelalpha-cli.mjs` — [`files/docker/panelalpha-cli.mjs`](files/docker/panelalpha-cli.mjs)
- `panelalpha-app.sh` — [`overrides/app.sh`](overrides/app.sh)