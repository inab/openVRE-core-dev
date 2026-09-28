#!/bin/sh

set -e

echo "Fixing OpenBao data permissions..."

chown -R openbao:openbao /openbao/data

echo "Starting OpenBao..."

exec su-exec openbao bao server \
    -config=/openbao/config/openbao.hcl