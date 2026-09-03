#!/bin/sh

# A Railway service can start before its linked MySQL service accepts
# connections. Retry migrations for a short, bounded period instead of
# immediately crashing the web container.
attempt=1
max_attempts=30

until php artisan migrate --force; do
    if [ "$attempt" -ge "$max_attempts" ]; then
        echo "Database migrations failed after ${max_attempts} attempts. Check DB_* Railway variables and the MySQL service status." >&2
        exit 1
    fi

    echo "Database is not ready; retrying migrations in 5 seconds (${attempt}/${max_attempts})..." >&2
    attempt=$((attempt + 1))
    sleep 5
done

exec php -S "0.0.0.0:${PORT:-8080}" -t public
