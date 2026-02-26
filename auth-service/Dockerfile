FROM php:8.2-apache
# Install extensions needed to talk to MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql
# Enable Apache mod_rewrite (useful for routing)
RUN a2enmod rewrite