#!/usr/bin/env bash
# Mirror Docker parent cgroup mem/cpu limits into sysbox init.scope so
# `docker stats` reports the real LIMIT instead of host RAM.
#
# Sysbox puts processes under docker-<id>.scope/init.scope where memory.max
# stays "max"; the parent scope still enforces the limit, but docker stats
# reads the child. See nestybox/sysbox#303 / #714.
#
# Manual helper — run as root on the engine host when needed:
#   bash engine/scripts/fix-sysbox-cgroups.sh

set -euo pipefail

while read -r name cid runtime; do
  [ "$runtime" = "sysbox-runc" ] || continue
  base="/sys/fs/cgroup/system.slice/docker-${cid}.scope"
  [ -d "$base/init.scope" ] || continue
  mem=$(cat "$base/memory.max" 2>/dev/null || echo max)
  cpu=$(cat "$base/cpu.max" 2>/dev/null || true)
  if [ "$mem" != "max" ]; then
    echo "$mem" > "$base/init.scope/memory.max" 2>/dev/null || true
    echo "$mem" > "$base/init.scope/memory.swap.max" 2>/dev/null || true
    [ -d "$base/docker" ] && echo "$mem" > "$base/docker/memory.max" 2>/dev/null || true
  fi
  if [ -n "$cpu" ] && [ "$cpu" != "max 100000" ]; then
    echo "$cpu" > "$base/init.scope/cpu.max" 2>/dev/null || true
    [ -d "$base/docker" ] && echo "$cpu" > "$base/docker/cpu.max" 2>/dev/null || true
  fi
done < <(docker ps -q | while read -r short; do
  docker inspect -f "{{.Name}} {{.Id}} {{.HostConfig.Runtime}}" "$short" 2>/dev/null
done)
