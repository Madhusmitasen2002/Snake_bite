FROM php:8.2-apache

# Install mysqli extension
RUN docker-php-ext-install mysqli

# Enable mod_rewrite (optional but useful)
RUN a2enmod rewrite

# Copy project
COPY . /var/www/html/

EXPOSE 80