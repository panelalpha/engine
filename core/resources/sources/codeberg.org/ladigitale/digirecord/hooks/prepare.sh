#!/bin/bash
set -e

# The build refuses to run without this file: vite.config.mjs registers
# viteStaticCopy on './.env.production' (renamed to '.env' inside dist/),
# and "No file was found to copy on /app/.env.production src" is a hard
# error, not a warning. The README says the operator creates it before
# compiling. The keys it may name are optional runtime configuration —
# VITE_UMAMI_* analytics, VITE_UPLOAD_LIMIT, VITE_STORAGE (fs by default,
# or S3 via inc/s3.php) — and inc/env.php read()s the copy the bundle
# carries, where SQLite stays the default DB_DRIVER, so an empty file is a
# valid default: everything stays off until the operator fills it in.
if [ ! -f .env.production ]; then
    printf '# Created by PanelAlpha: the Vite build fails without this file.\n# Fill in VITE_UMAMI_SCRIPT_URL / VITE_UMAMI_WEBSITE_ID / VITE_UPLOAD_LIMIT / VITE_STORAGE as needed; DB_DRIVER defaults to SQLite with no server to run.\n' > .env.production
fi