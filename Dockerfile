# Official PHP 8.2 image with Apache web server
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Copy all your backend files into the container
COPY . .

# Enable Apache modules for clean URLs (if needed)
RUN a2enmod rewrite

# Expose port 80 (Render will map this automatically)
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]