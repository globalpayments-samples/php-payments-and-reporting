#!/bin/bash

# Go Payments API Startup Script
# Equivalent to PHP's run.sh

echo "Starting Go Payments API..."

# Check if Go is installed
if ! command -v go &> /dev/null; then
    echo "Error: Go is not installed. Please install Go 1.21 or higher."
    exit 1
fi

# Check if .env file exists
if [ ! -f .env ]; then
    echo "Warning: .env file not found. Copying from .env.example..."
    cp .env.example .env
    echo "Please configure your GP-API credentials in .env file"
fi

# Install dependencies
echo "Installing dependencies..."
go mod tidy

# Build the application
echo "Building application..."
go build -o go-payments-server ./cmd/server

# Start the server
echo "Starting server on http://localhost:8000..."
./go-payments-server