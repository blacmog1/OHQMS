# 1. Get a standard PHP server with Apache
FROM php:8.2-apache

# 2. Install the PostgreSQL drivers so your cloud database connects
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Remove db.php if it exists locally to avoid compiling local credentials
RUN rm -f config/db.php

# Make the entrypoint script executable
RUN chmod +x docker-entrypoint.sh

# Expose port (Render sets $PORT dynamically, entrypoint will adjust Apache)
EXPOSE 80

# Run entrypoint script
ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
