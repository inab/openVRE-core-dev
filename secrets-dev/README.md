# Dev-only dummy secrets

Each file here is one secret for the **development** stack (`docker-compose.yml`),
mounted read-only at `/run/secrets/<file name>`. The values are the same public
dummies the old `.env.sample` used, so they are safe to commit and an existing
dev Mongo volume keeps working.

Never use these values anywhere else. Production secrets live in OpenBao
(`infra/openvre/*`) and are written by `./deploy.sh`; see `secrets/manifest`.

To use other local values, edit the files (and run `docker compose down -v`
once if you change a Mongo password), or point `SECRETS_DIR` in `.env` at
another directory with the same file names.
