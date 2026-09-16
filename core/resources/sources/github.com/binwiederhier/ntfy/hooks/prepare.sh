#!/bin/bash
set -e
cd ~/project

# ntfy's own Dockerfile-build is stale. The server imports three packages that
# the file never copies into the builder stage, so the Go layer stops with:
#
#     cmd/serve.go:21:2: no required module provides package heckel.io/ntfy/v2/ban
#     server/server.go:40:2: no required module provides package heckel.io/ntfy/v2/metrics
#     server/server.go:43:2: no required module provides package heckel.io/ntfy/v2/twilio
#
# and the deploy fails at `make cli-linux-server`, before an image exists. The
# directories were added to the repository after the file's ADD list was last
# extended — ban/ in July 2026, metrics/ and twilio/ earlier the same month —
# which is why only this part of the file is wrong: docs, web and Go are
# sequenced correctly, and the final stage is the default last stage, so no
# build target is involved.
#
# Add each missing directory to the builder, immediately above the layer that
# compiles it. The patch is idempotent and self-checking: a directory the file
# already copies is left alone, a file whose build layer has moved keeps its
# own ADD lines rather than being rewritten, and if a directory still is not
# copied afterwards the hook fails here with the name it could not place,
# rather than leaving the deploy to die in the Go layer with a message about
# the module instead of the file.
for dir in ban metrics twilio; do
    if grep -qE "^[[:space:]]*ADD[[:space:]]+\./${dir}([[:space:]]|$)" Dockerfile-build; then
        continue
    fi
    sed -i "\|cli-linux-server|i ADD ./${dir} ./${dir}" Dockerfile-build
done

for dir in ban metrics twilio; do
    grep -qE "^[[:space:]]*ADD[[:space:]]+\./${dir}([[:space:]]|$)" Dockerfile-build \
        || { echo "Dockerfile-build still does not copy ${dir}/" >&2; exit 1; }
done
