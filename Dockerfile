# Use official PHP image with FPM
FROM php:8.2-fpm

# Install Nginx and necessary extensions
RUN apt-get update && apt-get install -y nginx \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure PHP settings for larger uploads
RUN echo "upload_max_filesize = 2000M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 2000M" >> /usr/local/etc/php/conf.d/uploads.ini

# Copy Nginx configuration
COPY nginx.conf /etc/nginx/nginx.conf

# Create and copy PHP script
RUN mkdir -p /var/www/html
COPY index.php /var/www/html/index.php

# copy html files
COPY 401.html /401.html
COPY 404.html /404.html
COPY 410.html /410.html
COPY index.html /index.html

# Set working directory
WORKDIR /var/www/html

# Expose port 80
EXPOSE 80

# Start PHP-FPM and Nginx in the foreground
CMD php-fpm -D && nginx -g "daemon off;"
