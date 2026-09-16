#!/usr/bin/env bash

# set -x

LOGS_DIR="/opt/panelalpha/log/engine-updates"
mkdir -p "$LOGS_DIR"
LOCK_FILE="$LOGS_DIR/lock"

exec 200>"$LOCK_FILE" || {
    echo "Cannot open lock file $LOCK_FILE for writing." >&2
    exit 1
}
flock -n 200 || {
    echo "Another instance is already running."
    exit 1
}

strip_colors() {
    sed -r 's/\x1B\[[0-9;]*[mK]//g'
}

RUN_ID=$(date +%s)
RUN_DIR="$LOGS_DIR/$RUN_ID"

STDOUT_FILE="$RUN_DIR/stdout"
STDERR_FILE="$RUN_DIR/stderr"
EXIT_CODE_FILE="$RUN_DIR/exit_code"
PID_FILE="$RUN_DIR/pid"

ENGINE_VERSION='master'
PACKAGE_HOST='connect.panelalpha.com'
BACKGROUND=0
NO_SELF_UPDATE=0
ALL_ARGS=("$@")

while [[ $# -gt 0 ]]; do
    case "$1" in
    --version=*)
        ENGINE_VERSION="${1#*=}"
        shift
        ;;
    --version)
        ENGINE_VERSION="$2"
        shift 2
        ;;
    --package-host=*)
        PACKAGE_HOST="${1#*=}"
        shift
        ;;
    --package-host)
        PACKAGE_HOST="$2"
        shift 2
        ;;
    --background)
        BACKGROUND=1
        shift
        ;;
    --no-self-update)
        NO_SELF_UPDATE=1
        shift
        ;;
    --help | -h)
        echo "Usage: $0 [--version <ver>] [--host <host>] [--background] [--no-self-update]"
        exit 0
        ;;
    *)
        shift
        ;;
    esac
done

SELF_PATH="$(realpath "$0")"
SCRIPT_FILE="/opt/panelalpha/shared-hosting/scripts/int-updater.sh"

SELF_UPDATE_URL="https://${PACKAGE_HOST}/engine-updater.sh?version=${ENGINE_VERSION}"
SCRIPT_UPDATE_URL="https://${PACKAGE_HOST}/engine-int-updater.sh?version=${ENGINE_VERSION}"

cleanup() {
    echo "$1" >"$EXIT_CODE_FILE"
    echo "[INFO] Updater exited with code $1" >>"$STDOUT_FILE"
}

run_foreground() {
    trap 'cleanup $exit_code' EXIT INT TERM
    echo $$ >"$PID_FILE"

    bash "$SCRIPT_FILE" "$@" \
        > >(tee "$STDOUT_FILE") \
        2> >(tee "$STDERR_FILE" >&2)
    exit_code=$?

    strip_colors <"$STDOUT_FILE" >"$RUN_DIR/stdout.clean"
    strip_colors <"$STDERR_FILE" >"$RUN_DIR/stderr.clean"
}

run_background() {
    (
        trap 'cleanup $exit_code' EXIT INT TERM
        bash "$SCRIPT_FILE" "$@" >"$STDOUT_FILE" 2>"$STDERR_FILE"
        exit_code=$?

        strip_colors <"$STDOUT_FILE" >"$RUN_DIR/stdout.clean"
        strip_colors <"$STDERR_FILE" >"$RUN_DIR/stderr.clean"
    ) &
    echo $! >"$PID_FILE"
    disown
    echo "[INFO] Updater started in background with PID $!"
    echo "[INFO] Updater started in background with PID $!" >>"$STDOUT_FILE"
}

try_update_self() {
    local tmp_self tmp_internal

    tmp_self="$(mktemp)"
    tmp_internal="$(mktemp)"

    echo "[INFO] Checking for updater script updates..."

    if curl -fsSL "$SELF_UPDATE_URL" -o "$tmp_self" &&
        curl -fsSL "$SCRIPT_UPDATE_URL" -o "$tmp_internal"; then

        echo "[INFO] Downloaded latest scripts."

        # Optionally check for diff before overwriting
        if ! cmp -s "$SELF_PATH" "$tmp_self"; then
            echo "[INFO] Updating wrapper script..."
            cp "$tmp_self" "$SELF_PATH"
            chmod +x "$SELF_PATH"
            UPDATED_SELF=1
        fi

        if ! cmp -s "$SCRIPT_FILE" "$tmp_internal"; then
            echo "[INFO] Updating internal script..."
            cp "$tmp_internal" "$SCRIPT_FILE"
            chmod +x "$SCRIPT_FILE"
        fi

        rm -f "$tmp_self" "$tmp_internal"

        if [[ "$UPDATED_SELF" == "1" ]]; then
            echo "[INFO] Re-executing updated wrapper script..."
            exec "$SELF_PATH" "$@" || {
                echo "[ERROR] Re-execution failed." >&2
                exit 1
            }
        fi
    else
        echo "[ERROR] Failed to fetch latest scripts from '$SELF_UPDATE_URL' '$SCRIPT_UPDATE_URL'." >&2
        rm -f "$tmp_self" "$tmp_internal"
        exit 1
    fi
}

if [[ "$NO_SELF_UPDATE" -eq 0 ]]; then
    try_update_self "${ALL_ARGS[@]}"
fi

mkdir -p $RUN_DIR
ln -sfn $RUN_DIR "$LOGS_DIR/latest"

if [[ "$BACKGROUND" -eq 1 ]]; then
    run_background "${ALL_ARGS[@]}"
else
    run_foreground "${ALL_ARGS[@]}"
fi
