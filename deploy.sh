#!/usr/bin/env bash
# deploy.sh - PROD deploy: OpenBao -> secret files on a tmpfs -> docker compose.
#
#   sudo ./deploy.sh [--token-prompt] [extra args for `docker compose up -d`]
#   e.g. sudo ./deploy.sh --force-recreate front_end   (after a rotation)
#
# 1. reads non-secret settings from .env (the file is parsed, not sourced)
# 2. ensures the Docker network exists; moves an old bao-server container
#    (from docker-compose-prod.yml) to its own Compose project, after asking
# 3. starts OpenBao (docker-compose-openbao.yml); a no-op if already running
# 4. waits for OpenBao and unseals it if needed (docker exec ... unseal)
# 5. gets a token: $BAO_TOKEN if valid, else AppRole login with the files in
#    $BAO_APPROLE_DIR (made by openbao/bao-config-prod.sh --install-approle),
#    else (or with --token-prompt) a hidden prompt (break-glass). The token is
#    revoked (`bao token revoke -self`) as soon as the files are written.
# 6. writes every "prod" row of secrets/manifest to /run/openvre/secrets/<name>
#    (tmpfs, never on disk), mode 0400, owned by the reading container's UID
# 7. SECRETS_DIR=/run/openvre/secrets docker compose -f docker-compose-prod.yml
#    --profile local_auth up -d "$@"
#
# Needs root (tmpfs mount, file owners), docker, the host `bao` CLI and jq.
# Secret values only travel through pipes and files, never argv or the terminal.
# /run is cleared on reboot: run this script again after every reboot.
set -euo pipefail

usage() { sed -n '2,5p' "$0" | sed 's/^# \{0,1\}//' >&2; exit 2; }
TOKEN_PROMPT=0
while [ $# -gt 0 ]; do
    case "$1" in
        --token-prompt) TOKEN_PROMPT=1; shift ;;
        -h|--help) usage ;;
        --) shift; break ;;
        *) break ;;
    esac
done

REPO_DIR=$(cd "$(dirname "$0")" && pwd)
cd "$REPO_DIR"
ENV_FILE=.env
MANIFEST=secrets/manifest
SECRETS_DIR=/run/openvre/secrets
BAO_PROJECT=openvre-openbao
BAO_CONTAINER=bao-server

die() { echo "ERROR: $*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "run as root: it mounts a tmpfs and sets file owners"
for cmd in docker bao jq install mountpoint; do
    command -v "$cmd" >/dev/null || die "$cmd is required"
done
[ -r "$ENV_FILE" ] || die "$ENV_FILE not found (copy .env.sample-prod)"
[ -r "$MANIFEST" ] || die "$MANIFEST not found"

# --- 1. settings from .env ------------------------------------------------
env_get() { # KEY [default]: last KEY=value line; strips " # comment" and quotes
    local v
    v=$(grep -E "^$1=" "$ENV_FILE" | tail -n 1 || true)
    v=${v#*=}
    case "$v" in
        \"*\") v=${v#\"}; v=${v%\"} ;;
        \'*\') v=${v#\'}; v=${v%\'} ;;
        *) v=$(printf '%s' "$v" | sed -e 's/[[:space:]]\{1,\}#.*$//' -e 's/[[:space:]]*$//') ;;
    esac
    printf '%s' "${v:-${2:-}}"
}
NETWORK_NAME=$(env_get NETWORK_NAME)
BAO_DATA_VOLUME=$(env_get BAO_DATA_VOLUME)
BAO_ADDR=$(env_get BAO_ADDR)
BAO_SSL_CAFILE=$(env_get BAO_SSL_CAFILE)
APPROLE_DIR=$(env_get BAO_APPROLE_DIR /etc/openvre/approle)
for v in NETWORK_NAME BAO_DATA_VOLUME BAO_ADDR; do
    [ -n "${!v}" ] || die "$v is not set in $ENV_FILE"
done
export BAO_ADDR
if [ -n "$BAO_SSL_CAFILE" ]; then
    case "$BAO_SSL_CAFILE" in /*) ;; *) BAO_SSL_CAFILE="$REPO_DIR/${BAO_SSL_CAFILE#./}" ;; esac
    export BAO_CACERT="$BAO_SSL_CAFILE"
fi

ROWS=$(awk '!/^[[:space:]]*#/ && NF == 6 && index("," $3 ",", ",prod,") { print }' "$MANIFEST")
[ -n "$ROWS" ] || die "no prod rows in $MANIFEST"
# MIGRATION-ONLY (remove with migrate-secrets.sh): warn if legacy secrets are
# still in .env.
while read -r _name _uid _envs _kv _field from; do
    for var in ${from//,/ }; do
        var=${var#@}
        if grep -qE "^${var}=." "$ENV_FILE"; then
            echo "WARNING: $ENV_FILE still defines $var; delete it once migrated." >&2
        fi
    done
done <<< "$ROWS"

# --- 2. network and one-time move of the old bao container --------------------
if ! docker network inspect "$NETWORK_NAME" >/dev/null 2>&1; then
    docker network create --subnet 172.21.0.0/24 "$NETWORK_NAME" >/dev/null
    echo "Created network $NETWORK_NAME"
fi
docker volume inspect "$BAO_DATA_VOLUME" >/dev/null 2>&1 ||
    die "volume $BAO_DATA_VOLUME does not exist; refusing to start an empty OpenBao"
# MIGRATION-ONLY (remove with migrate-secrets.sh): move the old bao-server.
if docker container inspect "$BAO_CONTAINER" >/dev/null 2>&1; then
    project=$(docker container inspect -f '{{index .Config.Labels "com.docker.compose.project"}}' "$BAO_CONTAINER")
    if [ "$project" != "$BAO_PROJECT" ]; then
        vol=$(docker container inspect "$BAO_CONTAINER" \
            -f '{{range .Mounts}}{{if eq .Destination "/openbao/data"}}{{.Name}}{{end}}{{end}}')
        echo "$BAO_CONTAINER belongs to Compose project '$project' and uses volume '$vol'."
        [ "$vol" = "$BAO_DATA_VOLUME" ] ||
            die "set BAO_DATA_VOLUME=$vol in $ENV_FILE first (it is $BAO_DATA_VOLUME)"
        read -rp "Remove the container and recreate it in project $BAO_PROJECT? The volume is kept. [y/N] " answer
        [ "$answer" = "y" ] || die "aborted"
        docker rm -f "$BAO_CONTAINER" >/dev/null
    fi
fi

# --- 3. OpenBao (own project, never restarted by app deploys) --------------
docker compose -p "$BAO_PROJECT" -f docker-compose-openbao.yml up -d

# --- 4. wait and unseal ------------------------------------------------------
bao_rc() { local rc=0; bao status >/dev/null 2>&1 || rc=$?; echo "$rc"; }
rc=1
for _ in $(seq 1 30); do
    rc=$(bao_rc)
    [ "$rc" -ne 1 ] && break
    sleep 2
done
[ "$rc" -ne 1 ] || die "OpenBao is not reachable at $BAO_ADDR"
if [ "$(bao status -format=json 2>/dev/null | jq -r '.initialized')" != "true" ]; then
    die "OpenBao is not initialised; see openbao/README.md"
fi
while [ "$(bao_rc)" -eq 2 ]; do
    echo "OpenBao is sealed. Enter one unseal key share (input is hidden):"
    docker exec -it "$BAO_CONTAINER" bao operator unseal || true
done

# --- 5. token ------------------------------------------------------------------
export BAO_TOKEN=${BAO_TOKEN:-}
revoke_token() {
    if [ -n "${BAO_TOKEN:-}" ]; then
        bao token revoke -self >/dev/null 2>&1 || true
    fi
    BAO_TOKEN=""
}
cleanup() {
    revoke_token
    rm -f "$SECRETS_DIR/.tmp" 2>/dev/null || true
    stty echo 2>/dev/null || true
}
trap cleanup EXIT
trap 'exit 130' INT TERM HUP
token_ok() { [ -n "$BAO_TOKEN" ] && bao token lookup >/dev/null 2>&1; }

if [ "$TOKEN_PROMPT" -eq 0 ] && ! token_ok; then
    BAO_TOKEN=""
    if [ -r "$APPROLE_DIR/role_id" ] && [ -r "$APPROLE_DIR/secret_id" ]; then
        echo "Logging in to OpenBao with AppRole ($APPROLE_DIR)..."
        # "=@file": bao reads the values from the files, not from argv.
        BAO_TOKEN=$(bao write -field=token auth/approle/login \
            role_id=@"$APPROLE_DIR/role_id" secret_id=@"$APPROLE_DIR/secret_id") || BAO_TOKEN=""
    else
        echo "No AppRole credentials in $APPROLE_DIR." >&2
    fi
fi
if ! token_ok; then
    echo "Break-glass: paste an OpenBao token with the openvre-deploy policy."
    read -rsp "OpenBao token: " BAO_TOKEN; echo
    token_ok || die "token rejected by OpenBao"
fi

# --- 6. secret files on tmpfs ---------------------------------------------------
mkdir -p "$SECRETS_DIR"
if ! mountpoint -q "$SECRETS_DIR"; then
    mount -t tmpfs -o size=1m,mode=0700,nosuid,nodev,noexec tmpfs "$SECRETS_DIR"
fi
chmod 0700 "$SECRETS_DIR"
find "$SECRETS_DIR" -mindepth 1 -delete
umask 077
count=0
while read -r name uid _envs kv field _from; do
    tmp="$SECRETS_DIR/.tmp"
    bao kv get -mount=infra -format=json "openvre/$kv" |
        jq -j --arg f "$field" '.data.data[$f] // error("missing field")' > "$tmp" ||
        die "cannot read field '$field' of infra/openvre/$kv"
    [ -s "$tmp" ] || die "infra/openvre/$kv field '$field' is empty"
    install -m 0400 -o "$uid" -g "$uid" "$tmp" "$SECRETS_DIR/$name"
    rm -f "$tmp"
    count=$((count + 1))
done <<< "$ROWS"
echo "Wrote $count secret files to $SECRETS_DIR"
revoke_token

# --- 7. app stack -----------------------------------------------------------------
SECRETS_DIR="$SECRETS_DIR" docker compose -f docker-compose-prod.yml --profile local_auth up -d "$@"
