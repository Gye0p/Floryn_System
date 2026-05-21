#!/bin/sh
set -e

echo "==> Starting Floryn Garden entrypoint..."

export APP_ENV=prod
export APP_DEBUG=0
export JWT_SECRET_KEY="${JWT_SECRET_KEY:-%kernel.project_dir%/config/jwt/private.pem}"
export JWT_PUBLIC_KEY="${JWT_PUBLIC_KEY:-%kernel.project_dir%/config/jwt/public.pem}"
export JWT_PASSPHRASE="${JWT_PASSPHRASE:-floryn-production-jwt}"
export DEFAULT_URI="${DEFAULT_URI:-${RAILWAY_PUBLIC_DOMAIN:+https://${RAILWAY_PUBLIC_DOMAIN}}}"
export DEFAULT_URI="${DEFAULT_URI:-http://localhost}"
export MERCURE_URL="${MERCURE_URL:-http://localhost/.well-known/mercure}"
export MERCURE_PUBLIC_URL="${MERCURE_PUBLIC_URL:-${DEFAULT_URI}/.well-known/mercure}"
export MERCURE_JWT_SECRET="${MERCURE_JWT_SECRET:-floryn-mercure-jwt-secret}"

if [ ! -f .env ]; then
    echo "==> Creating minimal production .env file..."
    {
        echo "APP_ENV=${APP_ENV:-prod}"
        echo "APP_DEBUG=${APP_DEBUG:-0}"
        echo "JWT_SECRET_KEY=${JWT_SECRET_KEY}"
        echo "JWT_PUBLIC_KEY=${JWT_PUBLIC_KEY}"
        echo "JWT_PASSPHRASE=${JWT_PASSPHRASE}"
        echo "DEFAULT_URI=${DEFAULT_URI}"
        echo "MERCURE_URL=${MERCURE_URL}"
        echo "MERCURE_PUBLIC_URL=${MERCURE_PUBLIC_URL}"
        echo "MERCURE_JWT_SECRET=${MERCURE_JWT_SECRET}"
    } > .env
fi

if [ -n "$PORT" ]; then
    echo "==> Configuring Apache to listen on port $PORT..."
    sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/g" /etc/apache2/sites-available/000-default.conf
fi

# Generate JWT keys if they don't exist yet
echo "==> Generating JWT keypair..."
php bin/console lexik:jwt:generate-keypair --skip-if-exists

# Clear and warm up Symfony cache for production
echo "==> Clearing cache..."
php bin/console cache:clear --env=prod --no-debug

# Run database migrations
echo "==> Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

echo "==> Startup complete. Starting Apache..."
exec apache2-foreground
