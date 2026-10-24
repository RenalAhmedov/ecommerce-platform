# syntax=docker/dockerfile:1

FROM node:25-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

# Development: docker compose mounts the source over /app and runs watch mode.
FROM deps AS dev
EXPOSE 3000
CMD ["npm", "run", "start:dev"]

FROM deps AS build
COPY . .
RUN npm run build && npm prune --omit=dev

# Production: compiled JavaScript and production dependencies only, as the unprivileged "node" user.
# Migrations: `npm run typeorm -- migration:run` with this image, before starting new instances.
FROM node:25-alpine AS prod
ENV NODE_ENV=production
WORKDIR /app
COPY --from=build --chown=node:node /app/package.json ./
COPY --from=build --chown=node:node /app/node_modules ./node_modules
COPY --from=build --chown=node:node /app/dist ./dist
USER node
EXPOSE 3000
HEALTHCHECK --interval=10s --timeout=3s --start-period=20s --retries=3 \
  CMD wget -qO /dev/null http://127.0.0.1:3000/api/health || exit 1
CMD ["node", "dist/main.js"]
