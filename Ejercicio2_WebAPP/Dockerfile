FROM php:8.1-apache

COPY app.php /var/www/html/index.php
RUN apt-get update && apt-get install -y libpq-dev && docker-php-ext-install pdo pdo_pgsql

EXPOSE 80

