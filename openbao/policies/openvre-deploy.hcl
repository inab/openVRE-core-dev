# openvre-deploy: what ./deploy.sh needs. Read-only access to the app's
# infrastructure secrets (infra/ kv-v2, path openvre/*). Attached to the
# AppRole role "openvre-deploy" by openbao/bao-config-prod.sh.
# Token lookup/revoke of its own token come from the "default" policy.
path "infra/data/openvre/*" {
  capabilities = ["read"]
}
