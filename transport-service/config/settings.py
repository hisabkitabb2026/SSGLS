"""Application configuration settings."""
from pydantic_settings import BaseSettings
from typing import Optional


class Settings(BaseSettings):
    """Application settings from environment variables."""

    # Application
    APP_NAME: str = "Transport Microservice"
    APP_VERSION: str = "1.0.0"
    DEBUG: bool = False

    # Server
    HOST: str = "0.0.0.0"
    PORT: int = 8006

    # Database
    DATABASE_URL: str
    DATABASE_ECHO: bool = False

    # JWT
    JWT_SECRET_KEY: str
    JWT_ALGORITHM: str = "HS256"
    JWT_EXPIRATION_HOURS: int = 24

    # RabbitMQ
    RABBITMQ_HOST: str = "localhost"
    RABBITMQ_PORT: int = 5672
    RABBITMQ_USER: str = "guest"
    RABBITMQ_PASSWORD: str = "guest"
    RABBITMQ_VHOST: str = "/"
    RABBITMQ_EXCHANGE: str = "transport_exchange"
    RABBITMQ_QUEUE: str = "transport_events"

    # Logging
    LOG_LEVEL: str = "INFO"
    LOG_FORMAT: str = "json"

    # Metrics
    METRICS_ENABLED: bool = True

    class Config:
        """Pydantic config."""
        env_file = ".env"
        case_sensitive = True


settings = Settings()
