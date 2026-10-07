#!/bin/sh

set -eu

echo "======================================"
echo "Configuring OpenBao"
echo "======================================"

export BAO_ADDR

echo "OpenBao address:"
echo "  ${BAO_ADDR}"
echo

echo "Keycloak:"
echo "  ${KEYCLOAK_SERVER}/realms/${KEYCLOAK_REALM}"
echo

echo "Enter an OpenBao administrator token."
echo "The token will NOT be stored in the environment."
echo

printf "OpenBao token: "
stty -echo
read BAO_TOKEN
stty echo
printf "\n\n"

export BAO_TOKEN

echo "Authenticating with OpenBao..."

bao login "$BAO_TOKEN" >/dev/null

unset BAO_TOKEN

echo "Authentication successful."
echo


echo "Enabling JWT authentication..."

if ! bao auth list -format=json | jq -e '."jwt/"' >/dev/null 2>&1; then
    bao auth enable jwt
fi

echo "Configuring JWT/OIDC..."

bao write auth/jwt/config \
    oidc_discovery_url="${KEYCLOAK_SERVER}/realms/${KEYCLOAK_REALM}" \
    bound_issuer="${KEYCLOAK_SERVER}/realms/${KEYCLOAK_REALM}"

echo "Configuring JWT role..."

bao write auth/jwt/role/user-role \
    role_type="jwt" \
    bound_audiences="account" \
    user_claim="sub" \
    policies="user-policy" \
    ttl="1h"

JWT_ACCESSOR=$(bao auth list -format=json \
    | jq -r '."jwt/".accessor')

echo "Generating JWT policy..."

sed "s/JWT_ACCESSOR/${JWT_ACCESSOR}/g" \
    /openbao/config/jwt-user-policies-template.hcl \
    > /tmp/jwt-user-policies.hcl

echo "Installing user policy..."

bao policy write \
    user-policy \
    /tmp/jwt-user-policies.hcl

rm -f /tmp/jwt-user-policies.hcl

echo "Enabling KV v2..."

if ! bao secrets list -format=json | jq -e '."secret/"' >/dev/null 2>&1; then
    bao secrets enable -path=secret kv-v2
fi

bao write secret/config max_versions=1

echo ""
echo "OpenBao configuration completed."