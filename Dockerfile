FROM php:8.3-apache

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

ENV PORT=10000
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
 && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && printf 'ServerName localhost\n<Directory ${APACHE_DOCUMENT_ROOT}>\n    Options -Indexes\n    AllowOverride All\n</Directory>\n' > /etc/apache2/conf-available/app.conf \
 && a2enconf app \
 && a2enmod rewrite

COPY . /var/www/html/

EXPOSE 10000
