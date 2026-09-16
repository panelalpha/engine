# Cal.diy for PanelAlpha Engine

Cal.diy (formerly Cal.com) ships a `docker-compose.yml` that builds the app from source — too slow and resource-heavy for a standard deployment. The after-clone script replaces it with a minimal compose using the official prebuilt image, generates required secrets, and sets up the database connection.

The Hub image is compiled with `NEXT_PUBLIC_WEBAPP_URL=http://localhost:3000`. At container start, Cal's `start.sh` rewrites that string in `.next` when the runtime value differs — ComposeHarden injects the public vhost as `NEXT_PUBLIC_WEBAPP_URL` / `NEXTAUTH_URL`. Do not pin those keys to localhost in `.env` or compose `environment:` (that skips the rewrite).

## PanelAlpha Engine snippets

- `panelalpha-before-clone-validation.sh` — [`hooks/precheck.sh`](hooks/precheck.sh)
- `panelalpha-after-clone.sh` — [`hooks/prepare.sh`](hooks/prepare.sh)
- `docker-compose.yml` — [`overrides/docker-compose.yml`](overrides/docker-compose.yml)
- `docker/panelalpha-cli.mjs` — [`files/docker/panelalpha-cli.mjs`](files/docker/panelalpha-cli.mjs)
- `panelalpha-app.sh` — [`overrides/app.sh`](overrides/app.sh)