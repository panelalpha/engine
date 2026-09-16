# Digiscreen (La Digitale) for PanelAlpha Engine

Interactive whiteboard/wallpaper for the classroom
(https://codeberg.org/ladigitale/digiscreen). A Vue 3 + Vite SPA whose
frontend talks to a small PHP backend in `inc/`: SQLite (`inc/digiscreen.db`,
created on first use), session-based, no database server.

## How it builds and runs

- `composer.json` exists but only holds `aws/aws-sdk-php` — needed solely for
  the optional Digidrive/S3 saving. The engine resolves it with the PHP
  strategy; a project without `S3_*` env vars simply logs and keeps serving.
- `npm run build` (Vite) is required. `vite.config.mjs` registers
  `viteStaticCopy` on `./.env.production`, and a missing file is a **hard
  build error**, so `hooks/prepare.sh` writes a default one. The README asks
  the operator to create this file before compiling; every key it may name is
  optional.
- The built `dist/` is the whole application: the SPA plus the `inc/` PHP
  endpoints, `vendor/`, `.env`, README and LICENSE, all copied in by the same
  plugin. The manifest therefore sets `docroot: dist` — without it the
  document root would be the repository root, whose `index.html` is the
  *dev* entry (`./src/main.js`, module graph) and not the built app.
- PHP: the README requires 8.4+; the deploy resolves PHP from composer.json,
  which pins nothing here, so it serves on the engine default (8.3). The
  backend code tested runs unchanged on it. Pin a version by adding a
  `require.php` constraint to composer.json.

## Environment variables

`AUTHORIZED_DOMAINS` (comma-separated list of origins allowed to POST to the
API, `*` or empty for all), `VITE_GOOGLE_API_KEY` (YouTube search),
`VITE_PIXABAY_API_KEY`, `VITE_UMAMI_SCRIPT_URL` + `VITE_UMAMI_WEBSITE_ID`,
and `S3_*` (only with Digidrive). Set them via the project's environment
variables **before** deploying — the frontend ones are compiled into the
bundle at build time, the rest are read at request time from the `.env` file
the build ships inside `dist/`.

## PanelAlpha Engine snippets

- `panelalpha-after-clone.sh` — [`hooks/prepare.sh`](hooks/prepare.sh)