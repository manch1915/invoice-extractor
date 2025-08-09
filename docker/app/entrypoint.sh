#!/bin/sh
set -e

# Only the web container prepares the database; horizon and scheduler start after it is healthy.
if [ "${CONTAINER_ROLE:-web}" = "web" ]; then
    php artisan migrate --force --no-interaction

    if [ "$(php artisan tinker --execute 'echo App\Models\User::count();' 2>/dev/null)" = "0" ]; then
        php artisan db:seed --force --no-interaction
        if [ "${DEMO_MODE:-false}" = "true" ]; then
            # reading 41 documents takes a few minutes; serve the app meanwhile
            echo "Loading the sample documents in the background"
            php artisan demo:seed --no-interaction > storage/logs/demo-seed.log 2>&1 &
        fi
    fi
fi

php artisan optimize --no-interaction

exec "$@"
