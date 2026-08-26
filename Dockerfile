FROM php:8.1-apache
RUN apt-get update && apt-get install -y libzip-dev unzip libpq-dev \
    && docker-php-ext-install zip pdo pdo_pgsql pgsql
RUN a2enmod rewrite
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html
EXPOSE 80
CMD ["apache2-foreground"]
