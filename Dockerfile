# syntax=docker/dockerfile:1
FROM php:8.3-fpm-bookworm

# --- System packages ---
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# --- PHP extensions ---
# pdo_mysql: data | redis: queue/cache/sessions | pcntl: queue worker signals
# bcmath: budget/cost math | intl, zip, gd, opcache: app + assets
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        bcmath \
        intl \
        zip \
        gd \
        opcache \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis

# --- Composer (copied from the official image) ---
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- PHP config overrides ---
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-outboundengine.ini

WORKDIR /var/www/html

# --- Dependencies first (this layer caches unless composer files change) ---
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist \
        --no-dev --no-scripts --no-autoloader

# --- Application code, then build the optimized autoloader ---
# package:discover is intentionally skipped at build (no .env yet); Laravel
# rebuilds the package manifest at runtime when it's absent.
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

# --- Entrypoint ---
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint
ENTRYPOINT ["entrypoint"]

EXPOSE 9000
CMD ["php-fpm"]
