#!/bin/sh
set -eu

: "${KEYCLOAK_SERVER:?KEYCLOAK_SERVER must be set}"
: "${KEYCLOAK_REALM:?KEYCLOAK_REALM must be set}"

# keep Docker/Compose values if they are provided
: "${VAULT_ADDR:=http://127.0.0.1:8200}"
: "${BAO_DEV_ROOT_TOKEN_ID:=root}"

export VAULT_ADDR
export BAO_DEV_ROOT_TOKEN_ID


CONFIG_DIR="/openbao/config"
POLICY_TEMPLATE="$CONFIG_DIR/jwt-user-policies-template.hcl"
GENERATED_POLICY="/tmp/jwt-user-policies.hcl"


echo "Starting OpenBao in development mode..."

bao server \
    -dev \
    -dev-listen-address=0.0.0.0:8200 \
    -dev-root-token-id=root &

BAO_PID=$!

cleanup() {
    kill "$BAO_PID" 2>/dev/null || true
    wait "$BAO_PID" 2>/dev/null || true
}
trap cleanup TERM INT

echo "Waiting for OpenBao..."

until bao status >/dev/null 2>&1; do
    if ! kill -0 "$BAO_PID" 2>/dev/null; then
        echo "ERROR: OpenBao exited during startup."
        wait "$BAO_PID" || true
        exit 1
    fi
    sleep 1
done

echo "OpenBao is ready."

echo "Checking JWT authentication..."

if ! bao auth list -format=json | jq -e 'has("jwt/")' >/dev/null; then
    bao auth enable jwt
fi

echo "Configuring Keycloak JWT/OIDC..."

bao write auth/jwt/config \
    oidc_discovery_url="${KEYCLOAK_SERVER%/}/realms/$KEYCLOAK_REALM" \
    bound_issuer="${KEYCLOAK_SERVER%/}/realms/$KEYCLOAK_REALM"

bao write auth/jwt/role/user-role \
    role_type="jwt" \
    bound_audiences="account" \
    user_claim="sub" \
    policies="user-policy" \
    ttl="1h"

echo "Generating policy..."

JWT_ACCESSOR=$(bao auth list -format=json |
    jq -r '."jwt/".accessor')

sed "s/JWT_ACCESSOR/$JWT_ACCESSOR/g" \
    "$POLICY_TEMPLATE" > "$GENERATED_POLICY"

echo "Installing user policy..."

bao policy write user-policy "$GENERATED_POLICY"

echo "Checking KV v2..."

if ! bao secrets list -format=json | jq -e 'has("secret/")' >/dev/null; then
    bao secrets enable -path=secret kv-v2
fi

echo "Configuring KV v2..."

bao write secret/config max_versions=1

echo "OpenBao development configuration completed."

wait "$BAO_PID"