FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql
COPY public/ /var/www/html/
COPY src/ /var/www/src/
RUN a2enmod rewrite
