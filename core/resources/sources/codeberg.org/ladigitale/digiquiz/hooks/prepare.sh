#!/bin/bash
set -e

# The build refuses to run without this file: vite.config.mjs registers
# viteStaticCopy on './.env.production' (renamed to '.env' inside dist/),
# and "No file was found to copy on /app/.env.production src" is a hard
# error, not a warning. The README says the operator creates it before
# compiling ("fichier .env.production à créer à la racine avant
# compilation"). The keys it may name are optional: VITE_* (DOMAIN, FOLDER,
# RESULTS_LINK, UPLOAD_LIMIT, UMAMI_*) all have window.location or
# "off" fallbacks in the code, AUTHORIZED_DOMAINS stays open ('*') while
# unset — so an empty file is a valid default and everything stays off
# until the operator fills it in.
if [ ! -f .env.production ]; then
    printf '# Created by PanelAlpha: the Vite build fails without this file.\n# Fill in VITE_UMAMI_SCRIPT_URL / VITE_UMAMI_WEBSITE_ID as needed; AUTHORIZED_DOMAINS stays open ("*") while unset.\n' > .env.production
fi

# The SQLite database lives at inc/digiquiz.db, inside the deployed docroot
# but excluded from the build's copy of inc/ (viteStaticCopy copies
# 'inc/!(*.db)'), so it is not overwritten on rebuild. inc/db.php creates
# the file itself on first use — inside the container, where it has the
# rights to. Nothing to mkdir here.