FROM php:8.2-fpm

# Инсталирајте потребне пакете и PHP екстензије
RUN apt-get update && apt-get install -y \
    zip \
    libonig-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mysqli pdo pdo_mysql zip \
    && docker-php-ext-enable mysqli

# Поставите радни директорјум
WORKDIR /var/www/html