FROM php:8.1-apache

RUN apt-get update && apt-get install -y libpq-dev && docker-php-ext-install mysqli && docker-php-ext-enable mysqli

COPY index.php /var/www/html/
