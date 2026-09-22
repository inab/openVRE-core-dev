#!/bin/sh

set -e

echo "Fixing OpenBao data permissions..."

chown -R openbao:openbao /openbao/data

echo "Starting OpenBao..."
su-exec openbao bao server \
    -config=/openbao/config/openbao.hcl &
BAO_PID=$!

echo "Waiting for OpenBao..."

until bao status >/dev/null 2>&1; do
    sleep 1
done

echo "OpenBao is ready."

#/bao-config.sh

wait "$BAO_PID"