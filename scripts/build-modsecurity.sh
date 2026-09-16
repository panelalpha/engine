#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 2 || $1 != "--version" ]]; then
    echo "Usage: $0 --version <modsecurity_version>"
    exit 1
fi
MODSEC_VERSION="$2"

WORKDIR="$(pwd)/build/modsecurity-${MODSEC_VERSION}"
mkdir -p "$WORKDIR"

echo "Building ModSecurity $MODSEC_VERSION"
echo "Artifacts will be in $WORKDIR"

docker run --rm \
    -e MODSEC_VERSION="$MODSEC_VERSION" \
    -v "$WORKDIR":/out \
    debian:11-slim bash -c '
        set -euo pipefail
        export DEBIAN_FRONTEND=noninteractive

        apt-get update
        apt-get install -y \
            git g++ build-essential automake autoconf libtool pkg-config \
            libpcre2-dev libpcre3-dev libxml2-dev libyajl-dev libgeoip-dev \
            liblmdb-dev wget curl unzip ca-certificates cmake zlib1g-dev

        cd /tmp
        git clone --depth=1 -b "v${MODSEC_VERSION}" --recurse-submodules --shallow-submodules https://github.com/owasp-modsecurity/ModSecurity
        cd ModSecurity
        ./build.sh
        ./configure
        make -j"$(nproc)"
        make install

        mkdir -p /out/lib
        strip --strip-unneeded "/usr/local/modsecurity/lib/libmodsecurity.so.${MODSEC_VERSION}"
        cp /usr/local/modsecurity/lib/libmodsecurity.so.${MODSEC_VERSION} /out/lib/
        cp -r /usr/local/modsecurity/include /out/include

        mkdir -p /out/deps
        ldd "/usr/local/modsecurity/lib/libmodsecurity.so.${MODSEC_VERSION}" | while read -r line; do
            DEPENDENCY=$(echo "$line" | awk "{print \$3}")
            if [ -f "$DEPENDENCY" ]; then
                cp "$DEPENDENCY" /out/deps/
            fi
        done
    '

echo "Build finished!"
