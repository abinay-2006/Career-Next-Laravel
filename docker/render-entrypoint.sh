#!/bin/sh
set -eu

PORT="${PORT:-10000}"
case "$PORT" in
    ''|*[!0-9]*)
        echo "PORT must be numeric." >&2
        exit 64
        ;;
esac

sed -ri "s/^Listen [0-9]+$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \\*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

if [ "${1:-}" = "--configtest" ]; then
    exec apache2ctl -t
fi

# Build the framework's caches here rather than at image build: Render injects
# the environment into the running container, so a config cache written during
# the build would bake in empty values. Without these, every request reparses
# .env, re-registers the route file and compiles the Blade templates it needs.
#
# Each cache is built separately, so a template that fails to compile costs the
# view cache only - config, routes and events still land.
for command in config:cache route:cache event:cache view:cache; do
    php artisan "$command" || echo "render-entrypoint: ${command} failed; serving without it" >&2
done

exec apache2-foreground "$@"
