#!/bin/bash
set -euo pipefail

echo "=== Installing REDAXO Addons ==="

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

# Check if addons.txt exists
if [ ! -f ".github/files/addons.txt" ]; then
    echo "No addons.txt found, skipping addon installation"
    exit 0
fi

# Count total addons for progress
total_addons=$(grep -v '^#' .github/files/addons.txt | grep -v '^$' | wc -l)
current_addon=0

echo "Found $total_addons addons to install"

# Download and Install Addons
while IFS= read -r line; do
    if [[ -z "$line" || "$line" =~ ^# ]]; then
        continue
    fi
    
    current_addon=$((current_addon + 1))
    echo "[$current_addon/$total_addons] Processing: $line"
    
    ADDON_URL=$(echo "$line" | awk '{print $1}')
    TARGET_DIR=$(echo "$line" | awk '{print $2}')
    
    echo "Installing addon: $ADDON_URL"
    
    ADDON_REPO_NAME=$(echo "$ADDON_URL" | sed -E 's/.*\///g')
    REPO_OWNER=$(echo "$ADDON_URL" | sed -E 's/https:\/\/api.github.com\/repos\///g' | cut -d '/' -f1)
    REPO_NAME=$(echo "$ADDON_URL" | sed -E 's/https:\/\/api.github.com\/repos\///g' | cut -d '/' -f2)
    
    # Try to get release info with retry
    RELEASE_URL="$ADDON_URL/releases/latest"
    RELEASE_DATA=$(retry_with_backoff curl -s --connect-timeout 15 --max-time 30 -H 'User-Agent: Docker-REDAXO-Setup' "$RELEASE_URL" 2>/dev/null || echo '{}')
    ZIP_URL=$(echo "$RELEASE_DATA" | jq -r '.zipball_url' 2>/dev/null || echo 'null')
    
    if [[ "$ZIP_URL" == "null" || -z "$ZIP_URL" ]]; then
        echo "No release found, trying master branch..."
        ZIP_URL="https://github.com/$REPO_OWNER/$REPO_NAME/archive/refs/heads/master.zip"
        HTTP_STATUS=$(retry_with_backoff curl -s --connect-timeout 15 --max-time 30 -o /dev/null -w "%{http_code}" "$ZIP_URL" 2>/dev/null || echo "000")
        if [[ "$HTTP_STATUS" != "200" ]]; then
            echo "Master branch not found, trying main branch..."
            ZIP_URL="https://github.com/$REPO_OWNER/$REPO_NAME/archive/refs/heads/main.zip"
            HTTP_STATUS=$(retry_with_backoff curl -s --connect-timeout 15 --max-time 30 -o /dev/null -w "%{http_code}" "$ZIP_URL" 2>/dev/null || echo "000")
            if [[ "$HTTP_STATUS" != "200" ]]; then
                echo "ERROR: Could not find downloadable source for $ADDON_REPO_NAME"
                continue
            fi
        fi
    fi
    
    if [[ -z "$ZIP_URL" || "$ZIP_URL" == "null" ]]; then
        echo "ERROR: Could not determine download URL for $ADDON_REPO_NAME"
        continue
    fi
    
    echo "Downloading: $ZIP_URL"
    mkdir -p tmp
    if ! retry_with_backoff curl -Ls --connect-timeout 15 --max-time 120 "$ZIP_URL" -o "tmp/$ADDON_REPO_NAME.zip"; then
        echo "ERROR: Failed to download $ADDON_REPO_NAME after retries"
        continue
    fi
    
    if [ ! -f "tmp/$ADDON_REPO_NAME.zip" ] || [ ! -s "tmp/$ADDON_REPO_NAME.zip" ]; then
        echo "ERROR: Download file not found or empty for $ADDON_REPO_NAME"
        continue
    fi
    
    echo "Extracting addon..."
    if ! unzip -q "tmp/$ADDON_REPO_NAME.zip" -d tmp 2>/dev/null; then
        echo "ERROR: Failed to extract $ADDON_REPO_NAME"
        rm -f "tmp/$ADDON_REPO_NAME.zip"
        continue
    fi
    
    ADDON_DIR=$(find tmp -maxdepth 1 -type d -name "*${REPO_NAME}*" | grep -v "^tmp$" | head -1)
    
    if [ -z "$ADDON_DIR" ]; then
        ADDON_DIR=$(find tmp -maxdepth 1 -type d | grep -v "^tmp$" | head -1)
    fi
    
    if [[ -n "$ADDON_DIR" && -d "$ADDON_DIR" ]]; then
        if [[ -n "$TARGET_DIR" ]]; then
            ADDON_NAME="$TARGET_DIR"
        elif [[ -f "$ADDON_DIR/package.yml" ]]; then
            ADDON_NAME=$(grep -m 1 "^package:" "$ADDON_DIR/package.yml" | sed -E 's/package:\s*//g' 2>/dev/null || true)
            if [[ -z "$ADDON_NAME" ]]; then
                ADDON_NAME=$(grep -m 1 "^name:" "$ADDON_DIR/package.yml" | sed -E 's/name:\s*//g' 2>/dev/null || true)
            fi
            if [[ -z "$ADDON_NAME" ]]; then
                ADDON_NAME="$ADDON_REPO_NAME"
            fi
        else
            ADDON_NAME="$ADDON_REPO_NAME"
        fi
        
        echo "Installing as: $ADDON_NAME"
        mkdir -p "src/addons/$ADDON_NAME"
        if cp -r "$ADDON_DIR"/* "src/addons/$ADDON_NAME"/ 2>/dev/null; then
            echo "✓ Addon $ADDON_NAME installed successfully"
        else
            echo "ERROR: Failed to copy files for $ADDON_NAME"
        fi
    else
        echo "ERROR: Addon directory not found for $ADDON_REPO_NAME"
    fi
    
    # Clean up
    rm -f "tmp/$ADDON_REPO_NAME.zip"
    find tmp -maxdepth 1 -type d -name "*${REPO_NAME}*" -exec rm -rf {} \; 2>/dev/null || true
    
done < .github/files/addons.txt

# Clean up temporary files
rm -rf tmp

echo "=== Addon installation completed ==="