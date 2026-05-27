FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    && docker-php-ext-install pdo pdo_mysql mysqli

RUN a2dismod mpm_event mpm_worker && a2enmod mpm_prefork rewrite headers

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
