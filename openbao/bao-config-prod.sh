#!/bin/sh

set -eu

CONFIG_DIR="/openbao/config"
POLICY_TEMPLATE="$CONFIG_DIR/jwt-user-policies-template.hcl"
GENERATED_POLICY="/tmp/jwt-user-policies.hcl"

: "${BAO_ADDR:?Set BAO_ADDR to your OpenBao API address}"
: "${KEYCLOAK_SERVER:?Set KEYCLOAK_SERVER}"
: "${KEYCLOAK_REALM:?Set KEYCLOAK_REALM}"

KEYCLOAK_ISSUER="${KEYCLOAK_SERVER%/}/realms/${KEYCLOAK_REALM}"

echo "======================================"
echo "OpenBao production configuration"
echo "======================================"
echo "OpenBao: $BAO_ADDR"
echo "Keycloak issuer: $KEYCLOAK_ISSUER"
echo

if [ ! -r "$POLICY_TEMPLATE" ]; then
    echo "ERROR: Policy template not found: $POLICY_TEMPLATE" >&2
    exit 1
fi

printf "OpenBao administrator token: "
stty -echo
if ! IFS= read -r BAO_TOKEN; then
    stty echo
    printf "\n"
    echo "ERROR: Could not read token." >&2
    exit 1
fi
stty echo
printf "\n"

export BAO_TOKEN
trap 'unset BAO_TOKEN; rm -f "$GENERATED_POLICY"; stty echo 2>/dev/null || true' EXIT HUP INT TERM

echo "Checking OpenBao connectivity..."

if ! bao status >/dev/null; then
    echo "ERROR: OpenBao is unavailable or status check failed." >&2
    exit 1
fi

echo "Checking administrator permissions..."

if ! bao token capabilities sys/auth | grep -qE '(^|[[:space:]])(root|sudo|create|update)([[:space:]]|$)'; then
    echo "WARNING: Could not confirm auth-management capabilities."
    echo "The following configuration commands may fail."
fi

echo "Enabling JWT authentication if needed..."

if ! bao auth list -format=json | jq -e 'has("jwt/")' >/dev/null; then
    bao auth enable jwt
fi

echo "Configuring Keycloak JWT/OIDC..."

bao write auth/jwt/config \
    oidc_discovery_url="$KEYCLOAK_ISSUER" \
    bound_issuer="$KEYCLOAK_ISSUER"

echo "Configuring JWT role..."

bao write auth/jwt/role/user-role \
    role_type="jwt" \
    bound_audiences="account" \
    user_claim="sub" \
    policies="user-policy" \
    ttl="1h"

echo "Resolving JWT auth accessor..."

JWT_ACCESSOR=$(bao auth list -format=json |
    jq -er '."jwt/".accessor')

echo "Generating policy..."

sed "s/JWT_ACCESSOR/$JWT_ACCESSOR/g" \
    "$POLICY_TEMPLATE" > "$GENERATED_POLICY"

echo "Installing user policy..."

bao policy write user-policy "$GENERATED_POLICY"

rm -f "$GENERATED_POLICY"

echo "Enabling KV v2 if needed..."

if ! bao secrets list -format=json | jq -e 'has("secret/")' >/dev/null; then
    bao secrets enable -path=secret kv-v2
fi

echo "Configuration completed."
echo "Review KV retention settings separately if required."