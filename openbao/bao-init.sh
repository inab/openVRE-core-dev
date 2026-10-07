#!/bin/sh

set -eu

BAO_CONFIG="/openbao/config/openbao.hcl"
BAO_DATA="/openbao/data"
BOOTSTRAP_DIR="/openbao/bootstrap"
INIT_FILE="$BOOTSTRAP_DIR/init.json"

echo "Fixing OpenBao data permissions..."

chown -R openbao:openbao "$BAO_DATA"
chown -R openbao:openbao "$BOOTSTRAP_DIR"

echo "Starting OpenBao..."

bao server \
    -config="$BAO_CONFIG" &

BAO_PID=$!

trap 'kill "$BAO_PID" 2>/dev/null || true' TERM INT

echo "Waiting for OpenBao..."

while true; do
    STATUS=$(bao status 2>&1) || true

    if echo "$STATUS" | grep -q "Initialized"; then
        break
    fi

    sleep 1
done

echo "Checking initialization..."

STATUS=$(bao status 2>&1) || true

if echo "$STATUS" | grep -q "Initialized.*false"; then

    echo "======================================"
    echo "First OpenBao startup"
    echo "Initializing Raft storage..."
    echo "======================================"

    bao operator init \
        -key-shares=1 \
        -key-threshold=1 \
        -format=json \
        > "$INIT_FILE"

    chmod 600 "$INIT_FILE"

    UNSEAL_KEY=$(jq -r '.unseal_keys_b64[0]' "$INIT_FILE")
    ROOT_TOKEN=$(jq -r '.root_token' "$INIT_FILE")

    echo "Unsealing OpenBao..."

    bao operator unseal "$UNSEAL_KEY"

    echo "Authenticating..."

    bao login "$ROOT_TOKEN"

    echo "Running initial configuration..."
    echo "Enabling JWT authentication..."

    bao auth enable jwt 2>/dev/null 

    echo "Configuring JWT/OIDC..."

    bao write auth/jwt/config \
        oidc_discovery_url="$KEYCLOAK_SERVER/realms/$KEYCLOAK_REALM" \
        bound_issuer="$KEYCLOAK_SERVER/realms/$KEYCLOAK_REALM"


    bao write auth/jwt/role/user-role \
        role_type="jwt" \
        bound_audiences="account" \
        user_claim="sub" \
        policies="user-policy" \
        ttl="1h"

    JWT_ACCESSOR=$(bao auth list -format=json | jq -r '."jwt/".accessor')

    echo "Generating JWT policy..."

    sed "s/JWT_ACCESSOR/$JWT_ACCESSOR/g" \
        /openbao/config/jwt-user-policies-template.hcl \
        > /openbao/config/jwt-user-policies.hcl

    echo "Installing user policy..."

    bao policy write \
        user-policy \
        /openbao/config/jwt-user-policies.hcl

    echo "Enabling KV v2..."

    bao secrets enable -path=secret kv-v2 2>/dev/null

    bao write secret/config max_versions=1

    echo "Bootstrap configuration completed."

else

    echo "OpenBao was already initialized."

    if echo "$STATUS" | grep -q "Sealed.*true"; then

        echo "OpenBao is sealed."
        echo "Attempting automatic unseal..."

        if [ ! -f "$INIT_FILE" ]; then
            echo "ERROR: Initialization file not found:"
            echo "$INIT_FILE"
            exit 1
        fi

        UNSEAL_KEY=$(jq -r '.unseal_keys_b64[0]' "$INIT_FILE")

        bao operator unseal "$UNSEAL_KEY"

        echo "OpenBao unsealed."

    else

        echo "OpenBao is already unsealed."

    fi

fi

echo "OpenBao startup complete."

wait "$BAO_PID"