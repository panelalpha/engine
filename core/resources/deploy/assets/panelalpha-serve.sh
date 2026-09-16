#!/bin/sh
# Serve the mounted application. Baked into the shared PHP base image.
#
# This exists so that a platform manifest does not have to spell out a shell
# incantation to be served. Every one of the fourteen shipped PHP manifests
# used to carry its own copy of
#
#   { PA_DOCROOT=/app/public; export PA_DOCROOT; exec apache2-foreground; }
#
# differing only in the directory. That is not a command a recipe should have
# to know: the recipe knows *where the document root is*, and how to hand a
# document root to Apache is the image's business. So a manifest declares
# `docroot:` and the engine passes it as PA_DOCROOT; this script is what turns
# that into a running server.
set -e

if [ -z "${PA_DOCROOT:-}" ]; then
    # No declared document root. Almost every PHP application either serves
    # from public/ or from its own root, and which one is a fact about the
    # directory rather than a decision -- so look.
    if [ -d /app/public ]; then
        PA_DOCROOT=/app/public
    else
        PA_DOCROOT=/app
    fi
fi

# A document root that is not there produces `AH00526: Syntax error … DocumentRoot
# must be a directory`, which says nothing about which directory or who asked
# for it. Say it plainly instead, and fall back rather than refusing to boot:
# an application whose asset build has not created public/ yet is better served
# from its root than not at all.
if [ ! -d "$PA_DOCROOT" ]; then
    echo "panelalpha: document root $PA_DOCROOT does not exist; serving /app instead" >&2
    PA_DOCROOT=/app
fi

export PA_DOCROOT
exec apache2-foreground
