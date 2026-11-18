#!/usr/bin/env bash
# migrate-secrets.sh - one-off PROD migration of secrets from .env to OpenBao.
# Temporary: delete it (with MIGRATION.md and the MIGRATION-ONLY parts of
# deploy.sh) once production is migrated.
#
#   ./migrate-secrets.sh [path/to/old.env]      (default: .env)
#
# For every "prod" row of secrets/manifest it takes the value of the legacy
# variable in the old .env ("@VAR": the content of the file VAR points to) and
# writes it to infra/openvre/<kv_path> field <field>. When a row lists several
# variables (KEYCLOAK_DB_PASSWORD,POSTGRES_PASSWORD) they must be equal, or
# nothing is written.
#
# - the .env is parsed, not sourced; values only travel through files in a
#   private tmpfs directory and pipes (jq --rawfile, `bao kv put ... -`),
#   never through argv or the terminal
# - `-cas=0` refuses to overwrite a path that already exists in OpenBao
# - each value is read back and compared by SHA-256
# Needs: host `bao` CLI, jq, sha256sum, BAO_ADDR (+ BAO_CACERT), and a token
# with write access to infra/openvre/* (admin token or openvre-secrets-admin
# policy; $BAO_TOKEN, or a hidden prompt).
set -euo pipefail

REPO_DIR=$(cd "$(dirname "$0")" && pwd)
OLD_ENV=${1:-$REPO_DIR/.env}
MANIFEST="$REPO_DIR/secrets/manifest"

die() { echo "ERROR: $*" >&2; exit 1; }
for cmd in bao jq sha256sum; do
    command -v "$cmd" >/dev/null || die "$cmd is required"
done
[ -r "$OLD_ENV" ] || die "cannot read $OLD_ENV"
: "${BAO_ADDR:?Set BAO_ADDR (and BAO_CACERT if the CA is private)}"

# Private scratch space; prefer RAM-backed /dev/shm so values never hit disk.
WORK=$(mktemp -d "$( [ -d /dev/shm ] && echo /dev/shm || echo "${TMPDIR:-/tmp}")/openvre-migrate.XXXXXX")
chmod 0700 "$WORK"
trap 'rm -rf -- "$WORK"; unset BAO_TOKEN; stty echo 2>/dev/null || true' EXIT
trap 'exit 130' INT TERM HUP
umask 077

# Write the value of VAR from the old .env to file $2, compose-style:
# 'quoted' or "quoted" values are taken as is; unquoted values end at " #".
env_value_to_file() { # VAR out_file
    local line value
    line=$(grep -E "^$1=" "$OLD_ENV" | tail -n 1) || return 1
    value=${line#*=}
    case "$value" in
        \"*\") value=${value#\"}; value=${value%\"} ;;
        \'*\') value=${value#\'}; value=${value%\'} ;;
        *) value=$(printf '%s' "$value" | sed -e 's/[[:space:]]\{1,\}#.*$//' -e 's/[[:space:]]*$//') ;;
    esac
    [ -n "$value" ] || return 1
    case "$value" in *'$'*) echo "WARNING: $1 contains '\$'; check it was not interpolated by compose." >&2 ;; esac
    printf '%s' "$value" > "$2"
}

ROWS=$(awk '!/^[[:space:]]*#/ && NF == 6 && index("," $3 ",", ",prod,") { print }' "$MANIFEST")
[ -n "$ROWS" ] || die "no prod rows in $MANIFEST"

# 1. One value file per kv_path/field; all legacy variables of a row must agree.
declare -A VALUE_FILE
declare -a KV_PATHS=()
legacy_vars=()
while read -r _name _uid _envs kv field from; do
    key="$kv/$field"
    first=""
    for spec in ${from//,/ }; do
        var=${spec#@}
        out="$WORK/$(printf '%s' "$key" | tr '/' '_').$var"
        if [ "${spec:0:1}" = "@" ]; then
            env_value_to_file "$var" "$WORK/path" || die "$var is not set in $OLD_ENV"
            src=$(cat "$WORK/path")
            case "$src" in /*) ;; *) src="$REPO_DIR/${src#./}" ;; esac
            [ -r "$src" ] || die "$var points to $src, which is not readable"
            cp -- "$src" "$out"
        else
            env_value_to_file "$var" "$out" || die "$var is not set (or empty) in $OLD_ENV"
        fi
        legacy_vars+=("$var")
        if [ -z "$first" ]; then
            first=$var
            VALUE_FILE[$key]=$out
        elif ! cmp -s "${VALUE_FILE[$key]}" "$out"; then
            die "$var and $first differ, but both map to $key. Fix .env first."
        fi
    done
    case " ${KV_PATHS[*]} " in *" $kv "*) ;; *) KV_PATHS+=("$kv") ;; esac
done <<< "$ROWS"

# 2. Token.
if [ -z "${BAO_TOKEN:-}" ]; then
    read -rsp "OpenBao token (admin, or openvre-secrets-admin): " BAO_TOKEN; echo
fi
export BAO_TOKEN
bao token lookup >/dev/null 2>&1 || die "token rejected by OpenBao at $BAO_ADDR"

# 3. One JSON object per kv path, written with check-and-set 0 (create only).
for kv in "${KV_PATHS[@]}"; do
    jq_args=()
    filter="{"
    for key in "${!VALUE_FILE[@]}"; do
        [ "${key%%/*}" = "$kv" ] || continue
        field=${key#*/}
        jq_args+=(--rawfile "$field" "${VALUE_FILE[$key]}")
        filter+="\"$field\": \$$field,"
    done
    filter="${filter%,}}"
    # jq gets file paths and field names only; the JSON goes through a pipe.
    jq -n "${jq_args[@]}" "$filter" |
        bao kv put -mount=infra -cas=0 "openvre/$kv" - >/dev/null ||
        die "writing infra/openvre/$kv failed (does it already exist?)"
    echo "wrote infra/openvre/$kv"
done

# 4. Read back and compare hashes.
for key in "${!VALUE_FILE[@]}"; do
    kv=${key%%/*}
    field=${key#*/}
    want=$(sha256sum < "${VALUE_FILE[$key]}" | cut -d' ' -f1)
    got=$(bao kv get -mount=infra -format=json "openvre/$kv" |
        jq -j --arg f "$field" '.data.data[$f] // ""' | sha256sum | cut -d' ' -f1)
    [ "$want" = "$got" ] || die "read-back mismatch for infra/openvre/$key"
    echo "verified infra/openvre/$key"
done

echo
echo "Migration complete. After ./deploy.sh works, delete these lines from $OLD_ENV:"
printf '%s\n' "${legacy_vars[@]}" | sort -u | sed 's/^/  /'
echo "and shred the old TLS key file and any .env backups (see MIGRATION.md)."
