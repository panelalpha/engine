#!/bin/bash

ACTION="$1"
TARGET="$2"
SCRIPTS_DIR="/entrypoint.d"
INIT_DIR="/entrypoint-init.d"
PID_DIR="/var/run/entrypoint"

handle_script() {
  local SCRIPT_NAME="$1"
  local ORPHANED="$2"
  local SCRIPT_PATH="${SCRIPTS_DIR}/${SCRIPT_NAME}.sh"
  local PID_FILE="${PID_DIR}/${SCRIPT_NAME}.pid"
  mkdir -p $PID_DIR

  start() {
    if [[ "$ORPHANED" ]]; then
      echo "$SCRIPT_NAME is orphaned"
      return
    fi
    if [[ -f "$PID_FILE" ]] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null; then
      echo "$SCRIPT_NAME is already running (PID $(cat "$PID_FILE"))"
      return
    fi
    bash "$SCRIPT_PATH" &
    echo $! > "$PID_FILE"
    echo "Started $SCRIPT_NAME (PID $(cat "$PID_FILE"))"
  }

  stop() {
    local timeout=10
    local interval=1
    if [[ -f "$PID_FILE" ]]; then
      PID=$(cat "$PID_FILE")
      if kill -0 "$PID" 2>/dev/null; then
        kill "$PID" || true
        local waited=0
        while kill -0 "$PID" 2>/dev/null && (( waited < timeout )); do
          sleep "$interval"
          waited=$((waited + interval))
        done
        if kill -0 "$PID" 2>/dev/null; then
          echo "Process did not exit after ${timeout}s; sending SIGKILL"
          kill -9 "$PID" || true
          sleep 0.1
        else
          echo "Stopped $SCRIPT_NAME (PID $PID)"
        fi
      else
        echo "Process for $SCRIPT_NAME not running; removing stale PID file"
      fi
      rm -f "$PID_FILE"
    else
      echo "$SCRIPT_NAME is not running"
    fi
  }

  sync() {
    if [[ "$ORPHANED" ]]; then
      stop
    else
      start
    fi
  }

  status() {
    if [[ -f "$PID_FILE" ]] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null; then
      if [[ "$ORPHANED" ]]; then
        echo "(orphaned) $SCRIPT_NAME is running (PID $(cat "$PID_FILE"))"
      else
        echo "$SCRIPT_NAME is running (PID $(cat "$PID_FILE"))"
      fi
    else
      if [[ "$ORPHANED" ]]; then
        echo "(orphaned) $SCRIPT_NAME is not running"
      else
        echo "$SCRIPT_NAME is not running"
      fi
    fi
  }

  restart() {
    stop
    start
  }

  case "$ACTION" in
    start) start ;;
    stop) stop ;;
    status) status ;;
    restart) restart ;;
    sync) sync ;;
    *) echo "Unknown action: $ACTION" ;;
  esac
}

handle_init_script() {
  local SCRIPT_NAME="$1"
  local SCRIPT_PATH="${INIT_DIR}/${SCRIPT_NAME}.sh"

  if [[ -f "$SCRIPT_PATH" ]]; then
    echo "Running init script: $SCRIPT_NAME"
    bash "$SCRIPT_PATH"
  else
    echo "Init script not found: $SCRIPT_PATH"
  fi
}

handle_all() {
  local DIR="$1"
  local HANDLER="$2"

  declare -A scripts
  for script in "$DIR"/*.sh; do
    [[ -f "$script" ]] || continue
    local SCRIPT_BASENAME
    SCRIPT_BASENAME=$(basename "$script" .sh)
    scripts["$SCRIPT_BASENAME"]=1
    "$HANDLER" "$SCRIPT_BASENAME"
  done
  for proc in "$PID_DIR"/*.pid; do
    [[ -f "$proc" ]] || continue
    local SCRIPT_BASENAME
    SCRIPT_BASENAME=$(basename "$proc" .pid)
    [[ -n ${scripts["$SCRIPT_BASENAME"]} ]] && continue
    "$HANDLER" "$SCRIPT_BASENAME" 1
  done
}

case "$ACTION" in
  start|stop|restart|sync|status)
    if [[ "$TARGET" == "--all" ]]; then
      handle_all "$SCRIPTS_DIR" handle_script
    elif [[ -n "$TARGET" ]]; then
      handle_script "$TARGET"
    else
      echo "Usage: $0 $ACTION <script_name|--all>"
      exit 1
    fi
    ;;
  init|run-init-script)
    if [[ "$TARGET" == "--all" ]]; then
      handle_all "$INIT_DIR" handle_init_script
    elif [[ -n "$TARGET" ]]; then
      handle_init_script "$TARGET"
    else
      echo "Usage: $0 $ACTION <script_name|--all>"
      exit 1
    fi
    ;;
  *)
    echo "Usage: $0 <start|stop|restart|sync|status|init> <script_name|--all>"
    exit 1
    ;;
esac
