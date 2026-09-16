# WordPress for PanelAlpha Engine

The wordpress/wordpress repository contains WordPress source files but no `docker-compose.yml`. The deployment uses the official `wordpress` Docker image backed by a MySQL 8 database. Secure credentials and secret keys are generated and written to `.env` before the stack starts, so the installation wizard is the only manual step left.

## PanelAlpha Engine snippets

- `panelalpha-after-clone.sh` — [`hooks/prepare.sh`](hooks/prepare.sh)
- `docker-compose.yml` — [`overrides/docker-compose.yml`](overrides/docker-compose.yml)
- `wp-content/mu-plugins/panelalpha-app.php` — [`files/wp-content/mu-plugins/panelalpha-app.php`](files/wp-content/mu-plugins/panelalpha-app.php)
- `panelalpha-app.sh` — [`overrides/app.sh`](overrides/app.sh)