.PHONY: help install dev build test test-coverage clean docker-up docker-down docker-logs

help:
	@echo "Invoicing Microservice - Available Commands"
	@echo ""
	@echo "Development:"
	@echo "  make install       - Install dependencies"
	@echo "  make dev           - Start development server"
	@echo "  make build         - Build TypeScript"
	@echo "  make lint          - Run ESLint"
	@echo "  make lint-fix      - Fix ESLint issues"
	@echo ""
	@echo "Testing:"
	@echo "  make test          - Run all tests"
	@echo "  make test-coverage - Run tests with coverage report"
	@echo "  make test-watch    - Run tests in watch mode"
	@echo ""
	@echo "Docker:"
	@echo "  make docker-up     - Start all services with Docker Compose"
	@echo "  make docker-down   - Stop all services"
	@echo "  make docker-logs   - View Docker logs"
	@echo "  make docker-rebuild - Rebuild Docker images"
	@echo ""
	@echo "Utilities:"
	@echo "  make clean         - Clean build artifacts"
	@echo "  make seed          - Seed database with test data"

install:
	npm install

dev:
	npm run dev

build:
	npm run build

lint:
	npm run lint

lint-fix:
	npm run lint:fix

test:
	npm test

test-coverage:
	npm run test:coverage

test-watch:
	npm test -- --watch

clean:
	rm -rf dist coverage logs
	rm -rf node_modules package-lock.json

docker-up:
	docker-compose up -d
	@echo "Services starting..."
	@echo "App: http://localhost:8001"
	@echo "Prometheus: http://localhost:9091"
	@echo "RabbitMQ Management: http://localhost:15672"
	@echo "PostgreSQL: localhost:5432"

docker-down:
	docker-compose down

docker-logs:
	docker-compose logs -f

docker-rebuild:
	docker-compose build --no-cache
	docker-compose up -d

docker-shell:
	docker-compose exec invoicing-service sh

seed:
	npm run seed

.DEFAULT_GOAL := help
