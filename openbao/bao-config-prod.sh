#!/bin/sh
#
# Post-init configuration of the production OpenBao. Run from the admin host
# (host `bao` CLI and jq). Idempotent. Configures:
#   - JWT login for app users (user-role, user-policy) and the secret/ engine
#   - infra/ kv-v2 for the app's own secrets, the policies openvre-deploy and
#     openvre-secrets-admin, and the AppRole openvre-deploy used by deploy.sh
# Options:
#   --install-approle  (as root) write the AppRole role_id and a new secret_id
#                      to $BAO_APPROLE_DIR; destroys every older secret_id
#   --oidc             Keycloak login for humans (role openvre-operator); the
#                      Keycloak group openvre-secrets-admins gets
#                      openvre-secrets-admin. Needs BAO_OIDC_CLIENT_ID.
# Env: BAO_ADDR, KEYCLOAK_SERVER, KEYCLOAK_REALM (required); BAO_CACERT,
#   BAO_APPROLE_DIR (/etc/openvre/approle), BAO_APPROLE_CIDR (172.21.0.1/32:
#   the host's address on the Docker network), BAO_OIDC_PATH (oidc),
#   KC_GROUP_SECRETS_ADMINS (openvre-secrets-admins).

set -eu

INSTALL_APPROLE=0
OIDC=0
for arg in "$@"; do
    case "$arg" in
        --install-approle) INSTALL_APPROLE=1 ;;
        --oidc) OIDC=1 ;;
        *) echo "usage: $0 [--install-approle] [--oidc]" >&2; exit 2 ;;
    esac
done

# Run from the host: templates and policies are read from this repo.
HERE="$(cd "$(dirname "$0")" && pwd)"
CONFIG_DIR="${CONFIG_DIR:-$HERE/config}"
POLICY_DIR="$HERE/policies"
APPROLE_DIR="${BAO_APPROLE_DIR:-/etc/openvre/approle}"
APPROLE_CIDR="${BAO_APPROLE_CIDR:-172.21.0.1/32}"
OIDC_PATH="${BAO_OIDC_PATH:-oidc}"
KC_GROUP_SECRETS_ADMINS="${KC_GROUP_SECRETS_ADMINS:-openvre-secrets-admins}"
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

if [ "$INSTALL_APPROLE" -eq 1 ] && [ "$(id -u)" -ne 0 ]; then
    echo "ERROR: --install-approle must run as root (writes $APPROLE_DIR)." >&2
    exit 1
fi
if [ "$OIDC" -eq 1 ]; then
    : "${BAO_OIDC_CLIENT_ID:?Set BAO_OIDC_CLIENT_ID (Keycloak client for operators)}"
fi

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
trap 'unset BAO_TOKEN; rm -f "$GENERATED_POLICY" "$APPROLE_DIR/.new.json"; stty echo 2>/dev/null || true' EXIT HUP INT TERM

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

echo "Enabling infra/ kv-v2 (app infrastructure secrets) if needed..."

if ! bao secrets list -format=json | jq -e 'has("infra/")' >/dev/null; then
    bao secrets enable -path=infra -version=2 kv
fi

echo "Installing infra policies..."

bao policy write openvre-deploy "$POLICY_DIR/openvre-deploy.hcl"
bao policy write openvre-secrets-admin "$POLICY_DIR/openvre-secrets-admin.hcl"

echo "Configuring AppRole openvre-deploy (login from $APPROLE_CIDR only)..."

if ! bao auth list -format=json | jq -e 'has("approle/")' >/dev/null; then
    bao auth enable approle
fi
bao write auth/approle/role/openvre-deploy \
    token_policies=openvre-deploy \
    token_ttl=5m \
    token_max_ttl=10m \
    secret_id_ttl=0 \
    secret_id_bound_cidrs="$APPROLE_CIDR" \
    token_bound_cidrs="$APPROLE_CIDR"

if [ "$INSTALL_APPROLE" -eq 1 ]; then
    echo "Writing AppRole credentials to $APPROLE_DIR..."
    install -d -m 0700 -o 0 -g 0 "$APPROLE_DIR"
    # Credentials go straight to files (umask 077), never to the terminal.
    old_umask=$(umask)
    umask 077
    bao read -field=role_id auth/approle/role/openvre-deploy/role-id > "$APPROLE_DIR/role_id.new"
    bao write -f -format=json auth/approle/role/openvre-deploy/secret-id > "$APPROLE_DIR/.new.json"
    jq -j '.data.secret_id' "$APPROLE_DIR/.new.json" > "$APPROLE_DIR/secret_id.new"
    NEW_ACCESSOR=$(jq -r '.data.secret_id_accessor' "$APPROLE_DIR/.new.json")
    rm -f "$APPROLE_DIR/.new.json"
    chmod 0400 "$APPROLE_DIR/role_id.new" "$APPROLE_DIR/secret_id.new"
    mv -f "$APPROLE_DIR/role_id.new" "$APPROLE_DIR/role_id"
    mv -f "$APPROLE_DIR/secret_id.new" "$APPROLE_DIR/secret_id"
    umask "$old_umask"
    # Rotation: only the secret_id just written stays valid.
    for accessor in $(bao list -format=json auth/approle/role/openvre-deploy/secret-id 2>/dev/null | jq -r '.[]'); do
        if [ "$accessor" != "$NEW_ACCESSOR" ]; then
            bao write auth/approle/role/openvre-deploy/secret-id-accessor/destroy \
                secret_id_accessor="$accessor" >/dev/null
        fi
    done
fi

if [ "$OIDC" -eq 1 ]; then
    echo "Configuring OIDC login for operators at auth/$OIDC_PATH..."

    if ! bao auth list -format=json | jq -e --arg p "$OIDC_PATH/" 'has($p)' >/dev/null; then
        bao auth enable -path="$OIDC_PATH" oidc
    fi
    printf "Keycloak client secret for %s: " "$BAO_OIDC_CLIENT_ID"
    stty -echo
    IFS= read -r CLIENT_SECRET || CLIENT_SECRET=""
    stty echo
    printf "\n"
    if [ -z "$CLIENT_SECRET" ]; then
        echo "ERROR: empty client secret" >&2
        exit 1
    fi
    # "=-" makes bao read the value from stdin instead of argv.
    printf '%s' "$CLIENT_SECRET" | bao write "auth/$OIDC_PATH/config" \
        oidc_discovery_url="$KEYCLOAK_ISSUER" \
        oidc_client_id="$BAO_OIDC_CLIENT_ID" \
        oidc_client_secret=- \
        default_role=openvre-operator
    unset CLIENT_SECRET
    bao write "auth/$OIDC_PATH/role/openvre-operator" \
        role_type=oidc \
        user_claim=sub \
        groups_claim=groups \
        oidc_scopes="openid,profile,email" \
        bound_audiences="$BAO_OIDC_CLIENT_ID" \
        allowed_redirect_uris="http://localhost:8250/oidc/callback" \
        token_ttl=15m \
        token_max_ttl=1h

    echo "Mapping Keycloak group $KC_GROUP_SECRETS_ADMINS to openvre-secrets-admin..."

    bao write identity/group/name/openvre-secrets-admins \
        type=external policies=openvre-secrets-admin >/dev/null
    OIDC_ACCESSOR=$(bao auth list -format=json | jq -er --arg p "$OIDC_PATH/" '.[$p].accessor')
    GROUP_INFO=$(bao read -format=json identity/group/name/openvre-secrets-admins)
    if [ -z "$(printf '%s' "$GROUP_INFO" | jq -r '.data.alias.name // empty')" ]; then
        bao write identity/group-alias name="$KC_GROUP_SECRETS_ADMINS" \
            mount_accessor="$OIDC_ACCESSOR" \
            canonical_id="$(printf '%s' "$GROUP_INFO" | jq -r '.data.id')" >/dev/null
    fi
fi

echo "Configuration completed."
echo "Review KV retention settings separately if required."