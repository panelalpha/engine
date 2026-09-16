#!/bin/bash
# Require 8GB of free disk space
REQUIRED_SPACE=$((8 * 1024 * 1024))
AVAILABLE_SPACE=$(df -k . | awk 'NR==2 {print $4}')
if [ "$AVAILABLE_SPACE" -lt "$REQUIRED_SPACE" ]; then
    echo "Error: Less than 8GB of disk space available."
    exit 1
fi

# ActivePieces (app + postgres + redis + workers) is memory-heavy.
# Without a hard host/cgroup cap it can OOM-reboot the engine host and
# leave sysbox DinD containers wedged (uninterruptible fuse_flush).
REQUIRED_MEM_KB=$((2 * 1024 * 1024)) # 4 GiB total RAM
TOTAL_MEM_KB=$(awk '/^MemTotal:/ {print $2}' /proc/meminfo)
if [ -z "$TOTAL_MEM_KB" ] || [ "$TOTAL_MEM_KB" -lt "$REQUIRED_MEM_KB" ]; then
    echo "Error: ActivePieces requires at least 2GB of system RAM (found ${TOTAL_MEM_KB:-unknown} kB)."
    echo "Deploying it on smaller hosts can OOM the whole engine and corrupt sysbox DinD state."
    exit 1
fi

# Prefer available memory (MemAvailable) so a busy host also fails closed.
REQUIRED_AVAIL_KB=$((2 * 1024 * 1024)) # 2 GiB available
AVAIL_MEM_KB=$(awk '/^MemAvailable:/ {print $2}' /proc/meminfo)
if [ -n "$AVAIL_MEM_KB" ] && [ "$AVAIL_MEM_KB" -lt "$REQUIRED_AVAIL_KB" ]; then
    echo "Error: ActivePieces requires at least 2GB of available RAM (found ${AVAIL_MEM_KB} kB)."
    exit 1
fi
