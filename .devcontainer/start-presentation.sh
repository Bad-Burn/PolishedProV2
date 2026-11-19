#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."
if [[ "${DB_HOST:-}" != db || "${DB_NAME:-}" != polishedpro_presentation ]]; then
    echo 'Start this script inside the PolishedPro presentation container.' >&2
    exit 1
fi

umask 077
runtime=storage/codespaces
mkdir -p "$runtime"

# Prevent a concurrent lifecycle hook or manual restart from seeding twice.
exec 9>"$runtime/start.lock"
flock 9

local_url=http://127.0.0.1:8765
if curl --max-time 2 --fail --silent "$local_url/help" >/dev/null; then
    echo 'PolishedPro is already running on port 8765.'
    exit 0
fi

if [[ ! -s "$runtime/app-key" ]]; then
    php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;' > "$runtime/app-key"
fi
export APP_KEY
APP_KEY=$(cat "$runtime/app-key")

export APP_URL="$local_url"
if [[ -n "${CODESPACE_NAME:-}" && -n "${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-}" ]]; then
    APP_URL="https://${CODESPACE_NAME}-8765.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
fi

php bin/migrate.php
php bin/seed.php
php .devcontainer/presentation-admin.php

nohup php -S 0.0.0.0:8765 -t public bin/router.php >"$runtime/server.log" 2>&1 </dev/null 9>&- &
server=$!
for attempt in {1..30}; do
    if curl --max-time 2 --fail --silent "$local_url/help" >/dev/null; then
        printf 'PolishedPro is ready at %s\n' "$APP_URL"
        exit 0
    fi
    kill -0 "$server" 2>/dev/null || break
    sleep 1
done

echo 'PolishedPro did not start. See storage/codespaces/server.log.' >&2
exit 1
