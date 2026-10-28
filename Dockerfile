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
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

# Install Laravel production dependencies
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

# Make files stored on the public disk reachable through /storage.
RUN mkdir -p storage/app/public && php artisan storage:link

# Copy compiled frontend assets
COPY --from=frontend /app/public/build ./public/build

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

# Assemble and sanitize legacy content in a throwaway stage. The final image
# copies the sanitized snapshot and does not inherit this stage's file layers.
FROM php-runtime-base AS app-assembly
WORKDIR /var/www/html
COPY . .

RUN mkdir -p storage/app/private/upload storage/app/private/uploads/documents \
    && cp public/upload/1789573805_2_No_due_-_IV.pdf storage/app/private/upload/ \
    && cp public/uploads/documents/1776426982_69e21fe6680e7.pdf storage/app/private/uploads/documents/ \
    && cp public/uploads/documents/1784988802_VAt6XUfX.pdf storage/app/private/uploads/documents/ \
    && cp public/uploads/documents/Resume.pdf storage/app/private/uploads/documents/ \
    && rm public/upload/1789573805_2_No_due_-_IV.pdf \
        public/uploads/documents/1776426982_69e21fe6680e7.pdf \
        public/uploads/documents/1784988802_VAt6XUfX.pdf \
        public/uploads/documents/Resume.pdf

RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader \
    && mkdir -p storage/app/public \
    && php artisan storage:link

COPY --from=frontend /app/public/build ./public/build
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

FROM php-runtime-base AS runtime
WORKDIR /var/www/html
COPY --from=app-assembly --chown=www-data:www-data /var/www/html /var/www/html

EXPOSE 10000
CMD ["/usr/local/bin/render-entrypoint"]
