#!/bin/bash
set -euo pipefail

echo "=== Setting up REDAXO Modern Structure ==="

# Function to retry commands with exponential backoff
retry_with_backoff() {
    local max_attempts=${ATTEMPTS:-3}
    local timeout=${TIMEOUT:-2}
    local attempt=1
    local exitCode=0

    while [[ $attempt -le $max_attempts ]]; do
        if "$@"; then
            return 0
        else
            exitCode=$?
        fi

        echo "Attempt $attempt failed! Retrying in $timeout..." 1>&2
        sleep $timeout
        attempt=$(( attempt + 1 ))
        timeout=$(( timeout * 2 ))
    done

    return $exitCode
}

# Default fallback version
DEFAULT_VERSION="5.17.0"

# Get latest REDAXO release with retry and fallback
echo "Fetching latest REDAXO version..."
REDAXO_VERSION=""

# Try to get the latest version from GitHub API
if REDAXO_VERSION=$(retry_with_backoff curl -s --connect-timeout 10 --max-time 20 "https://api.github.com/repos/redaxo/redaxo/releases/latest" | jq -r ".tag_name" 2>/dev/null); then
    if [[ -n "$REDAXO_VERSION" && "$REDAXO_VERSION" != "null" ]]; then
        echo "Successfully fetched latest version: $REDAXO_VERSION"
    else
        REDAXO_VERSION=""
    fi
fi

# Fallback to default version if API call failed
if [[ -z "$REDAXO_VERSION" || "$REDAXO_VERSION" == "null" ]]; then
    echo "Failed to fetch latest version from GitHub API, using fallback version: $DEFAULT_VERSION"
    REDAXO_VERSION="$DEFAULT_VERSION"
fi

echo "Building REDAXO version: $REDAXO_VERSION"

# Create temporary directories
mkdir -p tmp/redaxo

# Try multiple download sources
echo "Downloading REDAXO..."
DOWNLOAD_SUCCESS=false

# First try: Official release zip
REDAXO_URL="https://github.com/redaxo/redaxo/releases/download/$REDAXO_VERSION/redaxo_$REDAXO_VERSION.zip"
echo "Trying official release: $REDAXO_URL"
if retry_with_backoff curl -Ls --connect-timeout 15 --max-time 120 "$REDAXO_URL" -o "tmp/redaxo/redaxo_$REDAXO_VERSION.zip"; then
    if [ -f "tmp/redaxo/redaxo_$REDAXO_VERSION.zip" ] && [ -s "tmp/redaxo/redaxo_$REDAXO_VERSION.zip" ]; then
        echo "✓ Successfully downloaded official release"
        DOWNLOAD_SUCCESS=true
    fi
fi

# Second try: Archive download if release failed
if [ "$DOWNLOAD_SUCCESS" = false ]; then
    echo "Official release failed, trying archive download..."
    ARCHIVE_URL="https://github.com/redaxo/redaxo/archive/refs/tags/$REDAXO_VERSION.zip"
    echo "Trying archive: $ARCHIVE_URL"
    if retry_with_backoff curl -Ls --connect-timeout 15 --max-time 120 "$ARCHIVE_URL" -o "tmp/redaxo/redaxo_$REDAXO_VERSION.zip"; then
        if [ -f "tmp/redaxo/redaxo_$REDAXO_VERSION.zip" ] && [ -s "tmp/redaxo/redaxo_$REDAXO_VERSION.zip" ]; then
            echo "✓ Successfully downloaded archive"
            DOWNLOAD_SUCCESS=true
        fi
    fi
fi

# Third try: Main branch if specific version failed
if [ "$DOWNLOAD_SUCCESS" = false ]; then
    echo "Version-specific download failed, trying main branch..."
    MAIN_URL="https://github.com/redaxo/redaxo/archive/refs/heads/main.zip"
    echo "Trying main branch: $MAIN_URL"
    if retry_with_backoff curl -Ls --connect-timeout 15 --max-time 120 "$MAIN_URL" -o "tmp/redaxo/redaxo_main.zip"; then
        if [ -f "tmp/redaxo/redaxo_main.zip" ] && [ -s "tmp/redaxo/redaxo_main.zip" ]; then
            echo "✓ Successfully downloaded main branch"
            mv "tmp/redaxo/redaxo_main.zip" "tmp/redaxo/redaxo_$REDAXO_VERSION.zip"
            DOWNLOAD_SUCCESS=true
        fi
    fi
fi

if [ "$DOWNLOAD_SUCCESS" = false ]; then
    echo "ERROR: Failed to download REDAXO from all sources"
    exit 1
fi

# Verify download
if [ ! -f "tmp/redaxo/redaxo_$REDAXO_VERSION.zip" ] || [ ! -s "tmp/redaxo/redaxo_$REDAXO_VERSION.zip" ]; then
    echo "ERROR: REDAXO download file is missing or empty"
    exit 1
fi

# Unzip REDAXO
echo "Extracting REDAXO..."
unzip -q "tmp/redaxo/redaxo_$REDAXO_VERSION.zip" -d tmp/extracted/

# Find the extraction root directory
EXTRACT_ROOT="tmp/extracted"
if [ ! -d "$EXTRACT_ROOT" ]; then
    echo "ERROR: Extraction failed"
    exit 1
fi

echo "Checking extracted contents..."
ls -la "$EXTRACT_ROOT/"

# Move REDAXO contents to public
mkdir -p public

# Copy root level files and directories (index.php, assets, media, redaxo)
if [ -f "$EXTRACT_ROOT/index.php" ]; then
    cp "$EXTRACT_ROOT/index.php" public/
fi
if [ -d "$EXTRACT_ROOT/assets" ]; then
    cp -r "$EXTRACT_ROOT/assets" public/
fi
if [ -d "$EXTRACT_ROOT/media" ]; then
    cp -r "$EXTRACT_ROOT/media" public/
fi
if [ -d "$EXTRACT_ROOT/redaxo" ]; then
    cp -r "$EXTRACT_ROOT/redaxo" public/
fi

# Copy license file
if [ -f "$EXTRACT_ROOT/LICENSE.md" ]; then
    cp "$EXTRACT_ROOT/LICENSE.md" LICENSE.md
fi

# Ensure redaxo directory exists in public
if [ ! -d "public/redaxo" ]; then
    echo "ERROR: No redaxo directory found in extracted files"
    echo "Contents of extraction:"
    ls -la "$EXTRACT_ROOT/"
    echo "Contents of public:"
    ls -la public/
    exit 1
fi

echo "REDAXO extracted successfully to public/redaxo"

# Create Yakamara file structure
echo "Creating modern file structure..."
mkdir -p var src

# Check if standard directories exist before moving
if [ -d "public/redaxo/bin" ]; then
    mv public/redaxo/bin/ bin/
else
    echo "Warning: bin directory not found, creating empty one"
    mkdir -p bin
fi

if [ -d "public/redaxo/cache" ]; then
    mv public/redaxo/cache/ var/cache/
else
    mkdir -p var/cache
fi

if [ -d "public/redaxo/data" ]; then
    mv public/redaxo/data/ var/data/
else
    mkdir -p var/data
fi

if [ -d "public/redaxo/src/addons" ]; then
    mv public/redaxo/src/addons/ src/addons/
else
    mkdir -p src/addons
fi

if [ -d "public/redaxo/src/core" ]; then
    mv public/redaxo/src/core/ src/core/
else
    echo "ERROR: REDAXO core directory not found"
    ls -la public/redaxo/src/ 2>/dev/null || echo "No src directory found"
    exit 1
fi

# Move license and clean up readme files
mv public/LICENSE.md LICENSE.md 2>/dev/null || mv public/LICENSE LICENSE.md 2>/dev/null || true
rm -f public/README.md public/README.de.md

# Copy setup files
echo "Setting up project files..."
mkdir -p src/addons/project
cp .github/files/addon.project.boot.php src/addons/project/boot.php
cp .github/files/console bin/console
chmod +x bin/console
cp .github/files/index.backend.php public/redaxo/index.php
cp .github/files/index.frontend.php public/index.php
cp .github/files/AppPathProvider.php src/AppPathProvider.php

# Clean up temporary files
rm -rf tmp

echo "=== REDAXO setup completed successfully ==="