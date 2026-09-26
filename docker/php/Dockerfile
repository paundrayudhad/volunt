FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    libpq-dev libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql pgsql intl zip bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
