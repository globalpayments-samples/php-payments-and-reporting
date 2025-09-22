# Go Payments API v0.1

A Go-based payment processing system with Global Payments GP-API integration, featuring card verification, payment processing, and transaction reporting.

## Overview

This is a complete payment processing system built in Go, featuring secure card tokenization, payment processing, and transaction management through Global Payments' GP-API.

### Key Features

- **Card Verification**: Zero-charge validation with AVS/CVV checks
- **Payment Processing**: Secure payment transactions via GP-API tokenization
- **Transaction Dashboard**: Real-time monitoring with filtering capabilities
- **Modern Architecture**: Clean Go codebase with structured error handling
- **Security First**: Input validation, secure tokenization, and structured logging
- **Web Interface**: Responsive HTML frontend with real-time feedback

## Project Structure

```
go-payments-api/
├── cmd/
│   └── server/                 # Application entry point
│       └── main.go
├── internal/                   # Private application code
│   ├── api/
│   │   └── handlers/          # HTTP request handlers
│   ├── config/               # Configuration management
│   ├── errors/               # Error handling
│   └── logger/               # Structured logging
├── public/                   # Static frontend files
│   ├── *.html                # Web interface pages
│   ├── assets/               # Static assets
│   └── css/                  # Stylesheets
├── tests/                    # Test files
│   ├── integration/          # Integration tests
│   └── unit/                # Unit tests
├── go.mod                    # Go module definition
├── go.sum                    # Go module checksums
├── Dockerfile                # Docker build configuration
├── docker-compose.yml        # Docker Compose setup
└── README.md                 # This file
```

## Quick Start

### Prerequisites

- Go 1.21 or higher
- Docker and Docker Compose (optional)
- Global Payments API credentials

### Environment Configuration

Create a `.env` file in the project root:

```env
# GP-API Configuration (Required)
GP_API_APP_ID=your_app_id_here
GP_API_APP_KEY=your_app_key_here
GP_API_ENVIRONMENT=sandbox
GP_API_COUNTRY=US
GP_API_CURRENCY=USD
GP_API_MERCHANT_ID=your_merchant_id

# Application Configuration
APP_ENV=development
APP_PORT=8000

# Logging Configuration
ENABLE_REQUEST_LOGGING=false
LOG_LEVEL=info
LOG_DIRECTORY=logs
```

### Running Locally

```bash
# Install dependencies
go mod download

# Run the application
go run cmd/server/main.go
```

The application will be available at `http://localhost:8000`

### Running with Docker

```bash
# Build and run with Docker Compose
docker-compose up --build

# Or build and run manually
docker build -t go-payments-api .
docker run -p 8000:8000 --env-file .env go-payments-api
```

## API Endpoints

### Health & Monitoring

- `GET /health` - Basic health check
- `GET /ready` - Readiness check including dependencies
- `GET /metrics` - Prometheus metrics

### Configuration

- `GET /api/config` - Get public configuration for client-side SDK

### Card Verification

- `POST /api/verify-card` - Verify card without processing payment

```json
{
  "payment_token": "token_from_drop_in_ui",
  "cvv": "123",
  "address": {
    "street": "123 Main St",
    "city": "New York",
    "postal_code": "10001",
    "country": "US"
  }
}
```

### Payment Processing

- `POST /api/process-payment` - Process payment transaction

```json
{
  "payment_token": "token_from_drop_in_ui",
  "amount": 99.99,
  "currency": "USD",
  "description": "Test payment",
  "order_id": "ORDER_123"
}
```

### Transaction Reporting

- `GET /api/transactions` - Get transaction list with filtering
- `GET /api/transactions/:id` - Get specific transaction details

Query parameters for transaction list:
- `start_date` - Filter by start date (YYYY-MM-DD)
- `end_date` - Filter by end date (YYYY-MM-DD)
- `status` - Filter by transaction status
- `limit` - Number of results (1-100, default: 25)
- `page` - Page number (default: 1)

### Frontend Pages

- `/` or `/index.html` - Main landing page
- `/card-verification.html` - Card verification interface
- `/payment.html` - Payment processing interface
- `/dashboard.html` - Transaction dashboard

## Development

### Running Tests

```bash
# Run all tests
go test ./...

# Run tests with coverage
go test -cover ./...

# Run specific test package
go test ./tests/unit/...
```

### Code Quality

```bash
# Format code
go fmt ./...

# Run linter (requires golangci-lint)
golangci-lint run

# Vet code
go vet ./...
```

### Building

```bash
# Build for current platform
go build -o bin/server cmd/server/main.go

# Build for Linux (production)
GOOS=linux GOARCH=amd64 go build -o bin/server-linux cmd/server/main.go
```

## Configuration

The application supports configuration via environment variables and `.env` files. See the `internal/config` package for all available options.

### Required Configuration

- `GP_API_APP_ID` - Global Payments Application ID
- `GP_API_APP_KEY` - Global Payments Application Key

### Optional Configuration

- `GP_API_ENVIRONMENT` - Environment (sandbox/production, default: sandbox)
- `GP_API_COUNTRY` - Processing country (default: US)
- `GP_API_CURRENCY` - Default currency (default: USD)
- `APP_ENV` - Application environment (default: development)
- `APP_PORT` - Server port (default: 8000)
- `LOG_LEVEL` - Logging level (debug/info/warning/error/critical, default: info)

## Performance

Built with Go for efficient performance and concurrent request handling. The application provides:

- Fast API response times
- Low memory footprint
- Native concurrency support
- Quick startup times

## Security

The application implements comprehensive security measures:

- Input validation and sanitization
- Rate limiting (100 requests/minute per IP)
- Security headers (HSTS, CSP, etc.)
- Structured error responses without sensitive data exposure
- Request/response logging with sensitive data filtering

## Monitoring & Observability

- Structured JSON logging with multiple channels
- Prometheus metrics for monitoring
- Health checks for load balancer integration
- Request tracing and performance monitoring

## Deployment

### Docker Production Deployment

The included `Dockerfile` uses multi-stage builds for optimized production images:

```bash
# Build production image
docker build -t go-payments-api:latest .

# Run in production
docker run -d \
  --name go-payments-api \
  -p 8000:8000 \
  --env-file .env \
  --restart unless-stopped \
  go-payments-api:latest
```

### Kubernetes Deployment

Example Kubernetes manifests are available in the `docs/` directory.

## Roadmap

### v0.1 (Current Release)
- ✅ Card verification with AVS/CVV validation
- ✅ Payment processing with GP-API tokenization
- ✅ Transaction dashboard with basic filtering
- ✅ Web interface with responsive design
- ✅ Docker containerization
- ✅ Comprehensive logging and error handling

### Future Releases
- Enhanced transaction reporting with CSV export
- Advanced filtering and search capabilities
- Webhook notifications for transaction events
- Multi-currency support
- Enhanced security features
- Performance monitoring and metrics

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make changes with appropriate tests
4. Run the test suite (`go test ./...`)
5. Commit your changes (`git commit -m 'Add amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

### Development Guidelines

- Follow Go naming conventions and best practices
- Add tests for new functionality
- Update documentation for API changes
- Ensure all tests pass before submitting PRs

## License

This project is licensed under the MIT License. See LICENSE file for details.

## Support

For issues and questions:

1. Check existing issues on GitHub
2. Create a new issue with detailed information
3. Include relevant logs and configuration (without sensitive data)
4. Use test/sandbox credentials when reporting issues