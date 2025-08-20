# Multi-stage build for REDAXO Modern Structure
ARG PHP_VERSION=8.1
FROM php:${PHP_VERSION}-apache as builder

# Install system dependencies
RUN apt-get update && apt-get install -y \
    curl \
    unzip \
    jq \
    git \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libxpm-dev \
    libzip-dev \
    zlib1g-dev \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Configure GD extension
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-xpm

# Install PHP extensions
RUN docker-php-ext-install \
    gd \
    pdo_mysql \
    zip

# Set working directory
WORKDIR /var/www/html

# Copy setup scripts and configuration files
COPY setup-redaxo.sh install-addons.sh ./
COPY .github/files/ .github/files/

# Make scripts executable
RUN chmod +x setup-redaxo.sh install-addons.sh

# Setup REDAXO base system
RUN ./setup-redaxo.sh

# Install addons (skip for now to test basic setup)
# RUN ./install-addons.sh

# Clean up scripts
RUN rm -f setup-redaxo.sh install-addons.sh

# Production stage
ARG PHP_VERSION=8.1
FROM php:${PHP_VERSION}-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    curl \
    unzip \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libxpm-dev \
    libzip-dev \
    zlib1g-dev \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Configure GD extension
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-xpm

# Install PHP extensions
RUN docker-php-ext-install \
    gd \
    pdo_mysql \
    zip

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy built application from builder stage
COPY --from=builder /var/www/html /var/www/html

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Configure Apache to serve from public directory
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!/var/www/html/public!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Create necessary directories with proper permissions
RUN mkdir -p /var/www/html/var/cache /var/www/html/var/data /var/www/html/public/media \
    && chown -R www-data:www-data /var/www/html/var \
    && chown -R www-data:www-data /var/www/html/public/media \
    && chmod -R 775 /var/www/html/var \
    && chmod -R 775 /var/www/html/public/media

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Start Apache
CMD ["apache2-foreground"]