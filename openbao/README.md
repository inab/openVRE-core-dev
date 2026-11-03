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

Start only the OpenBao server:

```bash
docker compose up -d bao-server
```

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
