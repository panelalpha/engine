#!/bin/bash
set -e

# Generate secure database credentials
MYSQL_PASSWORD=$(openssl rand -hex 16)
MYSQL_ROOT_PASSWORD=$(openssl rand -hex 16)

# Generate WordPress secret keys and salts
AUTH_KEY=$(openssl rand -hex 32)
SECURE_AUTH_KEY=$(openssl rand -hex 32)
LOGGED_IN_KEY=$(openssl rand -hex 32)
NONCE_KEY=$(openssl rand -hex 32)
AUTH_SALT=$(openssl rand -hex 32)
SECURE_AUTH_SALT=$(openssl rand -hex 32)
LOGGED_IN_SALT=$(openssl rand -hex 32)
NONCE_SALT=$(openssl rand -hex 32)

cat > .env <<EOF
MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}
MYSQL_PASSWORD=${MYSQL_PASSWORD}
WORDPRESS_AUTH_KEY=${AUTH_KEY}
WORDPRESS_SECURE_AUTH_KEY=${SECURE_AUTH_KEY}
WORDPRESS_LOGGED_IN_KEY=${LOGGED_IN_KEY}
WORDPRESS_NONCE_KEY=${NONCE_KEY}
WORDPRESS_AUTH_SALT=${AUTH_SALT}
WORDPRESS_SECURE_AUTH_SALT=${SECURE_AUTH_SALT}
WORDPRESS_LOGGED_IN_SALT=${LOGGED_IN_SALT}
WORDPRESS_NONCE_SALT=${NONCE_SALT}
EOF
