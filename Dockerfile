# Official PHP 8.2 image with Apache web server
FROM php:8.2-apache

# Install MySQL extensions required by the project
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Set working directory
WORKDIR /var/www/html

# Copy all backend files into the container
COPY . .

# Enable Apache rewrite module
RUN a2enmod rewrite

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
