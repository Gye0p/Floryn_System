#!/bin/sh
set -e

echo "==> Starting Floryn Garden entrypoint..."

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
