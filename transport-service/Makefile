.PHONY: help install dev test test-unit test-integration test-cov lint format clean docker-build docker-up docker-down docker-logs

help:
	@echo "Transport Microservice - Available Commands"
	@echo ""
	@echo "Installation:"
	@echo "  make install           Install dependencies"
	@echo ""
	@echo "Development:"
	@echo "  make dev               Run development server with auto-reload"
	@echo "  make format            Format code with black and isort"
	@echo "  make lint              Lint code with flake8 and pylint"
	@echo ""
	@echo "Testing:"
	@echo "  make test              Run all tests with coverage"
	@echo "  make test-unit         Run unit tests only"
	@echo "  make test-integration  Run integration tests only"
	@echo "  make test-cov          Generate coverage report"
	@echo ""
	@echo "Docker:"
	@echo "  make docker-build      Build Docker image"
	@echo "  make docker-up         Start services with docker-compose"
	@echo "  make docker-down       Stop services"
	@echo "  make docker-logs       View container logs"
	@echo ""
	@echo "Cleanup:"
	@echo "  make clean             Remove temporary files"

install:
	pip install -r requirements.txt

dev:
	python -m uvicorn app.main:app --reload --port 8006 --host 0.0.0.0

test:
	pytest --cov=app --cov=config --cov-report=html --cov-report=term-missing

test-unit:
	pytest tests/unit/ -v

test-integration:
	pytest tests/integration/ -v

test-cov:
	pytest --cov=app --cov=config --cov-report=html
	open htmlcov/index.html

lint:
	flake8 app config tests --max-line-length=100 || true
	pylint app config || true

format:
	black app config tests
	isort app config tests

clean:
	find . -type d -name __pycache__ -exec rm -rf {} +
	find . -type f -name "*.pyc" -delete
	find . -type d -name ".pytest_cache" -exec rm -rf {} +
	find . -type d -name ".coverage" -delete
	find . -type d -name "htmlcov" -exec rm -rf {} +
	rm -f .coverage
	rm -f pytest.log

docker-build:
	docker build -t transport-service:1.0.0 .

docker-up:
	docker-compose up -d
	@echo "Services started. Access them at:"
	@echo "  API: http://localhost:8006"
	@echo "  Docs: http://localhost:8006/api/docs"
	@echo "  RabbitMQ: http://localhost:15672 (guest/guest)"
	@echo "  pgAdmin: http://localhost:5050"

docker-down:
	docker-compose down

docker-logs:
	docker-compose logs -f

docker-clean:
	docker-compose down -v
	docker system prune -f

db-init:
	@echo "Initializing database..."
	docker exec transport-postgres psql -U transport_user -d transport_db -f /docker-entrypoint-initdb.d/01-init.sql

db-shell:
	docker exec -it transport-postgres psql -U transport_user -d transport_db

rabbitmq-shell:
	docker exec -it transport-rabbitmq rabbitmqctl

health:
	curl -s http://localhost:8006/health | jq .

metrics:
	curl -s http://localhost:8006/metrics | head -20

api-docs:
	open http://localhost:8006/api/docs

all: clean install test
