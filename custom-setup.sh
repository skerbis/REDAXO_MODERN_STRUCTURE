#!/bin/bash

# Custom setup script for REDAXO Modern Structure VSCode compatibility
# This script bridges the gap between the VSCode extension expectations
# and the modern structure setup

set -e

echo "🔧 Starting REDAXO Modern Structure setup (VSCode compatible)..."

# Ensure proper permissions
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

# Create necessary directories
mkdir -p /var/www/html/public/media
mkdir -p /var/www/html/var/log
mkdir -p /var/www/html/var/cache

# Set up SSL if enabled
if [ "${SSL_ENABLED}" = "true" ]; then
    echo "🔒 Setting up SSL configuration..."
    
    # Enable SSL module
    a2enmod ssl rewrite headers
    
    # Copy SSL configuration if available
    if [ -f /usr/local/bin/apache-ssl.conf ]; then
        cp /usr/local/bin/apache-ssl.conf /etc/apache2/sites-available/default-ssl.conf
        a2ensite default-ssl
    fi
    
    # Create SSL directories if they don't exist
    mkdir -p /etc/ssl/certs
    mkdir -p /etc/ssl/private
    
    echo "✅ SSL setup completed"
fi

# Configure Apache document root
if [ -n "${APACHE_DOCUMENT_ROOT}" ]; then
    echo "📁 Setting Apache document root to: ${APACHE_DOCUMENT_ROOT}"
    sed -i "s|/var/www/html|${APACHE_DOCUMENT_ROOT}|g" /etc/apache2/sites-available/000-default.conf
    if [ -f /etc/apache2/sites-available/default-ssl.conf ]; then
        sed -i "s|/var/www/html|${APACHE_DOCUMENT_ROOT}|g" /etc/apache2/sites-available/default-ssl.conf
    fi
fi

# Wait for MySQL to be ready
echo "⏳ Waiting for MySQL to be ready..."
while ! mysqladmin ping -h"mysql" -u"${REDAXO_DB_LOGIN}" -p"${REDAXO_DB_PASSWORD}" --silent; do
    echo "Waiting for MySQL..."
    sleep 2
done
echo "✅ MySQL is ready"

# Set up REDAXO if not already configured
if [ ! -f /var/www/html/var/config.yml ]; then
    echo "🎯 Setting up REDAXO configuration..."
    
    # Create basic config structure if needed
    mkdir -p /var/www/html/var
    
    # Set proper file permissions
    chown -R www-data:www-data /var/www/html/var
    chmod -R 775 /var/www/html/var
    
    echo "✅ REDAXO setup prepared"
fi

# Create a flag file to indicate setup completion
touch /var/www/html/.setup-complete

echo "🎉 REDAXO Modern Structure setup completed successfully!"
echo "📍 Instance: ${INSTANCE_NAME:-modern-structure}"
echo "🌐 Frontend: http://localhost:${HTTP_PORT:-8080}"
if [ "${SSL_ENABLED}" = "true" ]; then
    echo "🔒 HTTPS: https://${INSTANCE_NAME:-modern-structure}.local:${HTTPS_PORT:-8443}"
fi
echo "🔧 Backend: http://localhost:${HTTP_PORT:-8080}/redaxo"
echo "🗃️  Database: mysql:3306 (User: ${REDAXO_DB_LOGIN}, Database: ${REDAXO_DB_NAME})"