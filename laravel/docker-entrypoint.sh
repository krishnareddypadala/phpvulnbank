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

# -----------------------------------------------------------------------------
# Launch warning.
#
# The app port is published on all host interfaces so the lab is reachable
# across a trusted LAN. That is deliberate -- and it means the warning below
# has to be specific about consequences rather than vaguely cautionary,
# because "be careful" is not actionable and gets ignored.
# -----------------------------------------------------------------------------
# Hand the writable trees back to www-data.
#
# Every artisan command above runs as ROOT (the container's default user), and
# the first one to log creates storage/logs/laravel.log owned root:root. Apache
# workers run as www-data and then cannot append to it, so ANY request that
# logs anything -- a warning, a handled exception -- dies with
# "The stream or file ... could not be opened" and returns 500.
#
# That failure mode is nasty because it is invisible until something logs, and
# the error it produces points at logging rather than at ownership.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

CONTAINER_IPS="$(hostname -i 2>/dev/null || echo 'unknown')"

cat <<BANNER

################################################################################
#                                                                              #
#   PHPVulnBank (Laravel)  --  INTENTIONALLY VULNERABLE TRAINING APPLICATION   #
#                                                                              #
################################################################################

  Listening on port 8090 of EVERY interface on the Docker host.
  Reachable from other machines on your network, by design.

  Container address(es): ${CONTAINER_IPS}
  Students connect to:   http://<THIS-HOST-LAN-IP>:8090
  (run 'ip addr' or 'ipconfig' ON THE HOST to find that address --
   the container cannot see it)

  --------------------------------------------------------------------------
  WHAT ANYONE WHO CAN REACH THIS PORT CAN DO
  --------------------------------------------------------------------------

    * Run arbitrary shell commands as www-data, WITHOUT LOGGING IN.
      Two separate paths: the 'troy' backdoor on the login endpoint
      (VULN-02) and an unauthenticated webshell (VULN-03).
    * Upload a file that then executes as code (VULN-04).
    * Read arbitrary files from this container (VULN-07).
    * Make this host issue requests to your internal network (VULN-08).

    In short: reaching this port is equivalent to shell access on this
    container. Treat a running instance as already compromised.

  --------------------------------------------------------------------------
  WHERE THIS IS OK, AND WHERE IT IS NOT
  --------------------------------------------------------------------------

    OK      an isolated lab VLAN, a classroom or workshop network,
            a home LAN you control

    NEVER   a public IP address
    NEVER   a cloud VM with an open security group
    NEVER   a corporate or shared office network
    NEVER   behind a port-forwarded router
    NEVER   through ngrok, Cloudflare Tunnel or any similar service

    Do not leave it running after the session. Bring it down with:
        docker compose down

  See SECURITY.md.

################################################################################

BANNER

exec apache2-foreground
