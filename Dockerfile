# ============================================================
# Floryn Garden – Production Dockerfile for Railway
# ============================================================
FROM php:8.4-apache

# ── System dependencies ──────────────────────────────────────
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libssl-dev \
    && rm -rf /var/lib/apt/lists/*

# ── PHP extensions ───────────────────────────────────────────
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        intl \
        zip \
        mbstring \
        opcache \
        gd \
        bcmath

# ── Node.js 20 (for Webpack Encore / TailwindCSS build) ──────
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# ── Composer ─────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Apache: enable mod_rewrite + set DocumentRoot to /public ─
RUN a2dismod -f mpm_event mpm_worker \
    && a2enmod mpm_prefork rewrite
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' \
        /etc/apache2/sites-available/000-default.conf \
    && echo '<Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n\
SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1' >> /etc/apache2/sites-available/000-default.conf

# ── PHP production config ─────────────────────────────────────
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN echo "opcache.enable=1\nopcache.memory_consumption=256\nopcache.validate_timestamps=0" \
    >> "$PHP_INI_DIR/php.ini"

# ── Set working directory ────────────────────────────────────
WORKDIR /var/www/html

# ── Production defaults for Docker hosts (Render/Railway) ────
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

# ── Copy project files ───────────────────────────────────────
COPY . .

# ── Install PHP dependencies (production, optimized) ─────────
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --no-scripts

# ── Install Node dependencies & build frontend assets ─────────
RUN npm ci && npm run build

# ── Fix file permissions for Symfony var/ directory ──────────
RUN mkdir -p var/cache var/log public/uploads/flowers \
    && chown -R www-data:www-data var public/uploads \
    && chmod -R 775 var public/uploads

# ── Copy and set entrypoint ───────────────────────────────────
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# ── Expose port 80 ───────────────────────────────────────────
EXPOSE 80

# ── Start via entrypoint ─────────────────────────────────────
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
