# Dockerfile — PHP 8.2 + PostgreSQL on Render free tier
# Build:   docker build -t eserbisyo-hub .
# Local run: docker run -p 8080:8080 --env DATABASE_URL=... eserbisyo-hub

FROM php:8.2-apache

# Install the PostgreSQL PDO driver (this is the key piece — base image has MySQL but not pgsql)
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev git unzip \
 && docker-php-ext-install pdo pdo_pgsql pgsql \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Render's free tier expects the app to listen on $PORT (defaults to 10000).
# Apache listens on 80 by default — rewrite both Listen and the vhost to use $PORT.
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -i 's/:80>/:${PORT}>/'         /etc/apache2/sites-available/000-default.conf

# Enable mod_rewrite (used by some kiosk routing) and allow .htaccess overrides.
RUN a2enmod rewrite \
 && printf '<Directory /var/www/html>\n    AllowOverride All\n</Directory>\n' \
    > /etc/apache2/conf-available/override.conf \
 && a2enconf override

WORKDIR /var/www/html

# Install PHP deps first (cached layer)
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

# Copy the rest of the project
COPY . .

# Apache must own the docroot
RUN chown -R www-data:www-data /var/www/html

# Render injects $PORT; default to 10000 if running locally
ENV PORT=10000
EXPOSE 10000

CMD ["apache2-foreground"]
