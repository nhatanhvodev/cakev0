FROM php:8.2-apache

RUN docker-php-ext-install mysqli \
    && a2enmod rewrite headers

WORKDIR /var/www/html

COPY . /var/www/html
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

RUN mkdir -p /var/www/html/assets/uploads/banhkem \
    /var/www/html/assets/uploads/banhman \
    /var/www/html/assets/uploads/banhmi \
    /var/www/html/assets/uploads/banhngot \
    /var/www/html/pages/uploads \
    && chown -R www-data:www-data /var/www/html/assets/uploads /var/www/html/pages/uploads

EXPOSE 80
