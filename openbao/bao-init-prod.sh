#!/bin/sh

set -eu

echo "======================================"
echo "OpenBao production initialization"
echo "======================================"

echo "Checking OpenBao status..."

if bao operator init -status >/dev/null 2>&1; then
    echo "OpenBao is already initialized."
    exit 0
fi

echo "OpenBao is not initialized."
echo ""
echo "Initializing with Shamir secret sharing."
echo ""
echo "IMPORTANT:"
echo "  - The generated unseal keys must be stored securely."
echo "  - The root token must be stored securely."
echo "  - Do NOT commit them to Git."
echo "  - Do NOT store them in docker-compose.yml."
echo ""

bao operator init \
    -key-shares=5 \
    -key-threshold=3