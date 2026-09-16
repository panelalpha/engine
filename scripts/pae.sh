#!/usr/bin/env bash

cd "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")/.."

exec docker compose exec core php artisan "$@"
