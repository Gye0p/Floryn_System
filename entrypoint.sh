#!/bin/sh
set -e

echo "==> Starting Floryn Garden entrypoint..."

export APP_ENV=prod
export APP_DEBUG=0
export APP_SECRET="${APP_SECRET:-floryn-production-secret-change-me}"
export JWT_SECRET_KEY="${JWT_SECRET_KEY:-%kernel.project_dir%/config/jwt/private.pem}"
export JWT_PUBLIC_KEY="${JWT_PUBLIC_KEY:-%kernel.project_dir%/config/jwt/public.pem}"
export JWT_PASSPHRASE="${JWT_PASSPHRASE:-floryn-production-jwt}"
export DEFAULT_URI="${DEFAULT_URI:-${RAILWAY_PUBLIC_DOMAIN:+https://${RAILWAY_PUBLIC_DOMAIN}}}"
export DEFAULT_URI="${DEFAULT_URI:-http://localhost}"
export MERCURE_URL="${MERCURE_URL:-http://127.0.0.1:3000/.well-known/mercure}"
export MERCURE_PUBLIC_URL="${MERCURE_PUBLIC_URL:-${DEFAULT_URI}/.well-known/mercure}"
export MERCURE_JWT_SECRET="${MERCURE_JWT_SECRET:-floryn-mercure-jwt-secret}"
export MERCURE_PUBLISHER_JWT_KEY="${MERCURE_PUBLISHER_JWT_KEY:-${MERCURE_JWT_SECRET}}"
export MERCURE_SUBSCRIBER_JWT_KEY="${MERCURE_SUBSCRIBER_JWT_KEY:-${MERCURE_JWT_SECRET}}"
export MERCURE_PUBLISHER_JWT_ALG="${MERCURE_PUBLISHER_JWT_ALG:-HS256}"
export MERCURE_SUBSCRIBER_JWT_ALG="${MERCURE_SUBSCRIBER_JWT_ALG:-HS256}"
export MESSENGER_TRANSPORT_DSN="${MESSENGER_TRANSPORT_DSN:-doctrine://default?auto_setup=0}"
export MAILER_DSN="${MAILER_DSN:-null://null}"
export CORS_ALLOW_ORIGIN="${CORS_ALLOW_ORIGIN:-^https?://.*$}"
export GOOGLE_CLIENT_ID="${GOOGLE_CLIENT_ID:-disabled-google-client-id}"
export GOOGLE_CLIENT_SECRET="${GOOGLE_CLIENT_SECRET:-disabled-google-client-secret}"

if [ ! -f .env ]; then
    echo "==> Creating minimal production .env file..."
    {
        echo "APP_ENV=${APP_ENV:-prod}"
        echo "APP_DEBUG=${APP_DEBUG:-0}"
        echo "APP_SECRET=${APP_SECRET}"
        echo "JWT_SECRET_KEY=${JWT_SECRET_KEY}"
        echo "JWT_PUBLIC_KEY=${JWT_PUBLIC_KEY}"
        echo "JWT_PASSPHRASE=${JWT_PASSPHRASE}"
        echo "DEFAULT_URI=${DEFAULT_URI}"
        echo "MERCURE_URL=${MERCURE_URL}"
        echo "MERCURE_PUBLIC_URL=${MERCURE_PUBLIC_URL}"
        echo "MERCURE_JWT_SECRET=${MERCURE_JWT_SECRET}"
        echo "MESSENGER_TRANSPORT_DSN=${MESSENGER_TRANSPORT_DSN}"
        echo "MAILER_DSN=${MAILER_DSN}"
        echo "CORS_ALLOW_ORIGIN=${CORS_ALLOW_ORIGIN}"
        echo "GOOGLE_CLIENT_ID=${GOOGLE_CLIENT_ID}"
        echo "GOOGLE_CLIENT_SECRET=${GOOGLE_CLIENT_SECRET}"
    } > .env
fi

if [ -n "$PORT" ]; then
    echo "==> Configuring Apache to listen on port $PORT..."
    sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/g" /etc/apache2/sites-available/000-default.conf
fi

echo "==> Ensuring Apache uses a single MPM..."
rm -f /etc/apache2/mods-enabled/mpm_event.load \
    /etc/apache2/mods-enabled/mpm_event.conf \
    /etc/apache2/mods-enabled/mpm_worker.load \
    /etc/apache2/mods-enabled/mpm_worker.conf
a2enmod mpm_prefork rewrite >/dev/null

# Generate JWT keys if they don't exist yet
echo "==> Generating JWT keypair..."
php bin/console lexik:jwt:generate-keypair --skip-if-exists

# Clear and warm up Symfony cache for production
echo "==> Clearing cache..."
php bin/console cache:clear --env=prod --no-debug

# Run database migrations
echo "==> Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

if [ "$RUN_PROD_SEED" = "1" ]; then
    echo "==> Seeding production data..."
    php bin/console app:seed-production --env=prod
fi

echo "==> Starting Mercure hub on :3000..."
/usr/bin/mercure-caddy run --config /etc/mercure/Caddyfile &
MERCURE_PID=$!
sleep 2
if ! kill -0 "$MERCURE_PID" 2>/dev/null; then
    echo "WARNING: Mercure hub failed to start — real-time updates will not work."
else
    echo "==> Mercure hub running (pid $MERCURE_PID)"
fi

echo "==> Startup complete. Starting Apache..."
exec apache2-foreground
