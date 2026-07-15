#!/bin/sh
set -e

cd /workspace/react-frontend

case "${OPENVRE_ENV:-dev}" in
  prod|production)
    echo "Installing React frontend dependencies..."
    npm ci
    echo "Building React islands for production..."
    npm run build
    echo "React islands ready (production mode)."
    exit 0
    ;;
  *)
    exec sh /usr/local/bin/docker-dev.sh
    ;;
esac
