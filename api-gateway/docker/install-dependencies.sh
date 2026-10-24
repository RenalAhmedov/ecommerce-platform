#!/bin/sh
# node_modules is a Docker volume shared by the development containers. It is
# reinstalled only when package-lock.json changed since the last install, so a
# fresh clone and a pulled dependency change both just work, and every other
# start skips the install. Then runs the given command.
set -e

lock_hash=$(sha256sum package-lock.json | cut -d ' ' -f 1)
marker=node_modules/.package-lock.sha256

if [ "$(cat "$marker" 2>/dev/null)" != "$lock_hash" ]; then
  echo "package-lock.json changed: installing dependencies"
  npm ci --no-audit --no-fund
  echo "$lock_hash" > "$marker"
fi

exec "$@"
