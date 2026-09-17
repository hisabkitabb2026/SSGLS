.PHONY: help install install-dev test test-cov lint format clean run docker-build docker-up docker-down

help:
	@echo "Expense Microservice - Available Commands"
	@echo ""
	@echo "  install          Install production dependencies"
	@echo "  install-dev      Install development dependencies"
	@echo "  test             Run tests"
	@echo "  test-cov         Run tests with coverage report"
	@echo "  lint             Run linters (flake8, mypy)"
	@echo "  format           Format code with black and isort"
	@echo "  clean            Clean up cache and build files"
	@echo "  run              Run development server"
	@echo "  docker-build     Build Docker image"
	@echo "  docker-up        Start Docker containers"
	@echo "  docker-down      Stop Docker containers"
	@echo "  db-init          Initialize database"
	@echo "  db-migrate       Run database migrations"

install:
	pip install -r requirements.txt

install-dev:
	pip install -r requirements.txt
	pip install pytest-cov black isort flake8 mypy

test:
	pytest -v

test-cov:
	pytest --cov=app --cov-report=html --cov-report=term-missing -v

lint:
	flake8 app tests --max-line-length=100
	mypy app --ignore-missing-imports

format:
	black app tests
	isort app tests

clean:
	find . -type d -name __pycache__ -exec rm -rf {} + 2>/dev/null || true
	find . -type f -name "*.pyc" -delete
	rm -rf .pytest_cache
	rm -rf .mypy_cache
	rm -rf htmlcov
	rm -rf dist build *.egg-info

run:
	uvicorn app.main:app --host 0.0.0.0 --port 8002 --reload

docker-build:
	docker-compose build

docker-up:
	docker-compose up -d

docker-down:
	docker-compose down

docker-logs:
	docker-compose logs -f expense-service

db-init:
	python -c "from app.models import DatabaseManager; from app.config import get_settings; settings = get_settings(); DatabaseManager.initialize(settings.database_url); DatabaseManager.create_all()"

db-migrate:
	alembic upgrade head
