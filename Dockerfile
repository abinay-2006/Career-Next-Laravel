
# Build the frontend assets
FROM node:24-alpine AS frontend

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .
RUN npm run build


# Build the Laravel application runtime
FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libicu-dev libxml2-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
       pdo_mysql mbstring bcmath exif pcntl gd intl zip opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

# Install production PHP dependencies
RUN composer install --no-dev --prefer-dist \
    --no-interaction --optimize-autoloader

# Copy compiled Vite assets into Laravel public directory
COPY --from=frontend /app/public/build ./public/build

# Serve Laravel from its public directory
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri \
    -e 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/000-default.conf \
    && sed -ri \
    -e 's!/var/www/!/var/www/public!g' \
    /etc/apache2/apache2.conf \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 80

CMD ["apache2-foreground"]