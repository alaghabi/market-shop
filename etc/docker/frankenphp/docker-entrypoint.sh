#!/bin/sh
set -eu

# Worker/cron container: supervisord is PID 1 and autostarts cron + messengers.
if [ "${CONTAINER_ROLE:-}" = "supervisor" ] || [ "${1:-}" = "supervisord" ]; then
  exec supervisord -c /etc/supervisor/supervisord.conf
fi

# One-off CLI (migrations, cache:clear, seeds) — do not start the web server.
case "${1:-}" in
  php|composer|bin/console)
    exec "$@"
    ;;
esac

# Web container: keep FrankenPHP base-image behavior.
if [ "$#" -eq 0 ]; then
  set -- --config /etc/frankenphp/Caddyfile --adapter caddyfile
fi

exec docker-php-entrypoint "$@"
