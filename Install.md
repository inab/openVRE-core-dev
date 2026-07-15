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

### React islands and `OPENVRE_ENV`

The platform includes a React frontend (`react-frontend/`) embedded as islands in PHP pages. Set the application environment in `.env`:

| Variable | Dev value | Effect |
|---|---|---|
| `OPENVRE_ENV` | `dev` (default in `.env.sample`) | Vite dev server with hot reload |
| `REACT_VITE_DEV_SERVER` | `http://localhost:5173` | Browser loads islands from the dev server |

See [react-frontend/README.md](react-frontend/README.md) for the full guide (adding islands, PHP integration, production mode).

## Start the services

Run the `docker-compose.yml` file once you have set up your OpenVRE instance with the following command: 

``` bash
docker compose --profile "local_auth" up -d 
```

and check the status of the resulting containers:

```
docker ps
```

The `react-frontend` container starts automatically:

- **Development** (`OPENVRE_ENV=dev`, default): Vite dev server with hot reload — edit files under `react-frontend/src/` and see changes instantly.
- **Production** (`OPENVRE_ENV=prod`): runs a one-shot build on startup, then exits.

Details: [react-frontend/README.md](react-frontend/README.md).
