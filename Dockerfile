FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install zip \
    && docker-php-ext-install mysqli \
    && a2enmod rewrite headers

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Apache vhost config
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# Create upload directories and set permissions
RUN mkdir -p /var/www/html/assets/uploads/banhkem \
    /var/www/html/assets/uploads/banhman \
    /var/www/html/assets/uploads/banhmi \
    /var/www/html/assets/uploads/banhngot \
    /var/www/html/pages/uploads \
    && chown -R www-data:www-data /var/www/html/assets/uploads /var/www/html/pages/uploads \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
