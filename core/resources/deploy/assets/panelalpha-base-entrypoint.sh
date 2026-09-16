#!/bin/sh
# Baked into the shared PHP base image. Everything per-project arrives on the
# bind mount; this only makes the container fit to receive it.
#
# Note what is *not* here: any dropping of privileges. The container is
# already the hosting account, because the compose file says
# `user: "<uid>:<gid>"`. Doing it that way rather than starting as root and
# dropping is what lets Apache work at all -- Docker creates the container's
# stdin/out/err owned by that uid, so `ErrorLog /dev/stderr` opens. A process
# that starts as root and drops keeps root-owned stdio and Apache dies on
# `AH00091: could not open error log file /dev/stderr`.
set -e

# --- stay transparent to an explicit command --------------------------------
#
# `docker run <image> composer install` and `docker run <image> php -m` have to
# keep working: the host build step is exactly that, and so is every operator
# looking at why an account is broken. Only a container started with the
# image's own default command gets the staged entrypoint.
if [ "$#" -gt 0 ] && [ "$1" != "apache2-foreground" ]; then
    exec docker-php-entrypoint "$@"
fi

# --- a vendor tree that went missing ----------------------------------------
#
# A failure mode the old arrangement could not have: vendor/ used to live
# inside the image, where nobody could reach it. It lives in the customer's own
# directory now, so a restored backup that predates it, or an SFTP session that
# tidied it away, leaves an application that cannot autoload.
#
# The deploy resolves vendor/ on the host; this only covers a container that
# starts later and finds it gone. Best-effort on purpose -- a boot must not
# fail because packagist is unreachable.
#
# Where vendor/ is, is the project's to say: composer.json's
# `config.vendor-dir` moves it, and ownCloud puts it in lib/composer. Assuming
# `vendor/` meant the directory always looked missing on those projects, so
# every single boot re-ran composer install to be told "Nothing to install,
# update or remove" -- seconds of startup, for nothing, forever.
if [ -f /app/composer.json ]; then
    pa_vendor=$(sed -n 's/.*"vendor-dir"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' /app/composer.json | head -n 1)
    [ -n "$pa_vendor" ] || pa_vendor=vendor
    case "$pa_vendor" in
        /* | *..*) pa_vendor=vendor ;;
    esac

    if [ ! -f "/app/$pa_vendor/autoload.php" ]; then
        echo "panelalpha: $pa_vendor/ is missing; restoring it before serving" >&2
        ( cd /app && composer install --no-dev --no-interaction --no-scripts --no-plugins ) >&2 \
            || echo "panelalpha: could not restore $pa_vendor/; the application may not start" >&2
    fi
fi

# --- hand over to the project -----------------------------------------------
if [ -f /app/panelalpha-entrypoint.sh ]; then
    exec docker-php-entrypoint /bin/sh /app/panelalpha-entrypoint.sh
fi

# No staged entrypoint on the mount. Serve the tree rather than exiting: an
# account whose deploy half-finished is better off answering than crash-looping
# with nothing in the log.
echo "panelalpha: no /app/panelalpha-entrypoint.sh on the mount; serving directly" >&2
exec docker-php-entrypoint panelalpha-serve
