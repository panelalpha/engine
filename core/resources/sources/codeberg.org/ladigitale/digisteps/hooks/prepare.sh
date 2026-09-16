#!/bin/bash
set -e

# The build refuses to run without this file: vite.config.mjs registers
# viteStaticCopy on './.env.production' (renamed to '.env' inside dist/),
# and "No file was found to copy on /app/.env.production src" is a hard
# error, not a warning. The README says the operator creates it before
# compiling ("fichier .env.production à créer à la racine avant
# compilation"). The keys it may name are optional runtime configuration —
# VITE_DOCX_VIEWER / VITE_UPLOAD_LIMIT / VITE_UMAMI_* analytics,
# VITE_STORAGE / VITE_S3_PUBLIC_LINK plus S3_* for object storage,
# AUTHORIZED_DOMAINS for the CORS check and DB_* only when DB_DRIVER is
# mariadb/mysql — and inc/env.php read()s the copy the bundle carries,
# where DB_DRIVER defaults to SQLite and storage to fs, so an empty file is
# a valid default: everything stays off until the operator fills it in.
if [ ! -f .env.production ]; then
    printf '# Created by PanelAlpha: the Vite build fails without this file.\n# Fill in VITE_DOCX_VIEWER / VITE_UPLOAD_LIMIT / VITE_UMAMI_SCRIPT_URL / VITE_UMAMI_WEBSITE_ID / VITE_STORAGE / VITE_S3_PUBLIC_LINK / S3_* / AUTHORIZED_DOMAINS / DB_* as needed; DB_DRIVER defaults to SQLite with no server to run.\n' > .env.production
fi