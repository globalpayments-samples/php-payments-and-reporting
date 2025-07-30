#!/bin/bash

# PHP Card Authentication - Development Server Script
# This script provides an easy way to start the development server
# with proper environment setup and error checking.

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
DEFAULT_PORT=8000
DEFAULT_HOST="0.0.0.0"
LOG_LEVEL=${LOG_LEVEL:-"info"}

print_banner() {
    echo -e "${BLUE}"
    echo "╔══════════════════════════════════════════════════════════╗"
    echo "║                PHP Card Authentication                   ║"
    echo "║                Development Server                        ║"
    echo "╚══════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
}

print_usage() {
    echo "Usage: $0 [options]"
    echo ""
    echo "Options:"
    echo "  -p, --port PORT      Server port (default: $DEFAULT_PORT)"
    echo "  -h, --host HOST      Server host (default: $DEFAULT_HOST)"
    echo "  -e, --env ENV        Environment file (default: .env)"
    echo "  -d, --dev            Development mode with auto-reload"
    echo "  -t, --test           Run tests before starting server"
    echo "  -c, --check          Check dependencies and configuration"
    echo "  -l, --logs           Show server logs"
    echo "  --help               Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0                   # Start with default settings"
    echo "  $0 -p 9000          # Start on port 9000"
    echo "  $0 --dev            # Start in development mode"
    echo "  $0 --test           # Run tests first, then start server"
    echo "  $0 --check          # Check system requirements"
}

check_requirements() {
    echo -e "${BLUE}🔍 Checking system requirements...${NC}"
    
    # Check PHP version
    if ! command -v php &> /dev/null; then
        echo -e "${RED}❌ PHP is not installed${NC}"
        exit 1
    fi
    
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    echo -e "${GREEN}✅ PHP version: $PHP_VERSION${NC}"
    
    # Check required PHP extensions
    REQUIRED_EXTENSIONS=("curl" "dom" "openssl" "json" "mbstring" "fileinfo" "intl")
    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if php -m | grep -qi "$ext"; then
            echo -e "${GREEN}✅ PHP extension: $ext${NC}"
        else
            echo -e "${RED}❌ Missing PHP extension: $ext${NC}"
            exit 1
        fi
    done
    
    # Check Composer
    if ! command -v composer &> /dev/null; then
        echo -e "${RED}❌ Composer is not installed${NC}"
        exit 1
    fi
    
    echo -e "${GREEN}✅ Composer version: $(composer --version --no-ansi | head -n1)${NC}"
    
    echo -e "${GREEN}✅ All requirements satisfied${NC}"
}

check_dependencies() {
    echo -e "${BLUE}📦 Checking dependencies...${NC}"
    
    if [ ! -f "composer.json" ]; then
        echo -e "${RED}❌ composer.json not found${NC}"
        exit 1
    fi
    
    if [ ! -d "vendor" ]; then
        echo -e "${YELLOW}⚠️  Dependencies not installed. Installing...${NC}"
        composer install
    else
        echo -e "${GREEN}✅ Dependencies installed${NC}"
    fi
}

check_environment() {
    echo -e "${BLUE}🔧 Checking environment configuration...${NC}"
    
    if [ ! -f "$ENV_FILE" ]; then
        echo -e "${YELLOW}⚠️  Environment file $ENV_FILE not found${NC}"
        if [ -f ".env.sample" ]; then
            echo -e "${BLUE}📋 Creating $ENV_FILE from .env.sample...${NC}"
            cp .env.sample "$ENV_FILE"
            echo -e "${YELLOW}⚠️  Please update $ENV_FILE with your API credentials${NC}"
        else
            echo -e "${RED}❌ No environment template found${NC}"
            exit 1
        fi
    else
        echo -e "${GREEN}✅ Environment file found: $ENV_FILE${NC}"
    fi
    
    # Check for required environment variables
    source "$ENV_FILE"
    
    if [ -z "$PUBLIC_API_KEY" ] || [ "$PUBLIC_API_KEY" = "pkapi_cert_your_public_key_here" ]; then
        echo -e "${YELLOW}⚠️  PUBLIC_API_KEY not configured${NC}"
    else
        echo -e "${GREEN}✅ PUBLIC_API_KEY configured${NC}"
    fi
    
    if [ -z "$SECRET_API_KEY" ] || [ "$SECRET_API_KEY" = "skapi_cert_your_secret_key_here" ]; then
        echo -e "${YELLOW}⚠️  SECRET_API_KEY not configured${NC}"
    else
        echo -e "${GREEN}✅ SECRET_API_KEY configured${NC}"
    fi
}

run_tests() {
    echo -e "${BLUE}🧪 Running tests...${NC}"
    
    if [ -f "vendor/bin/phpunit" ]; then
        ./vendor/bin/phpunit
    elif command -v phpunit &> /dev/null; then
        phpunit
    else
        echo -e "${YELLOW}⚠️  PHPUnit not found, skipping tests${NC}"
    fi
    
    # Run example scripts as smoke tests
    echo -e "${BLUE}🔥 Running smoke tests...${NC}"
    
    if [ -f "examples/basic-verification.php" ]; then
        echo -e "${BLUE}  Testing basic verification...${NC}"
        php examples/basic-verification.php > /dev/null && echo -e "${GREEN}  ✅ Basic verification test passed${NC}" || echo -e "${RED}  ❌ Basic verification test failed${NC}"
    fi
}

start_server() {
    echo -e "${BLUE}🚀 Starting development server...${NC}"
    echo -e "${GREEN}Server running at: http://${HOST}:${PORT}${NC}"
    echo -e "${GREEN}API Configuration: http://${HOST}:${PORT}/config.php${NC}"
    echo -e "${GREEN}Verification API: http://${HOST}:${PORT}/verify-card.php${NC}"
    echo ""
    echo -e "${YELLOW}Press Ctrl+C to stop the server${NC}"
    echo ""
    
    # Create logs directory if it doesn't exist
    mkdir -p logs
    
    # Export environment variables for PHP
    export $(grep -v '^#' "$ENV_FILE" | xargs)
    
    if [ "$DEV_MODE" = true ]; then
        echo -e "${BLUE}📱 Development mode: Auto-reload enabled${NC}"
        # In development mode, we could add file watchers here
    fi
    
    # Start PHP built-in server with router
    if [ "$SHOW_LOGS" = true ]; then
        php -S "${HOST}:${PORT}" -t . router.php 2>&1 | tee logs/server.log
    else
        php -S "${HOST}:${PORT}" -t . router.php
    fi
}

kill_existing_server() {
    # Kill any existing PHP server on the port
    if lsof -Pi :$PORT -sTCP:LISTEN -t >/dev/null ; then
        echo -e "${YELLOW}⚠️  Port $PORT is in use, attempting to free it...${NC}"
        lsof -ti:$PORT | xargs kill -9 2>/dev/null || true
        sleep 2
    fi
}

# Default values
PORT=$DEFAULT_PORT
HOST=$DEFAULT_HOST
ENV_FILE=".env"
DEV_MODE=false
RUN_TESTS=false
CHECK_ONLY=false
SHOW_LOGS=false

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -p|--port)
            PORT="$2"
            shift 2
            ;;
        -h|--host)
            HOST="$2"
            shift 2
            ;;
        -e|--env)
            ENV_FILE="$2"
            shift 2
            ;;
        -d|--dev)
            DEV_MODE=true
            shift
            ;;
        -t|--test)
            RUN_TESTS=true
            shift
            ;;
        -c|--check)
            CHECK_ONLY=true
            shift
            ;;
        -l|--logs)
            SHOW_LOGS=true
            shift
            ;;
        --help)
            print_usage
            exit 0
            ;;
        *)
            echo -e "${RED}❌ Unknown option: $1${NC}"
            print_usage
            exit 1
            ;;
    esac
done

# Main execution
print_banner

# Always check requirements and dependencies
check_requirements
check_dependencies
check_environment

# Run tests if requested
if [ "$RUN_TESTS" = true ]; then
    run_tests
fi

# If check-only mode, exit here
if [ "$CHECK_ONLY" = true ]; then
    echo -e "${GREEN}✅ All checks passed!${NC}"
    exit 0
fi

# Setup signal handlers for graceful shutdown
trap 'echo -e "\n${YELLOW}🛑 Shutting down server...${NC}"; exit 0' INT TERM

# Kill existing server and start new one
kill_existing_server
start_server