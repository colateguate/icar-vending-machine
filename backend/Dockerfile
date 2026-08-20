# syntax=docker/dockerfile:1

# The API in one container, and deliberately one: FrankenPHP is a single
# process that speaks HTTP and runs PHP, so there is no FPM socket to wire to
# an nginx and no supervisor to keep two daemons alive. Fewer moving parts is
# worth more here than familiarity — whoever evaluates this runs one command
# and reads one Dockerfile.
#
# Both stages start from the same base on purpose. Installing dependencies with
# a different PHP than the one that will run them is how a lock file resolves
# to something the runtime cannot load.

FROM dunglas/frankenphp:1-php8.2-alpine AS base

# Without opcache a single-process server re-parses the whole framework on
# every request, which it cannot afford. intl is not used by this application,
# but Symfony logs a notice on every boot without it, and a log line nobody can
# act on is a log line people learn to skip.
RUN install-php-extensions opcache intl

# The suite talks to SQLite, and so does production (ADR-0008). It comes with
# the official images rather than being installed here, which is exactly why
# this line exists: if a base image ever drops it, the build fails now instead
# of the container failing at the first request.
RUN php -m | grep -qx pdo_sqlite || (echo 'pdo_sqlite is missing from this base image' && exit 1)

WORKDIR /app


# ---------------------------------------------------------------------------
# Dependencies. Separated so that editing a PHP file does not re-run composer:
# the manifests are copied alone first, and Docker reuses the cached layer for
# every build that does not change them.
# ---------------------------------------------------------------------------
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock symfony.lock ./

# --no-dev: PHPUnit, PHPStan and Infection have no business in a runtime image.
# --no-scripts: the Symfony scripts warm a cache that needs the application,
# which is not here yet — the cache is warmed in the runtime stage instead.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .

# Authoritative classmap: the autoloader stops looking at the filesystem, which
# is both faster and a guarantee that nothing unexpected is loadable.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative


# ---------------------------------------------------------------------------
# Runtime.
# ---------------------------------------------------------------------------
FROM base AS runtime

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    # A port and no hostname is how FrankenPHP is told to serve plain HTTP.
    # Left to itself it listens on 443 and provisions a local certificate
    # authority, which is the right default for a server on the internet and
    # the wrong one for a container behind whatever terminates TLS in front of
    # it — here, nothing, because this is an evaluation stack.
    SERVER_NAME=:8080 \
    # Caddy keeps a configuration API on localhost:2019 so a running server can
    # be rewired without restarting it. Nothing here does that, and a door that
    # can rewrite the routing table is worth closing rather than trusting: it is
    # the obvious second step for anyone who found a way to run code inside the
    # container. The base Caddyfile forwards this into its global block.
    CADDY_GLOBAL_OPTIONS="admin off"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# The php.ini the official images ship for production: display_errors off,
# opcache on, and the rest of the defaults you would otherwise discover one
# incident at a time. It is a RUN and not a COPY because it is already inside
# the image — COPY reads from the build context.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=vendor /app /app
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

# The container serves requests; it does not need to write to its own source.
# The two directories it does write to are its cache and its database, and the
# database one is where the named volume lands: creating it here with the right
# owner is what makes the volume inherit that ownership instead of root's.
RUN set -eux; \
    chmod +x /usr/local/bin/entrypoint; \
    mkdir -p var/cache var/log var/data; \
    php bin/console cache:warmup --env=prod --no-debug; \
    chown -R www-data:www-data var; \
    chmod -R 555 /app; \
    # Frozen, and then var/ thawed again: it is the only subtree the running
    # process writes to, and everything else being read-only is the point.
    chmod -R 755 var; \
    # /data and /config belong to FrankenPHP rather than to the application,
    # and it writes to both while running. They sit outside /app, so the chown
    # above does not reach them.
    chown -R www-data:www-data /data /config

USER www-data

EXPOSE 8080

# The healthcheck is the endpoint the API already exposes for it, and compose
# depends on it: nothing that needs the API starts before this answers.
#
# wget rather than curl, and for a smaller reason than it looks: BusyBox
# already provides it, so the check needs no install step, and --spider behaves
# the way --fail does — zero on 2xx, non-zero on anything else.
#
# Not for attack surface. The base image ships curl regardless, so removing the
# install removes a layer, not a binary.
HEALTHCHECK --interval=10s --timeout=3s --start-period=20s --retries=5 \
    CMD wget --spider --quiet http://127.0.0.1:8080/api/health || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
