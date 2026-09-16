# ActivityWatch: build the web UI, install the Python server, serve both.
#
# Written for the recipe rather than for the repository: the bundle has no
# Dockerfile of its own, and its root is not an installable project (see
# ../panelalpha.yaml). The two stages mirror the two halves the project's own
# `make build` performs for the server — `aw-server` and its embedded web UI.
#
# Stage 1 — aw-webui. Its vue.config.js calls `git rev-parse --short HEAD` at
# build time to stamp the bundle, so the builder needs git, and the submodule
# arrives as a gitlink with no repository of its own: one is seeded here so
# that call answers something. `.git` is removed first because the gitlink
# points at the parent repository's module store, which is not in the build
# context and would make `git` abort with "not a git repository".
FROM node:20-bookworm-slim AS webui
RUN apt-get update -qq \
 && apt-get install -y -qq --no-install-recommends git \
 && rm -rf /var/lib/apt/lists/*
WORKDIR /webui
COPY aw-server/aw-webui/package.json aw-server/aw-webui/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY aw-server/aw-webui/ ./
RUN rm -rf .git \
 && git init -q \
 && git config user.email build@panelalpha.local \
 && git config user.name panelalpha \
 && git commit -q --allow-empty -m build \
 && npm run build

# Stage 2 — the server. `pip install ./aw-server` resolves its own pyproject
# (flask, flask-restx, aw-core, aw-client), and the built SPA is copied into
# the package's `static/` directory, which is where aw_server.server serves
# the interface from. --host 0.0.0.0 because the default listener is
# 127.0.0.1, which the reverse proxy could never reach.
FROM python:3.12-slim
WORKDIR /app
COPY aw-server/ /app/aw-server/
RUN pip install --no-cache-dir ./aw-server
COPY --from=webui /webui/dist/ /usr/local/lib/python3.12/site-packages/aw_server/static/
EXPOSE 5600
CMD ["aw-server", "--host", "0.0.0.0", "--port", "5600"]
