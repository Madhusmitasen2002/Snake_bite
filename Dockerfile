FROM php:8.2-apache

# Install required packages + mysqli
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install mysqli

# Enable rewrite
RUN a2enmod rewrite

# Copy project
COPY . /var/www/html/

# Set working dir
WORKDIR /var/www/html

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN composer install --no-dev --optimize-autoloader

EXPOSE 80