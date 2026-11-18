# OpenBao Production Deployment

This directory contains the production deployment configuration for OpenBao used by openVRE.

The production setup uses:

* OpenBao `2.4.0`
* Integrated Raft storage
* A persistent Docker volume
* TLS
* A single OpenBao node
* Manual initialization and unsealing
* Automatic post-initialization configuration through `bao-config-prod.sh`

The deployment is intentionally split into two parts:

| Step                        | Manual / Automatic |
| --------------------------- | ------------------ |
| Configure `.env`            | MANUAL             |
| Configure TLS certificates  | MANUAL             |
| Start OpenBao               | MANUAL             |
| Initialize OpenBao          | MANUAL             |
| Store unseal keys securely  | MANUAL             |
| Unseal OpenBao              | MANUAL             |
| Configure JWT / Keycloak    | AUTOMATIC          |
| Configure JWT role          | AUTOMATIC          |
| Configure policy            | AUTOMATIC          |
| Configure KV secrets engine | AUTOMATIC          |
| Verify configuration        | MANUAL             |

---

# 1. Directory structure

The production deployment should have a structure similar to:

```text
project/
├── .env
├── docker-compose.yml
│
└── openbao/
    ├── config/
    │   ├── openbao.hcl
    │   ├── jwt-user-policies-template.hcl
    │   └── ssl/
    │       ├── bao.crt
    │       ├── bao.key
    │       └── ca.crt
    │
    └── bao-config-prod.sh
```

The OpenBao Docker volume is managed by Docker:

```text
bao_data
```

It is mounted inside the container as:

```text
/openbao/data
```

This directory contains the persistent Raft data.

---

# 2. Requirements

The deployment host must have:

* Docker
* Docker Compose
* OpenBao CLI (`bao`)
* `jq`

Check the required commands:

```bash
docker --version
docker compose version
bao version
jq --version
```

The `bao` CLI and `jq` are required by `bao-config-prod.sh`.

> The OpenBao container itself does not need to contain `jq`. The configuration script is executed from the deployment/admin host.

---

# 3. Configure the environment

Create or edit the production `.env` file.

Example:

```env
# OpenBao public/client address
BAO_ADDR=https://openbao.example.com:8200

# TLS certificates on the deployment host
BAO_SSL_PEM=./openbao/config/ssl/bao.crt
BAO_SSL_KEY=./openbao/config/ssl/bao.key
BAO_SSL_CAFILE=./openbao/config/ssl/ca.crt

# TLS paths inside the OpenBao container
BAO_CERT_PEM=/etc/ssl/certs/bao.crt
BAO_CERT_KEY=/etc/ssl/certs/bao.key
BAO_CERT_CAFILE=/etc/ssl/certs/bao-ca.pem

# Keycloak
KEYCLOAK_REALM=your-realm
KEYCLOAK_SERVER=https://keycloak.example.com
```

Do **not** put the OpenBao root token or unseal keys in `.env`.

The initial root token and unseal keys must be stored securely outside the repository.

---

# 4. TLS certificates

The TLS files must exist before starting OpenBao:

```text
openbao/config/ssl/
├── bao.crt
├── bao.key
└── ca.crt
```

The certificate used by OpenBao must contain the hostname configured in:

```hcl
api_addr = "https://openbao.example.com:8200"
```

For example, if:

```env
BAO_ADDR=https://openbao.example.com:8200
```

then `bao.crt` must contain `openbao.example.com` in its Subject Alternative Names (SANs).

The private key must be protected appropriately.

For example:

```bash
chmod 600 openbao/config/ssl/bao.key
```

---

# 5. OpenBao configuration

The production OpenBao configuration is:

```hcl
ui = true

cluster_name = "openvre-openbao"

# Public/client address.
# This hostname must match the TLS certificate SAN.
api_addr = "https://openbao.example.com:8200"

# Internal Raft cluster address.
cluster_addr = "https://172.21.0.18:8201"


listener "tcp" {
  address         = "0.0.0.0:8200"
  cluster_address = "0.0.0.0:8201"

  tls_cert_file = "/etc/ssl/certs/bao.crt"
  tls_key_file  = "/etc/ssl/certs/bao.key"

  tls_min_version = "tls13"
}


storage "raft" {
  path    = "/openbao/data"
  node_id = "bao-server"
}
```

For this deployment there is only one OpenBao node, so no `retry_join` configuration is required.

---

# 6. Start OpenBao

In production OpenBao is its own Compose project
(`docker-compose-openbao.yml`), so deploying the app never restarts it.
Start only the OpenBao server:

```bash
docker compose -p openvre-openbao -f docker-compose-openbao.yml up -d
```

`./deploy.sh` runs the same command on every deploy (a no-op when it is
already running). The data volume is `external`: create it once with
`docker volume create "$BAO_DATA_VOLUME"` before the first start of a new
installation, and the network with
`docker network create --subnet 172.21.0.0/24 "$NETWORK_NAME"`.

Check that the container is running:

```bash
docker ps
```

You should see:

```text
bao-server
```

Check the container logs:

```bash
docker logs bao-server
```

---

# 7. Check the OpenBao status

Run:

```bash
docker exec -it bao-server bao status
```

On a new installation, OpenBao should report that it is not initialized and is sealed.

For example:

```text
Initialized     false
Sealed          true
```

At this point, the server is running, but it has not yet been initialized.

---

# 8. Initialize OpenBao — MANUAL

Initialization must be performed manually.

Run:

```bash
docker exec -it bao-server bao operator init \
    -key-shares=5 \
    -key-threshold=3
```

OpenBao will generate:

* 5 unseal key shares
* A threshold of 3 keys required to unseal
* An initial root token

The output will look similar to:

```text
Unseal Key 1: ...
Unseal Key 2: ...
Unseal Key 3: ...
Unseal Key 4: ...
Unseal Key 5: ...

Initial Root Token: ...
```

## IMPORTANT

This information is extremely sensitive.

Immediately store the five unseal key shares and the initial root token in a secure location.

Do **not** put them in:

* Git
* `.env`
* `docker-compose.yml`
* `openbao.hcl`
* `bao-config-prod.sh`
* Docker images
* application configuration
* README files
* logs

Do not send the keys through normal chat or email.

---

# 9. Unseal OpenBao — MANUAL

OpenBao must be unsealed manually using 3 different unseal key shares.

Run:

```bash
docker exec -it bao-server bao operator unseal
```

Enter the first unseal key when prompted.

Run the command again:

```bash
docker exec -it bao-server bao operator unseal
```

Enter a second, different unseal key.

Run it a third time:

```bash
docker exec -it bao-server bao operator unseal
```

Enter a third, different unseal key.

You should now have:

```text
Initialized     true
Sealed          false
```

Verify:

```bash
docker exec -it bao-server bao status
```

---

# 10. Configure JWT, Keycloak, policy and KV — AUTOMATIC

Once OpenBao has been initialized and unsealed, run the production configuration script:

```bash
./openbao/bao-config-prod.sh
```

Make sure the script is executable:

```bash
chmod +x ./openbao/bao-config-prod.sh
```

Then run:

```bash
./openbao/bao-config-prod.sh
```

The script performs the repetitive post-initialization configuration.

It configures:

1. JWT authentication
2. Keycloak JWT configuration
3. The `user-role` JWT role
4. The OpenBao policy
5. The KV secrets engine
6. The required KV configuration

The script is intended to be run from the deployment/admin host, **not from inside the OpenBao container**.

---

# 11. OpenBao authentication

When the configuration script reaches the authentication step, it asks for the OpenBao token interactively.

You will see something similar to:

```text
OpenBao token:
```

Enter the initial root token generated during:

```bash
docker exec -it bao-server bao operator init \
    -key-shares=5 \
    -key-threshold=3
```

The token is entered interactively.

It is **not** stored in:

* `.env`
* `docker-compose.yml`
* `bao-config-prod.sh`

This is intentional.

---

# 12. What `bao-config-prod.sh` configures

After authentication, the script configures the OpenBao application environment.

It configures:

1. JWT authentication
2. Keycloak JWT configuration
3. The `user-role` JWT role
4. The OpenBao policy
5. The KV secrets engine
6. The required KV configuration

The script is intended to be run from the deployment/admin host, **not from inside the OpenBao container**.

## JWT authentication

The script enables JWT authentication if it is not already enabled.

It then configures the JWT provider using the configured Keycloak server.

The configuration is based on the values defined in `.env`:

```env
KEYCLOAK_REALM=your-realm
KEYCLOAK_SERVER=https://keycloak.example.com
```

The resulting authentication flow is:

```text
Keycloak
    |
    | JWT
    v
OpenBao JWT authentication
```

## JWT role

The script configures the application JWT role:

```text
user-role
```

The role contains the required JWT validation configuration, including the Keycloak issuer, audience and claims required by the application.

## Policy

The script creates or updates the OpenBao policy used by the application.

The policy template is located at:

```text
openbao/config/jwt-user-policies-template.hcl
```

The script automatically obtains the JWT authentication accessor and inserts it into the policy where required.

The resulting policy is installed as:

```text
user-policy
```

## KV secrets engine

The script enables the KV secrets engine if it is not already enabled.

The application can then use the configured secret path:

```text
secret/
```

The KV configuration is also applied automatically by the script.

---

# 13. Verify the OpenBao status

After the configuration script finishes, verify that OpenBao is initialized and unsealed:

```bash
docker exec -it bao-server bao status
```

Expected:

```text
Initialized     true
Sealed          false
```

---

# 14. Verify the Raft storage

Check the Raft cluster:

```bash
docker exec -it bao-server bao operator raft list-peers
```

For this single-node deployment, you should see one peer:

```text
bao-server
```

The Raft data is stored inside the container at:

```text
/openbao/data
```

This directory is backed by the Docker volume:

```text
bao_data
```

Therefore, the data survives normal container restarts and recreation as long as the Docker volume is preserved.

---

# 15. Verify authentication methods

Run:

```bash
docker exec -it bao-server bao auth list
```

You should see the JWT authentication method.

For example:

```text
Path      Type
----      ----
token/    token
jwt/      jwt
```

The exact output may vary depending on the OpenBao configuration.

---

# 16. Verify policies

Run:

```bash
docker exec -it bao-server bao policy list
```

You should see the application policy:

```text
user-policy
```

The list may also contain the standard OpenBao policies:

```text
default
root
```

---

# 17. Verify secrets engines

Run:

```bash
docker exec -it bao-server bao secrets list
```

You should see the KV secrets engine:

```text
Path         Type
----         ----
cubbyhole/   cubbyhole
identity/    identity
secret/      kv
sys/         system
```

The exact output may contain additional engines depending on the deployment.

---

# 18. Complete verification

At this point, the following should all be true:

* OpenBao container is running
* OpenBao is initialized
* OpenBao is unsealed
* Raft storage is enabled
* The Raft peer is `bao-server`
* JWT authentication is enabled
* The JWT role is configured
* `user-policy` is configured
* The KV secrets engine is enabled
* TLS is enabled
* The `bao_data` Docker volume is being used

---

# 19. Infrastructure secrets (deploy.sh)

The app's own secrets (database passwords, Keycloak client and admin
secrets, mail password, front-end TLS key) are stored in a separate kv-v2
engine, `infra/`, at `infra/openvre/<kv_path>`. [`secrets/manifest`](../secrets/manifest)
lists each one. `sudo ./deploy.sh` reads them and writes one file per secret
to a tmpfs (`/run/openvre/secrets/<name>`, mode 0400, owned by the reading
container's UID). Compose mounts each file at `/run/secrets/<name>`.

## Setup

`bao-config-prod.sh` (section 10) also enables `infra/`, installs the policies
in `openbao/policies/` and configures the AppRole `openvre-deploy` (read-only
`infra/openvre/*`, 5-minute tokens, login only from `BAO_APPROLE_CIDR`,
default `172.21.0.1/32`: the host's address on the Docker network). Write its
credentials on the host, as root:

```bash
sudo -E ./openbao/bao-config-prod.sh --install-approle
```

This writes `role_id` and `secret_id` to `/etc/openvre/approle/` (directory
0700, files 0400, root). They go straight to files, never to the terminal.

Then store each secret of the manifest (needs an admin token). Read the value
with hidden input so it never appears in argv or shell history:

```bash
read -rsp 'value: ' V; echo
printf '%s' "$V" | bao kv patch -mount=infra openvre/<kv_path> <field>=-   # or `kv put` for a new path
unset V
bao kv put -mount=infra openvre/frontend-tls key=@openVRE.key           # from a file
```

## The AppRole credential

The `secret_id` is the one secret on the host that unlocks the others. It is
limited (read-only, bound to the host's address, short tokens, every use in
the audit log), but **root on the host, and anything that can use
`/var/run/docker.sock`, can read it**, just like the tmpfs files.

- Rotate it (e.g. yearly, or when an admin leaves): run
  `--install-approle` again. Every older `secret_id` is destroyed.
- Revoke it in an emergency (admin token):
  `bao delete auth/approle/role/openvre-deploy` deletes the role and every
  `secret_id` at once. Re-run `bao-config-prod.sh --install-approle` to create
  new ones; until then deploy with `sudo ./deploy.sh --token-prompt`.
- If the host reaches OpenBao through a proxy instead of the Docker network,
  set `BAO_APPROLE_CIDR` to the address OpenBao sees and re-run the script.

## Optional: Keycloak login for secret admins

```bash
export BAO_OIDC_CLIENT_ID=openvre-bao-cli
./openbao/bao-config-prod.sh --oidc
```

Enables `auth/oidc` (role `openvre-operator`) and maps the Keycloak group
`openvre-secrets-admins` to the policy `openvre-secrets-admin` (manage
`infra/openvre/*`). The Keycloak client must be confidential, allow the
redirect URI `http://localhost:8250/oidc/callback`, and have a *Group
Membership* mapper with claim name `groups` and *Full group path* off. To
grant or remove access, change the group membership in Keycloak.

## Rotating a secret

Change it **at its source first**, then in OpenBao (`bao kv patch` as above),
then recreate the containers that read it:

| `kv_path` / `field` | 1. Change at the source | 2. Redeploy |
|---|---|---|
| `mongo/app_password` | `docker exec -it my-mongodb mongosh -u <root> -p --authenticationDatabase admin`, `use <MONGO_MAIN_DB>`, `db.changeUserPassword("<app user>", passwordPrompt())` | `sudo ./deploy.sh --force-recreate front_end` |
| `mongo/root_password` | same shell: `use admin`, `db.changeUserPassword("<root user>", passwordPrompt())` | `sudo ./deploy.sh` |
| `keycloak/db_password` | `docker exec -it postgres psql -U <POSTGRES_USER> -d <POSTGRES_DB> -c '\password <POSTGRES_USER>'` | `sudo ./deploy.sh --force-recreate keycloak` |
| `keycloak/client_secret` | Keycloak admin: Clients, `<KEYCLOAK_CLIENT>`, Credentials, Regenerate | `sudo ./deploy.sh --force-recreate front_end keycloak` |
| `keycloak/admin_password` | Keycloak admin: Users, `<KEYCLOAK_ADMIN_USER>`, Credentials, Reset password | `sudo ./deploy.sh` |
| `mail/password` | at the mail provider | `sudo ./deploy.sh --force-recreate front_end` |
| `frontend-tls/key` | new certificate **with a new key**; `bao kv patch ... key=@new.key`, new cert at `FRONTEND_SSL_PEM`, `shred -u new.key` | `sudo ./deploy.sh --force-recreate front_end` |

(Mongo root, Postgres and the Keycloak admin passwords are only read when
their volume is empty, so a plain redeploy just keeps the files in sync.)

## Adding a secret

Add a row to `secrets/manifest`, store the value in OpenBao, add it to the
top-level `secrets:` of both compose files and to the service's `secrets:`
list, and point the app at `/run/secrets/<name>` (a native `*_FILE` variable,
or any `X_FILE` variable for services started through `scripts/load-secrets.sh`).
For dev, also commit a dummy file in `secrets-dev/`.
