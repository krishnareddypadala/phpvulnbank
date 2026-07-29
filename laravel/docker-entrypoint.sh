#!/bin/sh
# LF line endings required -- see ../.gitattributes.
set -e

if [ ! -f .env ]; then
    cp .env.example .env
fi

# Write configuration INTO .env rather than relying on the container's
# environment reaching PHP.
#
# Under Apache + mod_php, variables set by `docker compose environment:` are
# visible to the CLI (so `php artisan migrate` would see them) but are NOT
# passed through to PHP for web requests unless Apache is configured with
# PassEnv for each one. That mismatch is nasty: migrations run against MySQL
# while the web app quietly falls back to the sqlite default in .env.example.
set_env() {
    key="$1"
    val="$2"
    [ -z "$val" ] && return 0
    if grep -q "^${key}=" .env; then
        # `|` as the delimiter so URLs and passwords do not need escaping.
        sed -i "s|^${key}=.*|${key}=${val}|" .env
    else
        echo "${key}=${val}" >> .env
    fi
}

set_env APP_ENV "${APP_ENV}"
set_env APP_DEBUG "${APP_DEBUG}"
set_env APP_URL "${APP_URL}"
set_env DB_CONNECTION "${DB_CONNECTION}"
set_env DB_HOST "${DB_HOST}"
set_env DB_PORT "${DB_PORT}"
set_env DB_DATABASE "${DB_DATABASE}"
set_env DB_USERNAME "${DB_USERNAME}"
set_env DB_PASSWORD "${DB_PASSWORD}"

# APP_KEY is generated at container start and never committed. The deliberately
# exposed-.env lesson (VULN-37) is a RUNTIME exposure via the vhost, not a
# committed secret -- see docs/proposed-lessons.md §6.
php artisan key:generate --force --no-interaction

# An open port is not the same as MySQL being ready to authenticate -- the
# official image opens 3306 during its own bootstrap, well before it will
# accept a connection. Retry the migration rather than racing it.
echo "Waiting for MySQL and building the lab..."
i=0
until php artisan migrate:fresh --seed --force --no-interaction 2>/dev/null; do
    i=$((i + 1))
    if [ "$i" -ge 40 ]; then
        echo "MySQL did not become ready in time. Last attempt:"
        php artisan migrate:fresh --seed --force --no-interaction
        exit 1
    fi
    sleep 3
done

echo ""
echo "  PHPVulnBank (Laravel) is up:  http://127.0.0.1:8090"
echo "  INTENTIONALLY VULNERABLE -- localhost only. See SECURITY.md."
echo ""

exec apache2-foreground
