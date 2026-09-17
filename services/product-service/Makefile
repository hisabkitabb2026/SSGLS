.PHONY: help build run test test-coverage lint fmt docker-build docker-up docker-down migrate clean

help:
	@echo "Product Service Makefile"
	@echo ""
	@echo "Usage:"
	@echo "  make build          - Build the service"
	@echo "  make run            - Run the service locally"
	@echo "  make test           - Run all tests"
	@echo "  make test-coverage  - Run tests with coverage report"
	@echo "  make lint           - Run linter"
	@echo "  make fmt            - Format code"
	@echo "  make docker-build   - Build Docker image"
	@echo "  make docker-up      - Start Docker containers"
	@echo "  make docker-down    - Stop Docker containers"
	@echo "  make docker-logs    - View Docker container logs"
	@echo "  make docker-ps      - List Docker containers"
	@echo "  make migrate        - Run database migrations"
	@echo "  make clean          - Clean build artifacts"

build:
	@echo "Building product service..."
	go build -o product-service .

run: build
	@echo "Running product service..."
	./product-service

test:
	@echo "Running tests..."
	go test -v ./...

test-coverage:
	@echo "Running tests with coverage..."
	go test -v -coverprofile=coverage.out ./...
	go tool cover -html=coverage.out -o coverage.html
	@echo "Coverage report generated: coverage.html"

test-race:
	@echo "Running tests with race detector..."
	go test -v -race ./...

lint:
	@echo "Running linter..."
	golangci-lint run ./...

fmt:
	@echo "Formatting code..."
	go fmt ./...
	goimports -w .

vet:
	@echo "Running go vet..."
	go vet ./...

docker-build:
	@echo "Building Docker image..."
	docker build -t product-service:latest .

docker-up:
	@echo "Starting Docker containers..."
	docker-compose up -d

docker-down:
	@echo "Stopping Docker containers..."
	docker-compose down

docker-logs:
	@echo "Viewing Docker logs..."
	docker-compose logs -f product-service

docker-ps:
	@echo "Docker containers:"
	docker-compose ps

docker-restart:
	@echo "Restarting Docker containers..."
	docker-compose restart

migrate:
	@echo "Running migrations..."
	go run . migrate

clean:
	@echo "Cleaning up..."
	rm -f product-service
	rm -f coverage.out coverage.html
	go clean

install-tools:
	@echo "Installing development tools..."
	go install github.com/cosmtrek/air@latest
	go install honnef.co/go/tools/cmd/staticcheck@latest
	go install github.com/golangci/golangci-lint/cmd/golangci-lint@latest

dev:
	@echo "Running in development mode..."
	air

deps:
	@echo "Downloading dependencies..."
	go mod download
	go mod tidy

update-deps:
	@echo "Updating dependencies..."
	go get -u ./...
	go mod tidy
