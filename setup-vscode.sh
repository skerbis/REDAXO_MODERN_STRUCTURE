#!/bin/bash

# REDAXO Modern Structure - VSCode Compatibility Setup
# This script helps you set up the project for use with redaxo-multi-instances-vscode extension

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to check Docker
check_docker() {
    print_info "Checking Docker installation..."
    
    if ! command_exists docker; then
        print_error "Docker is not installed. Please install Docker first."
        exit 1
    fi
    
    if ! docker info >/dev/null 2>&1; then
        print_error "Docker is not running. Please start Docker."
        exit 1
    fi
    
    print_success "Docker is ready"
}

# Function to setup VSCode compatibility
setup_vscode_mode() {
    print_info "Setting up VSCode compatibility mode..."
    
    # Create .env file from VSCode template
    if [ ! -f .env ]; then
        print_info "Creating .env file from VSCode template..."
        cp .env.vscode.example .env
        print_success "Created .env file"
    else
        print_warning ".env file already exists. You may want to check your configuration."
    fi
    
    # Create required directories
    print_info "Creating required directories..."
    mkdir -p data/redaxo
    mkdir -p data/mysql
    mkdir -p mysql-init
    mkdir -p ssl
    
    # Set proper permissions
    chmod +x custom-setup.sh
    
    print_success "VSCode compatibility setup completed!"
    
    echo ""
    print_info "Next steps:"
    echo "  1. Edit .env file to configure your instance"
    echo "  2. Run: docker-compose -f docker-compose.vscode.yml up -d"
    echo "  3. Open VS Code and install redaxo-multi-instances-vscode extension"
    echo ""
}

# Function to setup SSL
setup_ssl() {
    print_info "Setting up SSL for local development..."
    
    # Check if mkcert is installed
    if ! command_exists mkcert; then
        print_warning "mkcert is not installed."
        echo "Please install mkcert first:"
        echo ""
        echo "macOS: brew install mkcert nss"
        echo "Linux: curl -JLO 'https://dl.filippo.io/mkcert/latest?for=linux/amd64' && chmod +x mkcert-v*-linux-amd64 && sudo mv mkcert-v*-linux-amd64 /usr/local/bin/mkcert"
        echo ""
        echo "After installation, run this script with --ssl flag again."
        return 1
    fi
    
    # Get instance name from .env or use default
    INSTANCE_NAME="modern-structure"
    if [ -f .env ]; then
        INSTANCE_FROM_ENV=$(grep "^INSTANCE_NAME=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
        if [ -n "$INSTANCE_FROM_ENV" ]; then
            INSTANCE_NAME="$INSTANCE_FROM_ENV"
        fi
    fi
    
    print_info "Setting up SSL for instance: $INSTANCE_NAME"
    
    # Install mkcert CA
    print_info "Installing mkcert certificate authority..."
    mkcert -install
    
    # Generate SSL certificate
    print_info "Generating SSL certificate for $INSTANCE_NAME.local..."
    mkdir -p ssl
    mkcert -key-file ssl/$INSTANCE_NAME.local-key.pem -cert-file ssl/$INSTANCE_NAME.local.pem $INSTANCE_NAME.local
    
    # Update .env to enable SSL
    if [ -f .env ]; then
        if grep -q "SSL_ENABLED=" .env; then
            sed -i.bak "s/SSL_ENABLED=.*/SSL_ENABLED=true/" .env
        else
            echo "SSL_ENABLED=true" >> .env
        fi
        
        # Update REDAXO_SERVER for HTTPS
        HTTPS_PORT_FROM_ENV=$(grep "^HTTPS_PORT=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'" || echo "8443")
        if grep -q "REDAXO_SERVER=" .env; then
            sed -i.bak "s|REDAXO_SERVER=.*|REDAXO_SERVER=https://$INSTANCE_NAME.local:$HTTPS_PORT_FROM_ENV|" .env
        else
            echo "REDAXO_SERVER=https://$INSTANCE_NAME.local:$HTTPS_PORT_FROM_ENV" >> .env
        fi
        
        print_success "SSL enabled in .env file"
    fi
    
    # Add to hosts file
    print_info "Adding $INSTANCE_NAME.local to /etc/hosts..."
    if ! grep -q "$INSTANCE_NAME.local" /etc/hosts 2>/dev/null; then
        echo "127.0.0.1 $INSTANCE_NAME.local" | sudo tee -a /etc/hosts
        print_success "Added $INSTANCE_NAME.local to hosts file"
    else
        print_warning "$INSTANCE_NAME.local already exists in hosts file"
    fi
    
    print_success "SSL setup completed!"
    echo ""
    print_info "Your instance will be available at:"
    echo "  🌐 Frontend: https://$INSTANCE_NAME.local:8443"
    echo "  🔧 Backend: https://$INSTANCE_NAME.local:8443/redaxo"
}

# Function to start services
start_services() {
    print_info "Starting REDAXO services in VSCode compatibility mode..."
    
    # Check if .env exists
    if [ ! -f .env ]; then
        print_warning "No .env file found. Creating from template..."
        setup_vscode_mode
    fi
    
    # Start with VSCode-compatible docker-compose
    docker-compose -f docker-compose.vscode.yml up -d
    
    print_success "Services started!"
    
    # Show service information
    print_info "Service URLs:"
    
    REDAXO_PORT=$(grep "^REDAXO_PORT=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'" || echo "8080")
    PHPMYADMIN_PORT=$(grep "^PHPMYADMIN_PORT=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'" || echo "8081")
    SSL_ENABLED=$(grep "^SSL_ENABLED=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'" || echo "false")
    INSTANCE_NAME=$(grep "^INSTANCE_NAME=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'" || echo "modern-structure")
    
    if [ "$SSL_ENABLED" = "true" ]; then
        HTTPS_PORT=$(grep "^HTTPS_PORT=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'" || echo "8443")
        echo "  🌐 Frontend: https://$INSTANCE_NAME.local:$HTTPS_PORT"
        echo "  🔧 Backend: https://$INSTANCE_NAME.local:$HTTPS_PORT/redaxo"
    else
        echo "  🌐 Frontend: http://localhost:$REDAXO_PORT"
        echo "  🔧 Backend: http://localhost:$REDAXO_PORT/redaxo"
    fi
    
    echo "  🗃️  phpMyAdmin: http://localhost:$PHPMYADMIN_PORT"
    echo "  📊 Status: docker-compose -f docker-compose.vscode.yml ps"
}

# Function to stop services
stop_services() {
    print_info "Stopping REDAXO services..."
    docker-compose -f docker-compose.vscode.yml down
    print_success "Services stopped!"
}

# Function to show status
show_status() {
    print_info "REDAXO Services Status:"
    docker-compose -f docker-compose.vscode.yml ps
}

# Function to show help
show_help() {
    echo "REDAXO Modern Structure - VSCode Compatibility Setup"
    echo ""
    echo "Usage: $0 [OPTION]"
    echo ""
    echo "Options:"
    echo "  --setup        Set up VSCode compatibility mode"
    echo "  --ssl          Set up SSL/HTTPS with mkcert"
    echo "  --start        Start services in VSCode mode"
    echo "  --stop         Stop services"
    echo "  --status       Show service status"
    echo "  --help         Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 --setup      # Initial setup for VSCode extension"
    echo "  $0 --ssl        # Setup SSL certificates" 
    echo "  $0 --start      # Start all services"
    echo ""
}

# Main script logic
main() {
    echo "🚀 REDAXO Modern Structure - VSCode Compatibility Setup"
    echo "======================================================"
    echo ""
    
    case "${1:-}" in
        --setup)
            check_docker
            setup_vscode_mode
            ;;
        --ssl)
            setup_ssl
            ;;
        --start)
            check_docker
            start_services
            ;;
        --stop)
            stop_services
            ;;
        --status)
            show_status
            ;;
        --help)
            show_help
            ;;
        "")
            print_info "Welcome! What would you like to do?"
            echo ""
            echo "1) Set up VSCode compatibility mode"
            echo "2) Set up SSL/HTTPS"
            echo "3) Start services"
            echo "4) Stop services"
            echo "5) Show status"
            echo "6) Show help"
            echo ""
            read -p "Enter your choice (1-6): " choice
            
            case $choice in
                1)
                    check_docker
                    setup_vscode_mode
                    ;;
                2)
                    setup_ssl
                    ;;
                3)
                    check_docker
                    start_services
                    ;;
                4)
                    stop_services
                    ;;
                5)
                    show_status
                    ;;
                6)
                    show_help
                    ;;
                *)
                    print_error "Invalid choice"
                    exit 1
                    ;;
            esac
            ;;
        *)
            print_error "Unknown option: $1"
            show_help
            exit 1
            ;;
    esac
}

# Run main function
main "$@"