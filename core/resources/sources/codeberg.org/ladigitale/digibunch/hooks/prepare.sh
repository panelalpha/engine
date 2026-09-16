#!/bin/bash
set -e

# The build refuses to run without this file: vite.config.mjs registers
# viteStaticCopy on './.env.production' (renamed to '.env' inside dist/),
# and "No file was found to copy on /app/.env.production src" is a hard
# error, not a warning. The README says the operator creates it before
# compiling ("fichier .env.production à créer à la racine avant
# compilation"). The keys it may name are optional: VITE_UMAMI_* only
# injects an analytics script, and inc/env.php read()s the copy the bundle
# carries, where an empty AUTHORIZED_DOMAINS keeps CORS wide open ('*') —
# so an empty file is a valid default and everything stays off until the
# operator fills it in.
if [ ! -f .env.production ]; then
    printf '# Created by PanelAlpha: the Vite build fails without this file.\n# Fill in VITE_UMAMI_SCRIPT_URL / VITE_UMAMI_WEBSITE_ID as needed; AUTHORIZED_DOMAINS stays open ("*") while unset.\n' > .env.production
fi

# The SQLite database lives at ../data/digibunch.db, outside the deployed
# docroot, and inc/db.php creates that directory itself on the first write
# — inside the container, where it has the rights to. Do not mkdir it here:
# the prepare hook runs outside the docroot tree, where the account user
# cannot write ("mkdir: cannot create directory '../data': Permission
# denied"), and the failure would abort the whole deploy.