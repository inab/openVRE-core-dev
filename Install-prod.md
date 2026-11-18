## OpenVRE Development Setup Guide

## Pre-requisites

- **Docker Engine - Community** (Version: 26.1.0)
- **Docker Compose** (Version: v2.26.1)

[Here](https://docs.docker.com/compose/install/) you can find instructions to install Docker Compose.

## Cloning the Repository

Clone the OpenVRE core development repository using the following command:

```bash
 git clone https://github.com/inab/openVRE.git 
```

Navigate into the cloned directory:

```bash
cd openVRE
```

## Configuration

First thing, is to create and configure your own  `.env` file:

``` bash
cd openVRE
cp .env.sample-prod .env
```

Edit the new `.env` file and adapt it to your own environment.

Then, do the same for the `globals.inc.php` file:

``` bash
cp front_end/openVRE/config/globals.inc.php.sample-prod front_end/openVRE/config/globals.inc.php
```

Remove commented certificates from the `docker-compose-prod.yml` file that your instance will use (in case you use a proxy not all the certificates are required). You may use the template script `generate-certs.sh` to generate them.

After that, set up OpenBao (initialise, unseal, JWT login) as described in [openbao/README.md](openbao/README.md).

Secrets (passwords, client secrets, the front-end TLS key) are **not** in `.env`.
They are stored in OpenBao under `infra/openvre/*` (listed in
[secrets/manifest](secrets/manifest)) and `deploy.sh` writes them as files for
the containers. Set this up once, as root, with
`./openbao/bao-config-prod.sh --install-approle` and store the values (see
section 19 of [openbao/README.md](openbao/README.md)). An existing installation
with secrets in `.env` follows [MIGRATION.md](MIGRATION.md) instead.

For advanced system administration, such as SGE fine-tuning, Keycloak integration, or Vault setup, see [Admin-Specific Configuration](https://github.com/inab/openVRE/wiki/Developing-and-Administering-OpenVRE).

## Start the services

Start (or update) the services with:

``` bash
sudo ./deploy.sh
```

It starts and unseals OpenBao (you enter the unseal keys), logs in with the
AppRole credentials in `/etc/openvre/approle`, writes the secret files to a
tmpfs (`/run/openvre/secrets`) and runs
`docker compose -f docker-compose-prod.yml --profile local_auth up -d`.
Run it again after every reboot. A plain `docker compose -f docker-compose-prod.yml up`
fails on purpose because `SECRETS_DIR` is not set.

and check the status of the resulting containers:

```
docker ps
```