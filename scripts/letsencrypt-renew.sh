#!/bin/bash
#
# Renew the engine's Let's Encrypt lineages and reinstall the served one.
#
# "The served one" is whichever lineage crt/server.cert already holds, not
# whichever exists: renewal refreshes what is on :2011 and never changes it.
#
# Run from /etc/cron.d/panelalpha-letsencrypt every six hours; safe by hand.
# The HTTP-01 challenge needs :80, which sites-http holds, so a renewal costs
# a few seconds of webserver downtime. To keep that to the days something is
# actually due, the lineages are checked here first and `certbot renew` only
# runs when one is inside its renewal window: a third of its lifetime for the
# ~6-day IP lineage, thirty days for the 90-day domain one -- certbot's own
# thresholds. --always skips that gate and lets certbot decide by itself.
#
# Reinstalling is done here rather than by a certbot deploy hook: certbot runs
# inside its container, where neither this tree nor docker exist.
#
# Usage: letsencrypt-renew.sh [--always] [certbot renew options...]

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")" && pwd)"
# shellcheck source=./letsencrypt-lib.sh
source "${SCRIPT_DIR}/letsencrypt-lib.sh"

ALWAYS=0
if [ "${1:-}" = "--always" ]; then
    ALWAYS=1
    shift
fi

# 0 when the lineage should be renewed now. Lifetime and remaining time come
# from the certificate itself, so the rule holds for any profile.
lineage_due() {
    local cert="$(le_lineage_dir "$1")/fullchain.pem"
    [ -f "$cert" ] || return 1
    local not_before not_after now lifetime remaining threshold
    not_before=$(date -d "$(openssl x509 -in "$cert" -noout -startdate | cut -d= -f2)" +%s 2>/dev/null) || return 0
    not_after=$(date -d "$(openssl x509 -in "$cert" -noout -enddate | cut -d= -f2)" +%s 2>/dev/null) || return 0
    now=$(date +%s)
    lifetime=$((not_after - not_before))
    remaining=$((not_after - now))
    threshold=$((lifetime / 3))
    [ "$threshold" -gt $((30 * 86400)) ] && threshold=$((30 * 86400))
    [ "$remaining" -lt "$threshold" ]
}

main() {
    [ -d "${LE_LETSENCRYPT_DIR}/renewal" ] || return 0

    # Which lineage is on :2011, read before certbot touches anything: renewal
    # keeps serving whatever was being served. Whether the domain lineage
    # merely exists is the wrong question -- one issued at some point and since
    # replaced on :2011 by the IP certificate (a later re-request failed, an
    # administrator went back to the address) would be silently restored here.
    local serve
    serve=$(le_served_lineage)

    local -a due=()
    local name
    for name in "$LE_CERT_NAME" "$LE_IP_CERT_NAME"; do
        if lineage_due "$name"; then
            due+=("$name")
        fi
    done

    echo ">>> $(date -Is) renewal check: ${due[*]:-nothing due}"
    if [ "$ALWAYS" = 1 ] || [ "${#due[@]}" -gt 0 ]; then
        le_release_port_80
        le_certbot renew --non-interactive "$@"
        le_restore_stack
    fi

    # Nothing of ours is being served -- the installer's self-signed pair, or a
    # crt/ that was replaced -- so this is the first install of a lineage, and
    # the engine's own order applies: the domain one, the IP one otherwise.
    if [ -z "$serve" ]; then
        if le_lineage_exists "$LE_CERT_NAME"; then
            serve="$LE_CERT_NAME"
        elif le_lineage_exists "$LE_IP_CERT_NAME"; then
            serve="$LE_IP_CERT_NAME"
        fi
    fi
    # Copies only when the certificate on disk changed.
    [ -n "$serve" ] && le_install_lineage "$serve" server
    if le_lineage_exists "$LE_IP_CERT_NAME"; then
        le_install_lineage "$LE_IP_CERT_NAME" server-ip
    fi
}

if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    main "$@"
fi
