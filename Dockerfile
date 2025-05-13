# Use official PHP image with FPM
FROM php:8.2-fpm

# Install Nginx and necessary extensions
RUN apt-get update && apt-get install -y nginx \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Copy Nginx configuration
COPY nginx.conf /etc/nginx/nginx.conf

# Create and copy PHP script
RUN mkdir -p /var/www/html
COPY index.php /var/www/html/index.php

# copy html files
COPY 401.html /401.html
COPY index.html /index.html

# Set working directory
WORKDIR /var/www/html

# Expose port 80
EXPOSE 80

# Start PHP-FPM and Nginx in the foreground
CMD php-fpm -D && nginx -g "daemon off;"
