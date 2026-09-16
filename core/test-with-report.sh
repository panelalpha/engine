#!/usr/bin/env bash
DEFAULT_SESSION="default"
TESTING_SESSION="${TESTING_SESSION:-$DEFAULT_SESSION}"
TESTING_SESSION="$TESTING_SESSION" IGNORE_CACHE=1 php \
-dxdebug.mode=coverage \
-dmemory_limit=256M \
vendor/bin/phpunit \
--coverage-html=public/test-report/"$TESTING_SESSION" \
"$@"
