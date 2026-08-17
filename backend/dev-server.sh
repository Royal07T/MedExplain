#!/usr/bin/env bash
#
# Local dev server for the MedExplain API.
#
# `php artisan serve` spawns the built-in PHP server as a child process that
# ignores CLI php.ini overrides — meaning files larger than PHP's default
# `upload_max_filesize` (2M) get rejected with "The file failed to upload."
#
# This runs the built-in server directly with 10M upload limits applied to the
# request-handling process, matching the application's 10 MB upload limit.

set -euo pipefail

cd "$(dirname "$0")"

PORT="${PORT:-8000}"

exec php \
    -d upload_max_filesize=10M \
    -d post_max_size=12M \
    -S "127.0.0.1:${PORT}" \
    -t public \
    public/index.php