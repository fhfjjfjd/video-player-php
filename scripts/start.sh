#!/usr/bin/env bash
#
# videohub - start script for the video-player-php app (Unix / macOS / Termux).
#
# Launches PHP's built-in web server with index.php as the router script.
#   HOST=127.0.0.1 PORT=3000 PHP_WORKERS=4
#
# With multiple PHP worker processes one viewer's stream never blocks another.
#
set -euo pipefail

cd "$(dirname "$0")/.."

HOST="${HOST:-127.0.0.1}"
PORT="${PORT:-3000}"
WORKERS="${PHP_WORKERS:-4}"

command -v php >/dev/null 2>&1 || {
    echo "[videohub ERROR] PHP not found. Install PHP (>= 8.1) with pdo_sqlite and re-run." >&2
    exit 1
}

echo "[videohub] Starting Video Player on http://${HOST}:${PORT} (workers: ${WORKERS})"
PHP_CLI_SERVER_WORKERS="$WORKERS" exec php -S "${HOST}:${PORT}" index.php
