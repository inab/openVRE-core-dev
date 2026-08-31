# React frontend (islands)

OpenVRE embeds React components as **islands** inside existing PHP pages. Each island is a small bundle mounted into a DOM node, sharing one React runtime (`react-vendor.js`).

## Quick start

With Docker Compose running, no manual npm steps are required:

```bash
docker compose --profile local_auth up -d
```

The `react-frontend` container starts the Vite dev server with **hot reload**. Edit files under `src/` and changes appear in the browser automatically — no manual refresh.

After adding a package, run `npm install <package>` in `react-frontend/` — the container detects the change, runs `pnpm ci`, and restarts Vite automatically. No container restart needed.

## Environment variables

Set in `.env` (see `.env.sample` / `.env.sample-prod`).

### `OPENVRE_ENV`

Controls how the `react-frontend` Docker service runs.

| Value | Default in | `react-frontend` container | PHP (`front_end`) |
|---|---|---|---|
| `dev` | `.env.sample` | Vite dev server on port 5173 (hot reload) | Loads islands from the dev server |
| `prod` | `.env.sample-prod` | One-shot `npm run build`, then exits | Serves static files from `assets/react/` |

### `REACT_VITE_DEV_SERVER` (dev only)

URL of the Vite dev server, as seen from the **browser**. Default in local development:

```bash
REACT_VITE_DEV_SERVER=http://localhost:5173
```

Set automatically by `docker-compose.yml`. Leave unset in production.

### `REACT_ISLAND_FIXTURES_PATH`

Absolute path to the shared fixtures JSON used by AuthBff for island API stand-ins. The file must contain `{ "files": [...], "tools": [...] }` (see `src/fixtures/workspaceFixtures.json`).

AuthBff resolves the path in this order:

1. `REACT_ISLAND_FIXTURES_PATH` env var (if set and readable)
2. Monorepo path `react-frontend/src/fixtures/workspaceFixtures.json` (host / non-Docker dev)
3. `front_end/openVRE/fixtures/workspaceFixtures.json` (Docker bind mount)

Default in Docker Compose:

```bash
REACT_ISLAND_FIXTURES_PATH=/var/www/html/openVRE/fixtures/workspaceFixtures.json
```

`docker-compose.yml` bind-mounts `react-frontend/src/fixtures/workspaceFixtures.json` to that path inside `front_end`. Edit the source file under `react-frontend/src/fixtures/` — no copy step needed. Override the env var only if you keep fixtures elsewhere (e.g. custom test data).

Used when `REACT_ISLAND_USE_FIXTURES=1` (GET `/auth-bff/files`) and always for GET `/auth-bff/tools` until a live Tools API exists.

### Development (`OPENVRE_ENV=dev`)

```bash
# .env
OPENVRE_ENV=dev
REACT_VITE_DEV_SERVER=http://localhost:5173
REACT_VITE_PORT=5173
```

```bash
docker compose --profile local_auth up -d
```

- `front_end` waits until the Vite dev server is ready.
- Saving a file under `react-frontend/src/` updates the page via hot reload.
- Running `npm install <package>` in `react-frontend/` auto-syncs dependencies and restarts Vite (within ~3 seconds).

### Production (`OPENVRE_ENV=prod`)

```bash
# .env
OPENVRE_ENV=prod
```

Do **not** set `REACT_VITE_DEV_SERVER` in production.

```bash
docker compose -f docker-compose-prod.yml --profile local_auth up -d
```

- React assets are built once at startup into `assets/react/`.
- No dev server runs in production.

### Fallback: static watch mode (optional)

If you need to test production-like static bundles locally without the Vite dev server, unset `REACT_VITE_DEV_SERVER` and run:

```bash
cd react-frontend
npm run build:watch
```

Refresh the browser manually after each rebuild.

## Project layout

```
react-frontend/
├── src/
│   ├── components/     # Shared React components
│   ├── entries/        # One file per island (built to assets/react/{name}.js)
│   │   └── _example.tsx  # Template (ignored by build — leading underscore)
│   └── lib/
│       └── mount-island.tsx
├── Dockerfile
└── docker-entrypoint.sh

front_end/openVRE/public/
├── assets/react/       # Production build output (react-vendor.js + island bundles)
└── phplib/
    └── react-islands.inc.php   # PHP helper: react_island_scripts()
```

## Adding a new island

1. **Component** — create `src/components/MyComponent.tsx`

2. **Entry** — create `src/entries/my-component.tsx`:

   ```tsx
   import { MyComponent } from '../components/MyComponent'
   import { mountIsland } from '../lib/mount-island'

   mountIsland('react-component-root', () => <MyComponent />)
   ```

3. **PHP page** — mount point and scripts:

   ```html
   <div id="react-component-root"></div>
   ```

   ```php
   <?php react_island_scripts('my-component'); ?>
   ```

   For multiple islands on one page:

   ```php
   <?php react_island_scripts('workspace-file-table', 'my-component'); ?>
   ```

4. **See changes** — in dev, hot reload picks up new entries automatically after saving. In prod, restart compose or run `npm run build`.

Files in `src/entries/` whose names start with `_` are skipped by the production build (use as templates).

## Manual commands (without Docker)

```bash
cd react-frontend
npm install
npm run dev            # Vite dev server with hot reload (port 5173)
npm test               # Vitest (src/**/*.test.ts)
npm run build          # one-shot production build
npm run build:watch    # rebuild static bundles on file changes (manual refresh)
```

When using Docker, `npm install <package>` is enough — the `react-frontend` container watches `package.json` / `package-lock.json` and restarts Vite for you.

Production output is written to `front_end/openVRE/public/assets/react/`.

## Tests

Island unit tests use [Vitest](https://vitest.dev/). They mock `fetch`; they do not need Docker, Keycloak, or a logged-in session.

```bash
cd react-frontend
npm test
```

`getUserFiles` and `getTools` always `fetch` `/auth-bff/files` and `/auth-bff/tools`; tests stub `fetch`. For a local UI demo before the live files API exists, set `REACT_ISLAND_USE_FIXTURES=1` and recreate/restart `front_end` so compose picks up the env. AuthBff serves fixture data from `REACT_ISLAND_FIXTURES_PATH` (default: bind-mounted `src/fixtures/workspaceFixtures.json`). GET `/auth-bff/tools` always uses the `tools` section of that file. Add new cases under `tests/**/*.test.ts`.

`npm run check` runs lint, format, TypeScript, and the same tests.

## Related documentation

- [Install.md](../Install.md) — local development setup
- [Install-prod.md](../Install-prod.md) — production deployment
- [openVRE wiki — Extending Frontend Components](https://github.com/inab/openVRE/wiki/Extending-Frontend-Components) — broader UI extension docs (production wiki)
