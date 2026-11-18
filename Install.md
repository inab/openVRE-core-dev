## OpenVRE Development Setup Guide

## Pre-requisites

- **Docker Engine - Community** (Version: 26.1.0)
- **Docker Compose** (Version: v2.26.1)

[Here](https://docs.docker.com/compose/install/) you can find instructions to install Docker Compose.

## Cloning the Repository

Clone the OpenVRE core development repository using the following command:

```bash
 git clone https://github.com/inab/openVRE-core-dev.git 
```

Navigate into the cloned directory:

```bash
cd openVRE-core-dev
```

## Configuration

First thing, is to create and configure your own  `.env` file:

```
cd openVRE-core-dev
cp .env.sample .env
```

At the moment, the default values should work in most of the systems.

If you need to change them, you can do it in the `.env` file.

Then, do the same for the `globals.inc.php` file:

``` bash
cp front_end/openVRE/config/globals.inc.php.sample front_end/openVRE/config/globals.inc.php
```

For advanced system administration, such as SGE fine-tuning, Keycloak integration, or Vault setup, see [Admin-Specific Configuration](https://github.com/inab/openVRE/wiki/Developing-and-Administering-OpenVRE).

## Secrets

Passwords and client secrets are not in `.env`. Dev uses the committed dummy
files in [secrets-dev/](secrets-dev/README.md), mounted read-only at
`/run/secrets/<name>` in the containers (Compose `secrets:`). Nothing to set
up; read one with e.g. `cat secrets-dev/keycloak_admin_password`.

## Start the services

Run the `docker-compose.yml` file once you have set up your OpenVRE instance with the following command: 

``` bash
docker compose --profile "local_auth" up -d
```

and check the status of the resulting containers:

```
docker ps
```
