FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install mysqli

# 🔥 FORCE FIX MPM
RUN rm -f /etc/apache2/mods-enabled/mpm_* \
 && a2enmod mpm_prefork

RUN a2enmod rewrite

COPY . /var/www/html/
WORKDIR /var/www/html

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

EXPOSE 80