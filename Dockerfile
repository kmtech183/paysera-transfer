FROM php:8.4-apache

# Install system dependencies
RUN apt-get update && apt-get install -y git curl net-tools unzip libzip-dev libonig-dev libxml2-dev libicu-dev netcat-openbsd && docker-php-ext-install pdo pdo_mysql mbstring zip bcmath intl opcache && pecl install redis && docker-php-ext-enable redis && apt-get clean

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache mod_rewrite (required for Symfony routing)
RUN a2enmod rewrite

# Copy Apache config
COPY .docker/apache.conf /etc/apache2/sites-enabled/000-default.conf

WORKDIR /var/www/html

# 5. Copy and prepare the entrypoint script
#COPY entrypoint.sh /usr/local/bin/entrypoint.sh
#RUN chmod +x /usr/local/bin/entrypoint.sh

# 6. Set the entrypoint
# Changed from CMD to ENTRYPOINT so it triggers correctly on startup
#ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

