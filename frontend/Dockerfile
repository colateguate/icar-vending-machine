# syntax=docker/dockerfile:1

# The panel in two stages: Node builds a bundle, nginx serves it. Node does not
# survive into the runtime image and neither does anything in node_modules —
# what ships is a directory of static files and a web server, which is all a
# built SPA is.
#
# nginx is here for a second job as well, and it is the one that shaped this
# file: it reverse-proxies /api to the backend so the panel and the API share
# an origin. docker/nginx.conf explains why that beats widening CORS.

# ---------------------------------------------------------------------------
# Build.
# ---------------------------------------------------------------------------
FROM node:22-alpine AS build

WORKDIR /app

# The manifests alone first, so that editing a component does not re-run the
# install: Docker reuses this layer for every build that leaves them untouched.
COPY package.json package-lock.json ./

# `ci` and not `install`: it builds from the lock file and fails if the two
# manifests disagree, rather than resolving something new and quietly writing a
# different lock. A build that can change what it is building is not a build.
RUN npm ci

COPY . .

# The panel asks for a relative /api by default and needs no address at all
# behind the proxy below. This exists for the deployment that genuinely serves
# the API from somewhere else — httpClient.js has the override, and a Dockerfile
# that did not expose it would make that escape hatch unreachable from an image.
#
# Passed through only when it has a value. Vite freezes VITE_* into the bundle
# at build time, and an empty string is not the same as an absent one: the
# client falls back with `??`, which catches undefined and lets "" through.
ARG VITE_API_URL
RUN if [ -n "$VITE_API_URL" ]; then \
        VITE_API_URL="$VITE_API_URL" npm run build; \
    else \
        npm run build; \
    fi


# ---------------------------------------------------------------------------
# Runtime.
# ---------------------------------------------------------------------------
# nginx's own unprivileged build. The stock image runs its master process as
# root so it can bind port 80, and the backend next door already goes to the
# trouble of dropping to www-data on a high port — two containers in one stack
# should not disagree about whether that is worth doing.
FROM nginxinc/nginx-unprivileged:1-alpine AS runtime

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 8080

# Asks nginx for the document it exists to serve. A container that is running
# is not the same as one that is serving: a bad configuration leaves the
# process up and every request refused, and compose would call that healthy.
#
# wget rather than curl for the same reason as the backend's: BusyBox already
# provides it, so the check costs no install step.
HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=5 \
    CMD wget --spider --quiet http://127.0.0.1:8080/ || exit 1
