# Backend service: access system secrets only.
path "secret/data/system/*" {
  capabilities = ["create", "read", "update", "patch", "delete"]
}

path "secret/metadata/system/*" {
  capabilities = ["read", "list", "delete"]
}

path "auth/token/lookup-self" {
  capabilities = ["read"]
}

path "auth/token/renew-self" {
  capabilities = ["update"]
}

path "auth/token/revoke-self" {
  capabilities = ["update"]
}

# Explicitly deny access to user secrets.
path "secret/data/users/*" {
  capabilities = ["deny"]
}

path "secret/metadata/users/*" {
  capabilities = ["deny"]
}