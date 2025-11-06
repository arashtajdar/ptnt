# Stage 1: Build PHP backend
FROM php:8.3-fpm AS backend

WORKDIR /var/www

# Install dependencies
RUN apt-get update && apt-get install -y \
    git unzip libpng-dev libjpeg-dev libfreetype6-dev libzip-dev zip curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd mbstring zip exif pcntl opcache

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy and install Laravel dependencies
COPY . .
RUN composer install --no-dev --optimize-autoloader \
    && php artisan key:generate \
    && chown -R www-data:www-data storage bootstrap/cache

# Stage 2: Nginx serving PHP-FPM
FROM nginx:stable-alpine

# Copy Nginx configuration
COPY ./nginx.conf /etc/nginx/conf.d/default.conf

# Copy application files from backend stage
COPY --from=backend /var/www /var/www

# Expose port 80 for Railway
EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
