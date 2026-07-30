#!/bin/sh
# LF line endings required -- see ../.gitattributes.
#
# Entrypoint for the SINGLE-CONTAINER variant: brings up MariaDB inside this
# container, then the app. See Dockerfile.bundled.
set -e

DB_NAME="bankdb"
DB_USER="groot"
DB_PASS='bose123$'

# ---------------------------------------------------------------------------
# MariaDB
# ---------------------------------------------------------------------------
if [ ! -d /var/lib/mysql/mysql ]; then
    echo "Initialising MariaDB data directory..."
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null 2>&1
fi

chown -R mysql:mysql /var/lib/mysql

echo "Starting MariaDB..."
mysqld_safe --user=mysql >/dev/null 2>&1 &

i=0
until mariadb -uroot -e 'select 1' >/dev/null 2>&1; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then
        echo "MariaDB failed to start."
        exit 1
    fi
    sleep 1
done

# The legacy schema granted this account ALL PRIVILEGES ON *.* WITH GRANT
# OPTION. That is VULN-22, and it is also the foundation of the direct-database
# MCP lesson (VULN-91), so it is reproduced rather than tightened.
mariadb -uroot <<SQL
create database if not exists \`${DB_NAME}\`;
create user if not exists '${DB_USER}'@'%' identified by '${DB_PASS}';
create user if not exists '${DB_USER}'@'localhost' identified by '${DB_PASS}';
grant all privileges on *.* to '${DB_USER}'@'%' with grant option;
grant all privileges on *.* to '${DB_USER}'@'localhost' with grant option;
flush privileges;
SQL

# ---------------------------------------------------------------------------
# Application
# ---------------------------------------------------------------------------
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Written into .env rather than relying on the environment reaching PHP: under
# Apache + mod_php, container environment variables are visible to the CLI but
# are NOT passed to PHP for web requests without an explicit PassEnv. That
# mismatch would have migrations run against MariaDB while the web app quietly
# fell back to the sqlite default in .env.example.
set_env() {
    key="$1"; val="$2"
    [ -z "$val" ] && return 0
    if grep -q "^${key}=" .env; then
        sed -i "s|^${key}=.*|${key}=${val}|" .env
    else
        echo "${key}=${val}" >> .env
    fi
}

set_env APP_ENV "${APP_ENV:-local}"
set_env APP_DEBUG "${APP_DEBUG:-true}"
set_env APP_URL "${APP_URL:-http://localhost:8090}"
set_env DB_CONNECTION "mysql"
set_env DB_HOST "127.0.0.1"
set_env DB_PORT "3306"
set_env DB_DATABASE "${DB_NAME}"
set_env DB_USERNAME "${DB_USER}"
set_env DB_PASSWORD "${DB_PASS}"
set_env PHPVULNBANK_LAB "${PHPVULNBANK_LAB:-1}"

php artisan key:generate --force --no-interaction

echo "Building the lab..."
php artisan migrate:fresh --seed --force --no-interaction

# Hand the writable trees back to www-data. Every artisan command above ran as
# root, and the first one to log creates storage/logs/laravel.log owned
# root:root -- after which Apache workers cannot append to it and ANY request
# that logs anything returns 500. Invisible until something logs, and the error
# points at logging rather than at ownership.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

CONTAINER_IPS="$(hostname -i 2>/dev/null || echo 'unknown')"

cat <<BANNER

################################################################################
#                                                                              #
#   PHPVulnBank (Laravel)  --  INTENTIONALLY VULNERABLE TRAINING APPLICATION   #
#                          single-container build                              #
################################################################################

  Listening on port 80 in this container. If you published it with
  -p 8090:80 then it is on port 8090 of EVERY interface on the host.

  Container address(es): ${CONTAINER_IPS}
  Students connect to:   http://<THIS-HOST-LAN-IP>:8090
  (run 'ip addr' or 'ipconfig' ON THE HOST -- the container cannot see it)

  Web app:      http://<host>:8090
  API docs:     http://<host>:8090/docs
  OpenAPI spec: http://<host>:8090/api/v2/openapi.json
  MCP (HTTP):   POST http://<host>:8090/mcp/api
                POST http://<host>:8090/mcp/db

  Logins:  krishna / happy123\$      admin / krishna1\$

  --------------------------------------------------------------------------
  WHAT ANYONE WHO CAN REACH THIS PORT CAN DO
  --------------------------------------------------------------------------

    * Run arbitrary shell commands as www-data, WITHOUT LOGGING IN.
      Two paths: the 'troy' backdoor on the login endpoint (VULN-02) and
      an unauthenticated webshell (VULN-03).
    * Run arbitrary SQL through the unauthenticated MCP endpoint (VULN-80,
      VULN-90) on a connection that can drop tables.
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

    Do not leave it running after the session.

  If a student drops the database, rebuild it with:
      docker exec <container> php artisan migrate:fresh --seed --force

  See SECURITY.md.

################################################################################

BANNER

exec apache2-foreground
