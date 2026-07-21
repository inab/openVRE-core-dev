FROM node:22-alpine

ENV CI=true

RUN corepack enable && corepack prepare pnpm@11.13.0 --activate \
  && mkdir -p /pnpm/store

WORKDIR /workspace/react-frontend

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml .npmrc ./
RUN pnpm install --frozen-lockfile

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
COPY docker-dev.sh /usr/local/bin/docker-dev.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh /usr/local/bin/docker-dev.sh

ENTRYPOINT ["docker-entrypoint.sh"]
