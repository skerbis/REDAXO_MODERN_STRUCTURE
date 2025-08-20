#!/bin/bash

# Test script for Docker setup
set -e

echo "🐳 Testing REDAXO Docker Setup..."

# Check if Docker is available
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed or not in PATH"
    exit 1
fi

# Check if Docker Compose is available
if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose is not installed or not in PATH"
    exit 1
fi

echo "✅ Docker and Docker Compose are available"

# Test Dockerfile syntax
echo "🔍 Testing Dockerfile syntax..."
docker build -f Dockerfile --target builder -t redaxo-test-build . > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Dockerfile builds successfully"
else
    echo "❌ Dockerfile build failed"
    exit 1
fi

# Test development Dockerfile syntax  
echo "🔍 Testing Dockerfile.dev syntax..."
docker build -f Dockerfile.dev -t redaxo-test-dev . > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Dockerfile.dev builds successfully"
else
    echo "❌ Dockerfile.dev build failed"
    exit 1
fi

# Test docker-compose files syntax
echo "🔍 Testing docker-compose.yml syntax..."
docker-compose config > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✅ docker-compose.yml is valid"
else
    echo "❌ docker-compose.yml has syntax errors"
    exit 1
fi

echo "🔍 Testing docker-compose.dev.yml syntax..."
docker-compose -f docker-compose.dev.yml config > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✅ docker-compose.dev.yml is valid"
else
    echo "❌ docker-compose.dev.yml has syntax errors"
    exit 1
fi

echo "🎉 All Docker tests passed!"
echo ""
echo "📚 Quick Start:"
echo "  Production: docker-compose up -d"
echo "  Development: docker-compose -f docker-compose.dev.yml up -d"
echo ""
echo "🌐 URLs after startup:"
echo "  Frontend: http://localhost:8080"
echo "  Backend: http://localhost:8080/redaxo"  
echo "  phpMyAdmin: http://localhost:8081"