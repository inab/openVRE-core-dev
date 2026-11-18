# openvre-secrets-admin: manage the app's infrastructure secrets
# (migrate-secrets.sh, rotation with `bao kv patch`). Attached to the identity
# group "openvre-secrets-admins" by openbao/bao-config-prod.sh --oidc.
path "infra/data/openvre/*" {
  capabilities = ["create", "read", "update", "patch", "delete"]
}

path "infra/metadata/openvre/*" {
  capabilities = ["read", "list", "update", "delete"]
}

path "infra/delete/openvre/*" {
  capabilities = ["update"]
}

path "infra/undelete/openvre/*" {
  capabilities = ["update"]
}

path "infra/destroy/openvre/*" {
  capabilities = ["update"]
}
