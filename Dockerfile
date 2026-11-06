# Stage 1: Build frontend assets
FROM node:24-alpine AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
# Keep image builds independent of external Bunny Fonts availability.
ENV VITE_BUNNY_FONTS_DISABLED=1
RUN npm run build

# Shared PHP/Apache runtime base
FROM php:8.3-apache AS php-runtime-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libicu-dev libxml2-dev libonig-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
    pdo_mysql mbstring bcmath exif pcntl gd intl zip opcache \
    && a2enmod rewrite headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache-private-uploads.conf /etc/apache2/conf-available/private-uploads.conf
RUN a2enconf private-uploads
COPY docker/apache-static-cache.conf /etc/apache2/conf-available/static-cache.conf
RUN a2enconf static-cache

# Read after docker-php-ext-opcache.ini, which only loads the extension.
COPY docker/php-opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Configure Apache to serve Laravel's public directory and set port
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    PORT=10000

RUN sed -ri \
    's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/000-default.conf \
    && sed -ri \
    's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/apache2.conf

COPY docker/render-entrypoint.sh /usr/local/bin/render-entrypoint
RUN chmod 755 /usr/local/bin/render-entrypoint

# Assemble the application from a build context that excludes private legacy
# documents. Private documents must be provisioned on the configured private
# runtime disk, never copied into an image layer.
FROM php-runtime-base AS app-assembly
WORKDIR /var/www/html
COPY . .

RUN set -eu; \
    export COMPOSER_MAX_PARALLEL_HTTP=4; \
    for attempt in 1 2 3; do \
        if composer install \
            --no-dev \
            --prefer-dist \
            --no-interaction \
            --no-progress \
            --optimize-autoloader; then \
            break; \
        fi; \
        if [ "$attempt" -eq 3 ]; then \
            echo "Composer install failed after 3 attempts." >&2; \
            exit 1; \
        fi; \
        echo "Composer install failed; retrying in $((attempt * 10)) seconds..." >&2; \
        sleep $((attempt * 10)); \
    done

COPY --from=frontend /app/public/build ./public/build

# Start the final image from the clean PHP/Apache base. No stage containing
# private source documents is an ancestor of this image.
FROM php-runtime-base AS runtime
WORKDIR /var/www/html
COPY --from=app-assembly --chown=www-data:www-data /var/www/html /var/www/html
RUN mkdir -p \
    storage/app/public \
    storage/app/private \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && php artisan storage:link \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 10000
CMD ["/usr/local/bin/render-entrypoint"]
