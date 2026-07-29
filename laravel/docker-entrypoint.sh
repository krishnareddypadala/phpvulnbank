#!/bin/sh
# LF line endings required -- see .gitattributes.
set -e

if [ ! -f .env ]; then
    cp .env.example .env
fi

# APP_KEY is generated at container start and never committed. The deliberately
# exposed-.env lesson (VULN-37) is a RUNTIME exposure via the vhost, not a
# committed secret -- see docs/proposed-lessons.md §6.
php artisan key:generate --force --no-interaction

echo "Waiting for MySQL..."
until php -r 'exit(@fsockopen(getenv("DB_HOST") ?: "mysql", 3306) ? 0 : 1);' 2>/dev/null; do
    sleep 2
done

php artisan migrate:fresh --seed --force --no-interaction

echo ""
echo "  PHPVulnBank (Laravel) is up:  http://127.0.0.1:8090"
echo "  INTENTIONALLY VULNERABLE -- localhost only. See SECURITY.md."
echo ""

exec apache2-foreground
