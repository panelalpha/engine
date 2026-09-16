#!/bin/bash
set -e

# Magento's installer is a CLI step that wants an admin username, password and
# email, and there is nowhere for a customer to type them. Generate them here,
# before anything is built, and leave them in .env -- which the generated
# compose file loads, so the install command inside the container reads the
# same values, and `app.sh info` can print them back afterwards.
#
# The admin path is generated too. Left alone Magento invents one
# (Magento Admin URI: /admin_inabui6) and prints it once, into a build log
# nobody keeps. Choosing it here means the panel can say where the store is.

if [ -f .env ] && grep -q '^MAGENTO_ADMIN_PASSWORD=' .env; then
  # A rebuild. The store already has these credentials in its database;
  # regenerating them would lock the owner out of their own admin.
  exit 0
fi

# Magento requires a password with both letters and digits, at least 7 long.
ADMIN_PASSWORD="$(openssl rand -hex 12)Aa1"
ADMIN_URI="admin_$(openssl rand -hex 4)"

cat >> .env <<EOF
MAGENTO_ADMIN_USER=admin
MAGENTO_ADMIN_EMAIL=admin@example.com
MAGENTO_ADMIN_PASSWORD=${ADMIN_PASSWORD}
MAGENTO_ADMIN_URI=${ADMIN_URI}
EOF
