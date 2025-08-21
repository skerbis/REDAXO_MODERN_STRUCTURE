#!/bin/bash

# Port checking script for REDAXO Docker setup
# This script helps find available ports if the defaults are in use

echo "🔍 Checking port availability for REDAXO Docker setup..."
echo ""

# Default ports
DEFAULT_REDAXO_PORT=8080
DEFAULT_PHPMYADMIN_PORT=8081
DEFAULT_DATABASE_PORT=3306

# Function to check if port is in use
check_port() {
    local port=$1
    local service=$2
    
    if command -v netstat >/dev/null 2>&1; then
        if netstat -tuln | grep -q ":$port "; then
            echo "❌ Port $port ($service) is already in use"
            return 1
        fi
    elif command -v ss >/dev/null 2>&1; then
        if ss -tuln | grep -q ":$port "; then
            echo "❌ Port $port ($service) is already in use"
            return 1
        fi
    elif command -v lsof >/dev/null 2>&1; then
        if lsof -i :$port >/dev/null 2>&1; then
            echo "❌ Port $port ($service) is already in use"
            return 1
        fi
    else
        echo "⚠️  Cannot check port $port - no port checking tool available (netstat, ss, or lsof)"
        return 0
    fi
    
    echo "✅ Port $port ($service) is available"
    return 0
}

# Function to find next available port
find_available_port() {
    local start_port=$1
    local service=$2
    local port=$start_port
    
    while [ $port -le $((start_port + 100)) ]; do
        if command -v netstat >/dev/null 2>&1; then
            if ! netstat -tuln | grep -q ":$port "; then
                echo "✨ Suggested port for $service: $port"
                return
            fi
        elif command -v ss >/dev/null 2>&1; then
            if ! ss -tuln | grep -q ":$port "; then
                echo "✨ Suggested port for $service: $port"
                return
            fi
        elif command -v lsof >/dev/null 2>&1; then
            if ! lsof -i :$port >/dev/null 2>&1; then
                echo "✨ Suggested port for $service: $port"
                return
            fi
        fi
        port=$((port + 1))
    done
    
    echo "⚠️  Could not find available port for $service in range $start_port-$((start_port + 100))"
}

# Check default ports
redaxo_available=0
phpmyadmin_available=0
database_available=0

if check_port $DEFAULT_REDAXO_PORT "REDAXO Web"; then
    redaxo_available=1
fi

if check_port $DEFAULT_PHPMYADMIN_PORT "phpMyAdmin"; then
    phpmyadmin_available=1
fi

if check_port $DEFAULT_DATABASE_PORT "Database"; then
    database_available=1
fi

echo ""

# Generate .env suggestions if needed
if [ $redaxo_available -eq 0 ] || [ $phpmyadmin_available -eq 0 ] || [ $database_available -eq 0 ]; then
    echo "🔧 Port conflicts detected! Here are some solutions:"
    echo ""
    
    echo "Option 1: Use alternative ports in .env file:"
    echo "cat > .env << EOF"
    echo "PHP_VERSION=8.4"
    
    if [ $redaxo_available -eq 0 ]; then
        find_available_port 8080 "REDAXO"
    else
        echo "REDAXO_PORT=8080"
    fi
    
    if [ $phpmyadmin_available -eq 0 ]; then
        find_available_port 8081 "phpMyAdmin"
    else
        echo "PHPMYADMIN_PORT=8081"
    fi
    
    if [ $database_available -eq 0 ]; then
        find_available_port 3306 "Database"
    else
        echo "DATABASE_PORT=3306"
    fi
    
    echo "DB_TYPE=mysql"
    echo "DB_VERSION=8.0"
    echo "DB_ROOT_PASSWORD=redaxo"
    echo "DB_NAME=redaxo"
    echo "DB_USER=redaxo"
    echo "DB_PASSWORD=redaxo"
    echo "EOF"
    echo ""
    
    echo "Option 2: Set ports via environment variables:"
    env_vars=""
    if [ $redaxo_available -eq 0 ]; then
        suggested_port=$(find_available_port 8080 "REDAXO" | cut -d' ' -f5)
        env_vars="$env_vars REDAXO_PORT=$suggested_port"
    fi
    if [ $phpmyadmin_available -eq 0 ]; then
        suggested_port=$(find_available_port 8081 "phpMyAdmin" | cut -d' ' -f5)
        env_vars="$env_vars PHPMYADMIN_PORT=$suggested_port"
    fi
    if [ $database_available -eq 0 ]; then
        suggested_port=$(find_available_port 3306 "Database" | cut -d' ' -f5)
        env_vars="$env_vars DATABASE_PORT=$suggested_port"
    fi
    
    echo "$env_vars docker compose up -d"
    
else
    echo "🎉 All default ports are available! You can start with:"
    echo "docker compose up -d"
fi

echo ""
echo "💡 After starting, REDAXO will be available at:"
echo "   - Frontend: http://localhost:\${REDAXO_PORT:-8080}"
echo "   - Backend: http://localhost:\${REDAXO_PORT:-8080}/redaxo"
echo "   - phpMyAdmin: http://localhost:\${PHPMYADMIN_PORT:-8081}"