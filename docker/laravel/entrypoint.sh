#!/bin/sh
set -eu

# Prepare writable runtime directories.
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs

# Drop any configuration cached on the build host before we start.
php artisan config:clear >/dev/null 2>&1 || true

exec "$@"