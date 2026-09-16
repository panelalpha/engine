#!/usr/bin/env bash
set -euo pipefail

DAEMON_JSON="/etc/docker/daemon.json"
TMP_JSON="$(mktemp)"

show_usage() {
  echo "Usage:"
  echo "  $0 --show                 Show current DNS entries in Docker daemon.json"
  echo "  $0 --add IP1 [IP2 ...]    Add one or more DNS IPs (idempotent)"
  echo "  $0 --remove IP1 [IP2 ...] Remove one or more DNS IPs (idempotent)"
  exit 0
}

if ! command -v jq >/dev/null 2>&1; then
  echo "Error: 'jq' is required but not installed." >&2
  exit 1
fi

if [ ! -f "$DAEMON_JSON" ] || [ ! -s "$DAEMON_JSON" ]; then
  echo "{}" | tee "$DAEMON_JSON" >/dev/null
fi

if ! jq empty "$DAEMON_JSON" 2>/dev/null; then
  echo "[WARN] Invalid JSON detected in $DAEMON_JSON, exiting."
  exit 1;
fi

jq '.dns = (.dns // [])' "$DAEMON_JSON" | tee "$TMP_JSON" >/dev/null
mv "$TMP_JSON" "$DAEMON_JSON"

ACTION="${1:-}"

case "$ACTION" in
  --show)
    echo "Current Docker DNS entries:"
    jq -r '.dns // [] | .[]' "$DAEMON_JSON"
    ;;

  --add)
    shift
    [ $# -eq 0 ] && show_usage
    CURRENT_DNS=($(jq -r '.dns // [] | .[]' "$DAEMON_JSON"))
    UPDATED_DNS=("${CURRENT_DNS[@]}")
    for ip in "$@"; do
      if [[ ! " ${CURRENT_DNS[*]} " =~ " ${ip} " ]]; then
        UPDATED_DNS+=("$ip")
      fi
    done
    jq --argjson arr "$(printf '%s\n' "${UPDATED_DNS[@]}" | jq -R . | jq -s .)" \
      '.dns = $arr' "$DAEMON_JSON" | tee "$TMP_JSON" >/dev/null
    mv "$TMP_JSON" "$DAEMON_JSON"
    echo "Added DNS IP(s): $*"
    echo "Run 'service docker reload' or 'service docker restart' to apply changes."
    ;;

  --remove)
    shift
    [ $# -eq 0 ] && show_usage
    REMOVE_SET=("$@")
    jq --argjson rm "$(printf '%s\n' "${REMOVE_SET[@]}" | jq -R . | jq -s .)" '
      .dns = ((.dns // []) - $rm)
    ' "$DAEMON_JSON" | tee "$TMP_JSON" >/dev/null
    mv "$TMP_JSON" "$DAEMON_JSON"
    echo "Removed DNS IP(s): $*"
    echo "Run 'service docker restart' to apply changes."
    ;;
  --exists)
    shift
    [ $# -eq 0 ] && show_usage
    CURRENT_DNS=($(jq -r '.dns // [] | .[]' "$DAEMON_JSON"))
    MISSING=0
    for ip in "$@"; do
      if [[ ! " ${CURRENT_DNS[*]} " =~ " ${ip} " ]]; then
        MISSING=1
      fi
    done
    if [ $MISSING -eq 0 ]; then
      exit 0
    else
      exit 1
    fi
    ;;
  *)
    show_usage
    ;;
esac