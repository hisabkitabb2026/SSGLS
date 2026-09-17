"""Application configuration management."""

from functools import lru_cache
from typing import Optional

from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    """Application settings from environment variables."""

    # Database
    database_url: str = "postgresql://postgres:postgres@localhost:5432/expense_service"
    database_pool_size: int = 20
    database_max_overflow: int = 40
    database_echo: bool = False

    # Service
    service_host: str = "0.0.0.0"
    service_port: int = 8002
    service_name: str = "expense-service"
    service_env: str = "development"
    debug: bool = False

    # JWT
    jwt_secret_key: str = "your-secret-key-change-in-production"
    jwt_algorithm: str = "HS256"
    jwt_expiration_hours: int = 24

    # RabbitMQ
    rabbitmq_host: str = "localhost"
    rabbitmq_port: int = 5672
    rabbitmq_user: str = "guest"
    rabbitmq_password: str = "guest"
    rabbitmq_vhost: str = "/"
    rabbitmq_exchange_name: str = "invoiceshelf_events"
    rabbitmq_queue_prefix: str = "expense_service"

    # Logging
    log_level: str = "INFO"
    log_format: str = "json"

    # Metrics
    enable_metrics: bool = True
    metrics_port: int = 8003

    # Sentry
    sentry_dsn: Optional[str] = None
    sentry_environment: str = "development"

    # CORS
    allowed_origins: str = "http://localhost:3000,http://localhost:8000"

    class Config:
        env_file = ".env"
        case_sensitive = False

    @property
    def rabbitmq_url(self) -> str:
        """Construct RabbitMQ connection URL."""
        return (
            f"amqp://{self.rabbitmq_user}:{self.rabbitmq_password}@"
            f"{self.rabbitmq_host}:{self.rabbitmq_port}{self.rabbitmq_vhost}"
        )

    @property
    def allowed_origins_list(self) -> list[str]:
        """Parse allowed origins from comma-separated string."""
        return [origin.strip() for origin in self.allowed_origins.split(",")]

    @property
    def is_production(self) -> bool:
        """Check if running in production."""
        return self.service_env == "production"


@lru_cache()
def get_settings() -> Settings:
    """Get cached settings instance."""
    return Settings()
