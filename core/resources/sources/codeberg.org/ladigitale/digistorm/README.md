# Digistorm (La Digitale)

Collaborative surveys, quizzes, brainstorms and word clouds. Not a plain SPA
like the rest of the ladigitale family: it is a **vike (vite-plugin-ssr)
application** — an Express 5 server (`server/app.js`) renders the Vue pages
server-side and holds all state in **Redis**.

## What the application needs

| Variable | Why |
|---|---|
| `SESSION_KEY` | mandatory in production — `server/app.js` throws at startup without it |
| `DB_PWD` | the redis `--requirepass` the compose file starts redis with; the app connects to `redis://default:<pwd>@redis:6379` |
| `DB_HOST` / `DB_PORT` | the redis sidecar (compose sets them) |
| `DOMAIN` | the public origin; `hote.replace(...)` in `server/app.js` crashes if it is unset in production |
| `COOKIE_SECURE=0` | upstream's local-test value; the panel URL is HTTPS but the container is reached over the account network |
| `VITE_*` | baked into the client bundle at build time (upload limit, default language, storage) |

SMTP and S3 are optional (file uploads default to the filesystem under
`static/fichiers`).

## What the engine path does

`extends: dockerfile` — the app builds from the repository's own Dockerfile
(node:24-bookworm-slim, which installs `python3 make g++` for the native
`eiows`/`bcrypt`/`sharp` modules — the engine's unprivileged host-compile
container cannot), and the engine keeps the `redis` service from the repo's
docker-compose.yml as the sidecar the app connects to. The prepare hook:

- generates `DB_PWD`/`SESSION_KEY` into `.env` (the repo's compose file
  interpolates `requirepass` and the app's session store from them),
- stashes the repository's own `docker-compose.override.yml`, whose
  `profiles: [with-traefik]` would otherwise remove the traefik service that
  `app.depends_on` still names — a dangling dependency compose refuses.

This directory's own `overrides/docker-compose.override.yml` re-adds the
`--requirepass ${DB_PWD}` the stash takes away (the base file carries it and
stashing an override also drops what it patched), keeping redis
password-protected as upstream intended.

`COOKIE_SECURE=0` and `VITE_STORAGE=fs` ride in `.env` as the panel-account
defaults; an operator can override any of them from the account's env_vars.

There is no `overrides/app.sh`: Digistorm's accounts are self-serve
(sign-up in the UI creates the users), so the app endpoints answer
`MISSING_SNIPPET` and the engine falls back to its generic user list.