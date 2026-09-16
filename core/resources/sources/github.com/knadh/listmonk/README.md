# listmonk for PanelAlpha Engine

listmonk ships its own `docker-compose.yml` and works out of the box with no
extra setup required. App management queries go directly to the PostgreSQL
container using the credentials already present in the `db` container's
environment (`$POSTGRES_USER`, `$POSTGRES_PASSWORD`, `$POSTGRES_DB`).

SSO is handled by the PanelAlpha Engine itself: `users:sso` inserts a session
row into listmonk's database and returns the cookie name/value to the engine.
The engine stores a short-lived single-use token, then redirects the browser to
`/panelalpha-sso?token=<engine-token>` which is intercepted by the nginx-proxy,
sets the cookie on the app's domain, and forwards the browser to `/admin`.  No
extra containers or sidecars are needed.

## PanelAlpha Engine snippets

- `panelalpha-app.sh` — [`overrides/app.sh`](overrides/app.sh)