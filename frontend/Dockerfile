# syntax=docker/dockerfile:1
# ---------------------------------------------------------------------------
# MOOC Platform — Next.js frontend image (App Router, RTL, ar/en).
# ---------------------------------------------------------------------------
# Two stages: build the production bundle, then run it with `next start`.
# NEXT_PUBLIC_API_BASE is a *build-time* value (Next inlines NEXT_PUBLIC_*
# into the client bundle), so it must be supplied as a build arg.
# ---------------------------------------------------------------------------
FROM node:20-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
ARG NEXT_PUBLIC_API_BASE=http://localhost:8080/api/v1
ENV NEXT_PUBLIC_API_BASE=$NEXT_PUBLIC_API_BASE
ENV NEXT_TELEMETRY_DISABLED=1
RUN npm run build

FROM node:20-alpine
WORKDIR /app
ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
COPY --from=build /app/package.json ./package.json
COPY --from=build /app/node_modules ./node_modules
COPY --from=build /app/.next ./.next
COPY --from=build /app/public ./public
COPY --from=build /app/next.config.mjs ./next.config.mjs
EXPOSE 3000
CMD ["npm", "run", "start"]
