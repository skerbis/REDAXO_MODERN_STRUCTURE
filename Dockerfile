# Multi-stage build for REDAXO Modern Structure
FROM php:8.1-apache as builder

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

# Copy configuration files
COPY .github/files/ .github/files/

# Build REDAXO structure (replicating the GitHub workflow)
RUN set -eux; \
    # Get latest REDAXO release
    REDAXO_VERSION=$(curl -s "https://api.github.com/repos/redaxo/redaxo/releases/latest" | jq -r ".tag_name"); \
    echo "Building REDAXO version: $REDAXO_VERSION"; \
    \
    # Create temporary directories
    mkdir -p tmp/redaxo; \
    \
    # Download REDAXO
    curl -Ls "https://github.com/redaxo/redaxo/releases/download/$REDAXO_VERSION/redaxo_$REDAXO_VERSION.zip" -o "tmp/redaxo/redaxo_$REDAXO_VERSION.zip"; \
    \
    # Unzip REDAXO
    unzip "tmp/redaxo/redaxo_$REDAXO_VERSION.zip" -d public; \
    \
    # Create Yakamara file structure
    mkdir -p var src; \
    mv public/redaxo/bin/ bin/; \
    mv public/redaxo/cache/ var/cache/; \
    mv public/redaxo/data/ var/data/; \
    mv public/redaxo/src/addons/ src/addons/; \
    mv public/redaxo/src/core/ src/core/; \
    mv public/LICENSE.md LICENSE.md || true; \
    rm -f public/README.md public/README.de.md; \
    \
    # Copy setup files
    mkdir -p src/addons/project; \
    cp .github/files/addon.project.boot.php src/addons/project/boot.php; \
    cp .github/files/console bin/console; \
    chmod +x bin/console; \
    cp .github/files/index.backend.php public/redaxo/index.php; \
    cp .github/files/index.frontend.php public/index.php; \
    cp .github/files/AppPathProvider.php src/AppPathProvider.php; \
    \
    # Download and Install Addons
    while IFS= read -r line; do \
        if [[ -z "$line" || "$line" =~ ^# ]]; then \
            continue; \
        fi; \
        \
        ADDON_URL=$(echo "$line" | awk '{print $1}'); \
        TARGET_DIR=$(echo "$line" | awk '{print $2}'); \
        \
        echo "Installing addon: $ADDON_URL"; \
        \
        ADDON_REPO_NAME=$(echo "$ADDON_URL" | sed -E 's/.*\///g'); \
        REPO_OWNER=$(echo "$ADDON_URL" | sed -E 's/https:\/\/api.github.com\/repos\///g' | cut -d '/' -f1); \
        REPO_NAME=$(echo "$ADDON_URL" | sed -E 's/https:\/\/api.github.com\/repos\///g' | cut -d '/' -f2); \
        \
        RELEASE_URL="$ADDON_URL/releases/latest"; \
        RELEASE_DATA=$(curl -s -H 'User-Agent: PHP' "$RELEASE_URL"); \
        ZIP_URL=$(echo "$RELEASE_DATA" | jq -r '.zipball_url'); \
        \
        if [[ "$ZIP_URL" == "null" || -z "$ZIP_URL" ]]; then \
            ZIP_URL="https://github.com/$REPO_OWNER/$REPO_NAME/archive/refs/heads/master.zip"; \
            HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$ZIP_URL"); \
            if [[ "$HTTP_STATUS" != "200" ]]; then \
                ZIP_URL="https://github.com/$REPO_OWNER/$REPO_NAME/archive/refs/heads/main.zip"; \
            fi; \
        fi; \
        \
        if [[ -z "$ZIP_URL" || "$ZIP_URL" == "null" ]]; then \
            echo "ERROR: Could not determine download URL for $ADDON_REPO_NAME"; \
            continue; \
        fi; \
        \
        mkdir -p tmp; \
        curl -Ls "$ZIP_URL" -o "tmp/$ADDON_REPO_NAME.zip"; \
        \
        if [ ! -f "tmp/$ADDON_REPO_NAME.zip" ]; then \
            echo "ERROR: Failed to download zip file for $ADDON_REPO_NAME"; \
            continue; \
        fi; \
        \
        unzip -q "tmp/$ADDON_REPO_NAME.zip" -d tmp; \
        \
        ADDON_DIR=$(find tmp -maxdepth 1 -type d -name "*${REPO_NAME}*" | grep -v "^tmp$" | head -1); \
        \
        if [ -z "$ADDON_DIR" ]; then \
            ADDON_DIR=$(find tmp -maxdepth 1 -type d | grep -v "^tmp$" | head -1); \
        fi; \
        \
        if [[ -n "$ADDON_DIR" ]]; then \
            if [[ -n "$TARGET_DIR" ]]; then \
                ADDON_NAME="$TARGET_DIR"; \
            elif [[ -f "$ADDON_DIR/package.yml" ]]; then \
                ADDON_NAME=$(grep -m 1 "^package:" "$ADDON_DIR/package.yml" | sed -E 's/package:\s*//g' || true); \
                if [[ -z "$ADDON_NAME" ]]; then \
                    ADDON_NAME=$(grep -m 1 "^name:" "$ADDON_DIR/package.yml" | sed -E 's/name:\s*//g' || true); \
                fi; \
                if [[ -z "$ADDON_NAME" ]]; then \
                    ADDON_NAME="$ADDON_REPO_NAME"; \
                fi; \
            else \
                ADDON_NAME="$ADDON_REPO_NAME"; \
            fi; \
            \
            mkdir -p "src/addons/$ADDON_NAME"; \
            cp -r "$ADDON_DIR"/* "src/addons/$ADDON_NAME"/; \
        else \
            echo "Addon directory not found for $ADDON_REPO_NAME"; \
        fi; \
        \
        rm -f "tmp/$ADDON_REPO_NAME.zip"; \
    done < .github/files/addons.txt; \
    \
    # Clean up temporary files
    rm -rf tmp;

# Production stage
FROM php:8.1-apache

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