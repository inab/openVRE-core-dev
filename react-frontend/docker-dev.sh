#!/bin/sh
set -e

cd /workspace/react-frontend

deps_fingerprint() {
  stat -c '%Y' package.json package-lock.json 2>/dev/null | tr '\n' '-'
}

install_deps() {
  echo "Installing React frontend dependencies..."
  npm ci
}

build_static_fallback() {
  echo "Building static fallback bundles..."
  npm run build
}

start_vite() {
  echo "Starting Vite dev server with hot reload..."
  npx vite &
  VITE_PID=$!
}

stop_vite() {
  if [ -n "$VITE_PID" ] && kill -0 "$VITE_PID" 2>/dev/null; then
    echo "Stopping Vite dev server..."
    kill "$VITE_PID" 2>/dev/null || true
    wait "$VITE_PID" 2>/dev/null || true
  fi
  VITE_PID=
}

trap 'stop_vite; exit 0' INT TERM

install_deps
build_static_fallback
start_vite

LAST_FP=$(deps_fingerprint)

while true; do
  sleep 2
  CUR_FP=$(deps_fingerprint)
  if [ "$CUR_FP" = "$LAST_FP" ]; then
    continue
  fi

  # Wait for npm install to finish writing package-lock.json
  sleep 1
  CUR_FP=$(deps_fingerprint)
  LAST_FP=$CUR_FP

  echo "Dependency manifest changed — syncing and restarting Vite..."
  stop_vite
  install_deps
  start_vite
done
