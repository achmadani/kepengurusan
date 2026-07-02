#!/usr/bin/env bash
#
# Start the organization-structure app.
# Uses PHP 8.2 built-in server. MySQL must be running on port 3308.
#
set -e

PHP_BIN="/opt/homebrew/opt/php@8.2/bin/php"
HOST="127.0.0.1"
PORT="${1:-8099}"

cd "$(dirname "$0")"

echo "==> Struktur Organisasi"
echo "    PHP     : $($PHP_BIN -v | head -1)"
echo "    URL     : http://$HOST:$PORT"
echo "    Login   : admin / admin"
echo "    (Ctrl+C untuk berhenti)"
echo

exec "$PHP_BIN" -S "$HOST:$PORT" router.php
