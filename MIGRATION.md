# Migration: secrets from `.env` to files (OpenBao in prod)

> Temporary document. Once production is migrated, delete it together with
> `migrate-secrets.sh` and the `MIGRATION-ONLY` blocks in `deploy.sh`.

Passwords, client secrets and the front-end TLS key are no longer in `.env` and
no longer reach containers as environment variables (so they are gone from
`docker inspect`). Each container gets them as read-only files at
`/run/secrets/<name>`, through Compose `secrets:` entries:

| Environment | Where the files come from |
|---|---|
| dev | committed dummy files in [`secrets-dev/`](secrets-dev/README.md) |
| prod | OpenBao `infra/openvre/*`, written by `sudo ./deploy.sh` to a tmpfs (`/run/openvre/secrets`) |

[`secrets/manifest`](secrets/manifest) lists every secret. Mongo and Postgres
read the files natively (`*_FILE`); Keycloak and front_end start through
[`scripts/load-secrets.sh`](scripts/load-secrets.sh), which turns each
`X_FILE=/run/secrets/...` into `X` so Apache and PHP work unchanged.

---

## Dev

1. Delete these lines from your `.env` (they are ignored now):
   `KEYCLOAK_SECRET`, `KEYCLOAK_ADMIN_PASSWORD`, `MONGO_INITDB_PASSWORD`,
   `MONGO_INITDB_ROOT_PASSWORD`, `MAIL_PASS`.
2. Start as usual: `docker compose --profile local_auth up -d`.

The dummy files hold the old `.env.sample` values (`user`, `admin`, `pass`, ...),
so an existing Mongo volume keeps working. If you had changed those values in
your `.env`, recreate the volume once (`docker compose --profile local_auth down -v`,
which deletes local Mongo data) or point `SECRETS_DIR` at a directory with your
own values.

---

## Prod

> Plan a maintenance window with the holders of the unseal keys: step 5 moves
> the OpenBao container to its own Compose project, which restarts and seals it.

**Prerequisites:** root on the Docker host, the `bao` CLI and `jq` there, and
the host must reach `BAO_ADDR`. OpenBao publishes no port, so point the hostname
of `BAO_ADDR` at the container (e.g. `/etc/hosts`: `172.21.0.18 openbao.example.com`)
or use an existing proxy. The name must match the OpenBao certificate.

### 1. Back up

```bash
install -m 600 .env /root/env.bak-pre-openbao        # outside the repo
export BAO_ADDR=https://openbao.example.com:8200 BAO_CACERT=$PWD/openbao/config/ssl/ca.crt
read -rsp 'OpenBao root/admin token: ' BAO_TOKEN; echo; export BAO_TOKEN
bao operator raft snapshot save /root/bao.snap
unset BAO_TOKEN
```

### 2. Update the code and `.env`

Check out this version. **Keep the secret lines in `.env` for now**:
`migrate-secrets.sh` reads them. Then:

```bash
docker inspect bao-server --format '{{range .Mounts}}{{.Name}} {{end}}'
```
Set `BAO_DATA_VOLUME` to the volume listed there (`deploy.sh` refuses to start
OpenBao on a missing volume) and add `BAO_APPROLE_DIR=/etc/openvre/approle`
(see `.env.sample-prod`).

From now on `docker compose -f docker-compose-prod.yml ...` fails unless
`SECRETS_DIR` is set, which blocks a start without the secret files.

### 3. Configure OpenBao

With a root/admin token (hidden prompt), as root so it can write the AppRole files:

```bash
export KEYCLOAK_SERVER=... KEYCLOAK_REALM=...     # BAO_ADDR, BAO_CACERT from step 1
sudo -E ./openbao/bao-config-prod.sh --install-approle
```
Besides the existing JWT setup, this:
- re-applies `user-policy`, now scoped to each user's own metadata (before, any
  logged-in user could list and permanently delete every user's secrets);
- enables `infra/`, installs the policies in `openbao/policies/`, and creates the
  AppRole `openvre-deploy`, whose credentials go to `/etc/openvre/approle/`.

Add `--oidc` if secret admins should log in with Keycloak (see section 19 of
[openbao/README.md](openbao/README.md)).

### 4. Copy the secrets into OpenBao

```bash
./migrate-secrets.sh .env          # asks for the admin token (hidden)
```
It stops if `POSTGRES_PASSWORD` and `KEYCLOAK_DB_PASSWORD` differ, never
overwrites an existing path, verifies every value by hash, and prints the `.env`
lines to delete.

### 5. Deploy

```bash
sudo ./deploy.sh
```
The first run asks to remove the old `bao-server` container (the volume is
kept) and recreates it in the project `openvre-openbao`. Enter unseal key
shares when asked. It then logs in with the AppRole, writes the files to the
tmpfs, revokes its token and recreates the app containers.

If the AppRole login fails: `sudo ./deploy.sh --token-prompt` and paste an admin
token (e.g. from `bao operator generate-root`; revoke it afterwards).

### 6. Verify

```bash
sudo ls -ln /run/openvre/secrets           # 7 files, 0400, owned by 999/1000/0
docker inspect front_end keycloak my-mongodb postgres | grep -E 'PASS|SECRET|_KEY'
```
Only `*_FILE=/run/secrets/...` and `FRONTEND_CERT_KEY=/run/secrets/...` may
appear, never a value. Then log in to openVRE, open pages that read Mongo, store
and read a user secret (OpenBao), and send a test mail.

Check that the host's `front_end/openVRE/config/globals.inc.php` (gitignored, so
git does not update it) reads `getenv('BAO_ADDR')`, `BAO_API_VERSION`,
`BAO_ROLENAME` and `BAO_SECRET_PATH`, like lines 71-73 of
`globals.inc.php.sample-prod`. The old prod compose passed `VAULT_*` variables
that were never defined, so per-user secrets did not work in prod before.

### 7. Clean up

```bash
# delete from .env the lines migrate-secrets.sh listed, then:
shred -u /root/env.bak-pre-openbao
shred -u front_end/openVRE/ssl/openVRE.key        # the key is now in OpenBao
```
Remove any other copy of the old `.env` (backups, shell history). Keep
`/root/bao.snap` somewhere safe or delete it.

### 8. Rotate every secret

Every value sat in plaintext in `.env` and in `docker inspect`, so rotate all of
them, following the table in section 19 of [openbao/README.md](openbao/README.md).

### 9. Remove the migration code

In a follow-up PR: delete `migrate-secrets.sh`, this file, and the two
`MIGRATION-ONLY` blocks in `deploy.sh`.

### Day-2 operations

- **After every reboot** run `sudo ./deploy.sh`: OpenBao comes back sealed and
  `/run` is empty. Containers that try to start earlier fail on purpose.
- Manual Compose commands on the app stack need the variable:
  `sudo SECRETS_DIR=/run/openvre/secrets docker compose -f docker-compose-prod.yml --profile local_auth ps`
- OpenBao is managed on its own:
  `docker compose -p openvre-openbao -f docker-compose-openbao.yml logs`.
  `down` there never deletes the data volume (it is `external`).
