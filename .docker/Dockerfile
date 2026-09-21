
# Multi-stage build for Laravel (PHP-FPM + node assets)

ARG PHP_VERSION=8.4
FROM node:20-alpine AS frontend-build
WORKDIR /app
COPY package.json package-lock.json* pnpm-lock.yaml* yarn.lock* ./
RUN if [ -f package-lock.json ]; then npm ci; elif [ -f pnpm-lock.yaml ]; then npm install -g pnpm && pnpm install; elif [ -f yarn.lock ]; then yarn install --frozen-lockfile; else npm install; fi
COPY resources ./resources
COPY app ./app
COPY public ./public
COPY postcss.config.js tailwind.config.js vite.config.js ./
RUN npm run build

FROM php:${PHP_VERSION}-fpm-alpine AS php-base
# Install system deps
RUN apk add --no-cache bash git curl icu-libs icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev oniguruma-dev mariadb-client \
    $PHPIZE_DEPS
# PHP extensions
RUN docker-php-ext-configure intl && \
    docker-php-ext-install pdo_mysql intl mbstring zip exif gd
# Install and enable phpredis
RUN pecl install redis && docker-php-ext-enable redis
RUN addgroup -g 1000 laravel && adduser -G laravel -u 1000 -D laravel
WORKDIR /var/www/html
# Copy composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock* ./
# Install dependencies without running scripts (artisan not copied yet)
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts \
    || composer install --no-interaction --prefer-dist --no-scripts
COPY . ./
# Copy built assets if present
COPY --from=frontend-build /app/resources ./resources
COPY --from=frontend-build /app/public/build ./public/build
RUN chown -R laravel:laravel /var/www/html/storage /var/www/html/bootstrap/cache
# Optionally generate package discovery cache if code is baked (non-fatal in dev)
RUN php artisan package:discover --ansi || true
COPY .docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER laravel
ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=1 APP_ENV=production

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

# Nginx separate container uses this code volume in dev

