FROM php:8.3-apache

RUN a2enmod rewrite

# install composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# set apache root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

COPY . .

# install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

EXPOSE 80