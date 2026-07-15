FROM node:22-alpine

WORKDIR /workspace/react-frontend

COPY package.json package-lock.json ./
RUN npm ci

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
COPY docker-dev.sh /usr/local/bin/docker-dev.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh /usr/local/bin/docker-dev.sh

ENTRYPOINT ["docker-entrypoint.sh"]
