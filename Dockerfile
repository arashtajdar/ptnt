FROM serversideup/php:8.4-fpm-nginx

# Switch to root so we can set the user ID and group ID
USER root

# Set the working directory
WORKDIR /var/www/html

# Copy the application code
COPY . .

# Install dependencies
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Fix permissions
RUN chown -R www-data:www-data /var/www/html

# Switch back to the non-root user
USER www-data

# Expose port 8080 (default for this image)
EXPOSE 8080

# Explicitly set the server port to 8080
ENV PHP_SERVER_PORT=8080
