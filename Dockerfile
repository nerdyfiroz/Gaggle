FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql mysqli curl \
    && a2enmod rewrite headers expires \
    && sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \\*:80>/<VirtualHost *:10000>/' /etc/apache2/sites-available/000-default.conf

COPY docker/apache.conf /etc/apache2/conf-available/gaggle.conf
RUN a2enconf gaggle

WORKDIR /var/www/gaggle
COPY . /var/www/gaggle

RUN mkdir -p /var/www/gaggle/storage/logs \
    && chown -R www-data:www-data /var/www/gaggle/storage \
    && chmod -R 775 /var/www/gaggle/storage

ENV APACHE_DOCUMENT_ROOT=/var/www/gaggle/public
EXPOSE 10000

CMD ["apache2-foreground"]
