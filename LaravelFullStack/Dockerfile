# Use the official PHP 8.4 Apache image
FROM php:8.4-apache

# Install PostgreSQL drivers, intl, Zip, Git, AND Node.js
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl zip pdo_pgsql pgsql pdo_mysql

# Enable Apache mod_rewrite for Laravel routing
RUN a2enmod rewrite

# NEW: Create a bulletproof Apache VirtualHost explicitly for Laravel
RUN { \
    echo '<VirtualHost *:80>'; \
    echo '    DocumentRoot /var/www/html/public'; \
    echo '    <Directory /var/www/html/public>'; \
    echo '        AllowOverride All'; \
    echo '        Require all granted'; \
    echo '    </Directory>'; \
    echo '</VirtualHost>'; \
} > /etc/apache2/sites-available/000-default.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy all your Laravel code into the container
COPY . /var/www/html

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install NPM dependencies and build the frontend
RUN npm install && npm run build

# NEW: Fix file permissions for the entire project
# This ensures Apache (www-data) owns the files and has permission to read/execute them
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache
