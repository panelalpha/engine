#!/usr/bin/env bash
# Recover a wedged Sysbox (DinD) user container WITHOUT rebooting the host.
#
# Symptom: nested `docker ps` / `docker compose` hangs; host shows D-state
# processes on wchan fuse_flush / seccomp_do_user_notification; `docker kill`
# and `docker rm -f` hang or return "did not receive an exit event".
#
# Root cause: sysbox-fs FUSE deadlock under concurrent nested container starts
# or after host OOM (nestybox/sysbox#998). Fixed upstream in Sysbox 0.7.1;
# this script is the operational escape hatch when a container is already stuck.
#
# Method (proven on PanelAlpha engines):
#   1. docker update --restart=no
#   2. echo 1 > /sys/fs/fuse/connections/<id>/abort   # unblocks fuse_flush
#   3. echo 1 > cgroup.kill                           # SIGKILL subtree
#   4. docker rm -f
#   5. optional wipe of ~/docker (inner Docker data-root)
#   6. optional docker compose up -d recreate
#
# Usage:
#   recover-sysbox-dind.sh --username=USER [--wipe-inner] [--recreate]
#   recover-sysbox-dind.sh --all-stuck [--wipe-inner] [--recreate]
#   recover-sysbox-dind.sh --detect

set -euo pipefail

USERNAME=""
ALL_STUCK=0
WIPE_INNER=0
RECREATE=0
DETECT_ONLY=0
COMPOSE_ROOT="${COMPOSE_ROOT:-/opt/panelalpha/shared-hosting/users}"

log() { echo "[recover-sysbox-dind] $*"; }
err() { echo "[recover-sysbox-dind] ERROR: $*" >&2; }

usage() {
    sed -n '2,20p' "$0" | sed 's/^# \{0,1\}//'
    exit 1
}

while [ $# -gt 0 ]; do
    case "$1" in
        --username=*) USERNAME="${1#*=}" ;;
        --username) USERNAME="${2:-}"; shift ;;
        --all-stuck) ALL_STUCK=1 ;;
        --wipe-inner) WIPE_INNER=1 ;;
        --recreate) RECREATE=1 ;;
        --detect) DETECT_ONLY=1 ;;
        -h|--help) usage ;;
        *) err "unknown arg: $1"; usage ;;
    esac
    shift
done

d_state_count() {
    ps -eo stat 2>/dev/null | grep -c '^D' || true
}

is_sysbox_container() {
    local name="$1"
    local runtime
    runtime=$(docker inspect -f '{{.HostConfig.Runtime}}' "$name" 2>/dev/null || true)
    [ "$runtime" = "sysbox-runc" ]
}

fuse_id_for_container() {
    local cid="$1"
    local line fuse_id
    while IFS= read -r line; do
        case "$line" in
            *"/var/lib/sysboxfs/${cid}"*)
                fuse_id=$(echo "$line" | awk '{print $3}' | cut -d: -f2)
                echo "$fuse_id"
                return 0
                ;;
        esac
    done < /proc/self/mountinfo
    return 1
}

container_looks_stuck() {
    local name="$1"
    local cid scope fuse_id waiting

    # Outer docker exec into the DinD hangs or fails when sysbox-fs is wedged.
    if ! timeout 8 docker exec "$name" true >/dev/null 2>&1; then
        return 0
    fi
    # Inner docker CLI hang is the common user-visible symptom.
    if ! timeout 8 docker exec "$name" docker info >/dev/null 2>&1; then
        return 0
    fi

    cid=$(docker inspect -f '{{.Id}}' "$name")
    if fuse_id=$(fuse_id_for_container "$cid"); then
        waiting=$(cat "/sys/fs/fuse/connections/${fuse_id}/waiting" 2>/dev/null || echo 0)
        if [ "${waiting:-0}" -gt 0 ] 2>/dev/null; then
            return 0
        fi
    fi
    return 1
}

list_sysbox_containers() {
    docker ps -a --format '{{.Names}} {{.ID}}' | while read -r name short; do
        [ -n "$name" ] || continue
        is_sysbox_container "$name" && echo "$name"
    done
}

recover_one() {
    local name="$1"
    local cid runtime scope fuse_id home data_root compose_dir

    if ! docker inspect "$name" >/dev/null 2>&1; then
        err "container not found: $name"
        return 1
    fi
    if ! is_sysbox_container "$name"; then
        err "$name is not runtime=sysbox-runc"
        return 1
    fi

    cid=$(docker inspect -f '{{.Id}}' "$name")
    runtime=$(docker inspect -f '{{.HostConfig.Runtime}}' "$name")
    scope="/sys/fs/cgroup/system.slice/docker-${cid}.scope"
    home="/home/${name}"
    data_root="${home}/docker"
    compose_dir="${COMPOSE_ROOT}/${name}"

    log "recovering $name id=${cid:0:12} (D=$(d_state_count))"

    docker update --restart=no "$name" >/dev/null 2>&1 || true
    # Keep compose from racing us back up during wipe.
    if [ -f "${compose_dir}/docker-compose.yml" ]; then
        sed -i 's/restart: always/restart: "no"/; s/restart: "always"/restart: "no"/' \
            "${compose_dir}/docker-compose.yml" 2>/dev/null || true
    fi

    fuse_id=""
    if fuse_id=$(fuse_id_for_container "$cid"); then
        log "fuse connection id=$fuse_id"
        if [ -w "/sys/fs/fuse/connections/${fuse_id}/abort" ]; then
            echo 1 > "/sys/fs/fuse/connections/${fuse_id}/abort"
            log "aborted fuse connection $fuse_id"
            sleep 1
        fi
    else
        log "no sysboxfs fuse mount found for $name (continuing)"
    fi

    if [ -w "${scope}/cgroup.kill" ]; then
        echo 1 > "${scope}/cgroup.kill" || true
        log "wrote cgroup.kill"
        sleep 1
    fi

    if timeout 45 docker rm -f "$name" >/dev/null 2>&1; then
        log "docker rm -f ok"
    else
        log "docker rm -f failed — aborting all fuse connections and retrying"
        for abort in /sys/fs/fuse/connections/*/abort; do
            [ -w "$abort" ] || continue
            # Skip fusectl itself; only numeric connection dirs have abort.
            echo 1 > "$abort" || true
        done
        sleep 2
        if ! timeout 45 docker rm -f "$name" >/dev/null 2>&1; then
            err "still cannot remove $name — host reboot may be required"
            return 2
        fi
        log "docker rm -f ok after global fuse abort"
    fi

    if [ "$WIPE_INNER" -eq 1 ] && [ -d "$data_root" ]; then
        log "wiping inner docker data-root $data_root"
        rm -rf "$data_root"
        mkdir -p "$data_root"
        chmod 710 "$data_root"
    fi

    if [ -d "/var/lib/sysboxfs/${cid}" ]; then
        umount -l "/var/lib/sysboxfs/${cid}" 2>/dev/null || true
        rmdir "/var/lib/sysboxfs/${cid}" 2>/dev/null || true
    fi

    if [ "$RECREATE" -eq 1 ]; then
        if [ ! -f "${compose_dir}/docker-compose.yml" ]; then
            err "cannot recreate: missing ${compose_dir}/docker-compose.yml"
            return 1
        fi
        # Restore restart policy for normal operation.
        sed -i 's/restart: "no"/restart: always/; s/restart: no$/restart: always/' \
            "${compose_dir}/docker-compose.yml" 2>/dev/null || true
        log "recreating via docker compose up -d in $compose_dir"
        (cd "$compose_dir" && docker compose up -d)
        sleep 3
        if timeout 15 docker exec "$name" docker info >/dev/null 2>&1; then
            log "inner docker healthy after recreate"
        else
            err "recreated outer container but inner docker not responding yet"
            return 3
        fi
    fi

    log "done $name (D=$(d_state_count))"
    return 0
}

detect_stuck() {
    local name found=0
    log "scanning sysbox containers (host D=$(d_state_count))"
    while IFS= read -r name; do
        [ -n "$name" ] || continue
        if container_looks_stuck "$name"; then
            echo "STUCK $name"
            found=1
        else
            echo "OK    $name"
        fi
    done < <(list_sysbox_containers)
    return $found
}

if [ "$DETECT_ONLY" -eq 1 ]; then
    detect_stuck
    exit $?
fi

if [ -z "$USERNAME" ] && [ "$ALL_STUCK" -eq 0 ]; then
    err "pass --username=USER or --all-stuck (or --detect)"
    usage
fi

targets=()
if [ -n "$USERNAME" ]; then
    targets+=("$USERNAME")
else
    while IFS= read -r name; do
        [ -n "$name" ] || continue
        container_looks_stuck "$name" && targets+=("$name")
    done < <(list_sysbox_containers)
    if [ "${#targets[@]}" -eq 0 ]; then
        log "no stuck sysbox containers detected"
        exit 0
    fi
    log "stuck targets: ${targets[*]}"
fi

rc=0
for name in "${targets[@]}"; do
    recover_one "$name" || rc=$?
done
exit "$rc"
