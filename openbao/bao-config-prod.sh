#!/bin/sh

set -e
apk add --no-cache jq

export BAO_ADDR="${BAO_ADDR}"

echo "Waiting for OpenBao..."

until bao status >/dev/null 2>&1; do
    sleep 2
done

echo "OpenBao is available."

echo "Enabling JWT authentication..."

bao auth enable jwt 2>/dev/null 

echo "Configuring JWT/OIDC..."

bao write auth/jwt/config \
    oidc_discovery_url="$KEYCLOAK_SERVER/realms/$KEYCLOAK_REALM" \
    bound_issuer="$KEYCLOAK_SERVER/realms/$KEYCLOAK_REALM"

echo "Configuring JWT role..."

bao write auth/jwt/role/user-role \
    role_type="jwt" \
    bound_audiences="account" \
    user_claim="sub" \
    policies="user-policy" \
    ttl="1h"

echo "Getting JWT accessor..."

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

echo "OpenBao configuration complete."