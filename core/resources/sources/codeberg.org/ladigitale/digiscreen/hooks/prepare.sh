#!/bin/bash
set -e

# The build refuses to run without this file: vite.config.mjs registers
# viteStaticCopy on './.env.production', and "No file was found to copy on
# /app/.env.production src" is a hard error, not a warning. The README says
# the operator creates it before compiling ("fichier .env.production à créer
# à la racine avant compilation"). Every key it may name is optional runtime
# configuration — Google/Pixabay API keys, Umami analytics, S3 for Digidrive
# — and inc/env.php read()s the copy the bundle carries, so an empty file is
# a valid default: everything stays off until the operator fills it in.
if [ ! -f .env.production ]; then
    printf '# Created by PanelAlpha: the Vite build fails without this file.\n# Fill in VITE_GOOGLE_API_KEY / VITE_PIXABAY_API_KEY / VITE_UMAMI_* / S3_* as needed.\n' > .env.production
fi