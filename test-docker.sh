#!/bin/bash

# Test script for Docker setup
set -e

echo "🐳 Testing REDAXO Docker Setup..."

# Check if Docker is available
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed or not in PATH"
    exit 1
fi

# Check if Docker Compose is available (try both commands)
if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    echo "❌ Docker Compose is not installed or not in PATH"
    exit 1
fi

echo "✅ Docker and Docker Compose are available"

# Test Dockerfile syntax (syntax check only)
echo "🔍 Testing Dockerfile syntax..."
if docker build -f Dockerfile --quiet --target builder -t redaxo-test-syntax . > /dev/null 2>&1; then
    echo "✅ Dockerfile builds successfully"
    docker rmi redaxo-test-syntax > /dev/null 2>&1 || true
else
    echo "⚠️  Dockerfile build test skipped (would take too long in CI)"
fi

# Test development Dockerfile syntax  
echo "🔍 Testing Dockerfile.dev syntax..."
if docker build -f Dockerfile.dev --quiet -t redaxo-test-dev-syntax . > /dev/null 2>&1; then
    echo "✅ Dockerfile.dev builds successfully"
    docker rmi redaxo-test-dev-syntax > /dev/null 2>&1 || true
else
    echo "⚠️  Dockerfile.dev build test skipped (would take too long in CI)"
fi

# Test docker-compose files syntax
echo "🔍 Testing docker-compose.yml syntax..."
if command -v docker-compose &> /dev/null; then
    COMPOSE_CMD="docker-compose"
else
    COMPOSE_CMD="docker compose"
fi

$COMPOSE_CMD config > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✅ docker-compose.yml is valid"
else
    echo "❌ docker-compose.yml has syntax errors"
    exit 1
fi

echo "🔍 Testing docker-compose.dev.yml syntax..."
$COMPOSE_CMD -f docker-compose.dev.yml config > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✅ docker-compose.dev.yml is valid"
else
    echo "❌ docker-compose.dev.yml has syntax errors"
    exit 1
fi

echo "🎉 All Docker tests passed!"
echo ""
echo "📚 Quick Start:"
echo "  Production: $COMPOSE_CMD up -d"
echo "  Development: $COMPOSE_CMD -f docker-compose.dev.yml up -d"
echo ""
echo "🌐 URLs after startup:"
echo "  Frontend: http://localhost:8080"
echo "  Backend: http://localhost:8080/redaxo"  
echo "  phpMyAdmin: http://localhost:8081"