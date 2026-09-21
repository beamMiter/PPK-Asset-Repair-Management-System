# Multi-stage build for Laravel (PHP-FPM + node assets)

ARG PHP_VERSION=8.4

# ---------- Stage 1: build the Vite assets ----------
FROM node:20-alpine AS frontend-build
WORKDIR /app
COPY package.json package-lock.json* pnpm-lock.yaml* yarn.lock* ./
RUN if [ -f package-lock.json ]; then npm ci; elif [ -f pnpm-lock.yaml ]; then npm install -g pnpm && pnpm install; elif [ -f yarn.lock ]; then yarn install --frozen-lockfile; else npm install; fi
COPY resources ./resources
COPY app ./app
COPY public ./public
COPY postcss.config.js tailwind.config.js vite.config.js ./
RUN npm run build

# ---------- Stage 2: PHP-FPM runtime ----------
FROM php:${PHP_VERSION}-fpm-alpine AS php-base
# System deps (the -dev packages are needed to compile the extensions below)
RUN apk add --no-cache bash git curl icu-libs icu-dev libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev oniguruma-dev mariadb-client \
    $PHPIZE_DEPS
# PHP extensions. gd has to be told which image formats to support: without these flags it cannot read a JPEG or write a
# WebP, which is what the avatar upload does. pcntl lets `queue:work --timeout` actually stop a job that overruns.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_mysql intl mbstring zip exif gd pcntl
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
COPY --from=frontend-build /app/public/build ./public/build
# The dependencies were installed before app/ was copied in, so the optimised class map has to be built again now
RUN composer dump-autoload --optimize --no-dev --no-scripts
# Generate the package discovery cache (non-fatal in dev)
RUN php artisan package:discover --ansi || true
COPY .docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
# .dockerignore keeps storage/ out of the image (uploads, logs, sessions): create the folders Laravel writes to
RUN mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R laravel:laravel storage bootstrap/cache

USER laravel
ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=1 APP_ENV=production

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
