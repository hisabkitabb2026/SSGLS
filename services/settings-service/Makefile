.PHONY: help deps build run test test-unit test-integration coverage clean docker-build docker-up docker-down fmt lint

help:
	@echo "Settings Microservice - Development Commands"
	@echo ""
	@echo "Usage:"
	@echo "  make deps                   Install dependencies"
	@echo "  make build                  Build the application"
	@echo "  make run                    Run the application locally"
	@echo "  make test                   Run all tests"
	@echo "  make test-unit              Run unit tests only"
	@echo "  make test-integration       Run integration tests only"
	@echo "  make coverage               Generate coverage report"
	@echo "  make fmt                    Format code"
	@echo "  make lint                   Lint code"
	@echo "  make docker-build           Build Docker image"
	@echo "  make docker-up              Start Docker Compose stack"
	@echo "  make docker-down            Stop Docker Compose stack"
	@echo "  make clean                  Clean build artifacts"

deps:
	@echo "Downloading dependencies..."
	go mod download
	go mod tidy

build:
	@echo "Building application..."
	CGO_ENABLED=0 GOOS=linux go build -a -installsuffix cgo -o bin/settings-service ./cmd

run:
	@echo "Running application..."
	go run cmd/main.go

test:
	@echo "Running all tests..."
	go test -v -race -coverprofile=coverage.out ./...

test-unit:
	@echo "Running unit tests..."
	go test -v -race -coverprofile=coverage_unit.out ./tests/unit/...

test-integration:
	@echo "Running integration tests..."
	go test -v -race -coverprofile=coverage_integration.out ./tests/integration/...

coverage:
	@echo "Generating coverage report..."
	go test -coverprofile=coverage.out ./...
	go tool cover -html=coverage.out -o coverage.html
	@echo "Coverage report generated: coverage.html"

coverage-check:
	@echo "Checking test coverage..."
	@go tool cover -func=coverage.out | grep total | awk '{print "Total coverage: " $$3}'

fmt:
	@echo "Formatting code..."
	go fmt ./...

lint:
	@echo "Linting code..."
	go vet ./...
	@which golangci-lint > /dev/null || (echo "golangci-lint not found. Install with: curl -sSfL https://raw.githubusercontent.com/golangci/golangci-lint/master/install.sh | sh -s -- -b $(go env GOPATH)/bin" && exit 1)
	golangci-lint run ./...

docker-build:
	@echo "Building Docker image..."
	docker build -t invoiceshelf/settings-service:latest .

docker-up:
	@echo "Starting Docker Compose stack..."
	docker-compose up -d
	@echo "Services started:"
	@echo "  Settings Service: http://localhost:8005"
	@echo "  PostgreSQL: localhost:5432"
	@echo "  pgAdmin: http://localhost:5050"
	@echo "  RabbitMQ: http://localhost:15672"
	@echo "  Prometheus: http://localhost:9091"
	@echo "  Grafana: http://localhost:3000"

docker-logs:
	@echo "Displaying Docker Compose logs..."
	docker-compose logs -f settings-service

docker-down:
	@echo "Stopping Docker Compose stack..."
	docker-compose down

docker-clean:
	@echo "Cleaning Docker resources..."
	docker-compose down -v
	docker rmi invoiceshelf/settings-service:latest

clean:
	@echo "Cleaning build artifacts..."
	rm -rf bin/
	rm -f coverage.out coverage.html coverage_unit.out coverage_integration.out
	go clean

install-tools:
	@echo "Installing development tools..."
	go install github.com/golangci/golangci-lint/cmd/golangci-lint@latest
	go install github.com/golang/mock/mockgen@latest

migrate-up:
	@echo "Running database migrations..."
	go run cmd/main.go

generate-test-token:
	@echo "Generating test JWT token..."
	@go run scripts/generate-token.go

seed-db:
	@echo "Seeding database with sample data..."
	@go run scripts/seed.go

.DEFAULT_GOAL := help
