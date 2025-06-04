# Dockerfile for frontend-web (PHP)
FROM php:8.1-apache

# Apache document root is /var/www/html

# Copy the PHP file into the web root
COPY index.php /var/www/html/index.php

RUN apt-get update && apt-get install -y libpq-dev && docker-php-ext-install pdo pdo_pgsql
# Optional: Install extensions if needed (e.g., curl, pgsql for direct DB access - not needed here)
# RUN docker-php-ext-install curl pdo pdo_pgsql

# Ensure Apache permissions are correct (usually handled by base image)
# RUN chown -R www-data:www-data /var/www/html

# Port 80 is exposed by the base image
