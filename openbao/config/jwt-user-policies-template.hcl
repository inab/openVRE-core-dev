path "secret/data/{{identity.entity.aliases.JWT_ACCESSOR.name}}/*" {
  capabilities = ["create", "read", "update", "delete", "list"]
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
# Metadata is scoped to the user's own prefix. "secret/metadata/*" let any
# logged-in user list and permanently delete every user's secrets.
path "secret/metadata/{{identity.entity.aliases.JWT_ACCESSOR.name}}/*" {
  capabilities = ["read", "list", "delete"]
}