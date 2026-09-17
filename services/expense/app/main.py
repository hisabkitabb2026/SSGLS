"""FastAPI application entry point."""

import sys
from contextlib import asynccontextmanager
from datetime import datetime

import sentry_sdk
from fastapi import FastAPI, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse

from app import __version__
from app.api.routes import router
from app.config import get_settings
from app.middleware.jwt_middleware import JWTHandler
from app.models import DatabaseManager
from app.schemas import HealthResponse
from app.services.event_publisher import get_event_publisher
from app.utils.logging import LoggingMiddleware, configure_logging, get_logger

# Configure logging
settings = get_settings()
configure_logging(level=settings.log_level, format_type=settings.log_format)

logger = get_logger(__name__)

# Initialize Sentry if configured
if settings.sentry_dsn:
    sentry_sdk.init(
        dsn=settings.sentry_dsn,
        environment=settings.sentry_environment,
        traces_sample_rate=0.1,
    )


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Manage application lifecycle."""
    # Startup
    logger.msg("starting_application", version=__version__)

    # Initialize database
    try:
        DatabaseManager.initialize(
            settings.database_url,
            pool_size=settings.database_pool_size,
            max_overflow=settings.database_max_overflow,
            echo=settings.database_echo,
        )
        DatabaseManager.create_all()
        logger.msg("database_initialized")
    except Exception as e:
        logger.msg("database_initialization_failed", error=str(e))
        sys.exit(1)

    # Initialize RabbitMQ connection
    publisher = get_event_publisher()
    rabbitmq_connected = publisher.connect()
    if not rabbitmq_connected and not settings.debug:
        logger.msg("rabbitmq_connection_failed_startup")
        # Don't exit, just log the warning

    yield

    # Shutdown
    logger.msg("shutting_down_application")
    publisher.disconnect()


# Create FastAPI application
app = FastAPI(
    title="Expense Microservice",
    description="Expense tracking and management microservice",
    version=__version__,
    lifespan=lifespan,
)

# Add CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.allowed_origins_list,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Add logging middleware
app.add_middleware(LoggingMiddleware)


# Routes
app.include_router(router)


@app.get("/health", response_model=HealthResponse)
async def health_check():
    """Health check endpoint."""
    try:
        db = DatabaseManager.get_session()
        db.execute("SELECT 1")
        db.close()
        database_status = "healthy"
    except Exception as e:
        logger.msg("health_check_database_failed", error=str(e))
        database_status = "unhealthy"

    publisher = get_event_publisher()
    rabbitmq_status = "healthy" if publisher.is_connected() else "disconnected"

    return HealthResponse(
        status="healthy" if database_status == "healthy" else "degraded",
        service_name=settings.service_name,
        version=__version__,
        timestamp=datetime.utcnow(),
        database=database_status,
        rabbitmq=rabbitmq_status,
    )


@app.get("/metrics")
async def metrics():
    """Prometheus metrics endpoint."""
    if not settings.enable_metrics:
        return JSONResponse(
            status_code=status.HTTP_404_NOT_FOUND,
            content={"error": "Metrics endpoint disabled"},
        )

    # Basic metrics in Prometheus format
    metrics_data = """# HELP service_info Service information
# TYPE service_info gauge
service_info{service="expense_service",version="1.0.0"} 1

# HELP service_requests_total Total number of requests
# TYPE service_requests_total counter
service_requests_total 0

# HELP service_request_duration_seconds Request duration in seconds
# TYPE service_request_duration_seconds histogram
service_request_duration_seconds_bucket{le="0.1"} 0
service_request_duration_seconds_bucket{le="0.5"} 0
service_request_duration_seconds_bucket{le="1.0"} 0
service_request_duration_seconds_bucket{le="+Inf"} 0
service_request_duration_seconds_sum 0
service_request_duration_seconds_count 0

# HELP service_errors_total Total number of errors
# TYPE service_errors_total counter
service_errors_total 0
"""
    return metrics_data


@app.post("/auth/token")
async def get_token(user_id: int, company_id: int, permissions: list[str] = None):
    """Generate JWT token (for testing/internal use)."""
    try:
        jwt_handler = JWTHandler()
        token = jwt_handler.create_token(
            {
                "user_id": user_id,
                "company_id": company_id,
                "permissions": permissions or [],
            }
        )
        return {
            "access_token": token,
            "token_type": "bearer",
            "expires_in": settings.jwt_expiration_hours * 3600,
        }
    except Exception as e:
        logger.msg("token_generation_failed", error=str(e))
        return JSONResponse(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            content={"error": "Failed to generate token"},
        )


@app.exception_handler(Exception)
async def general_exception_handler(request, exc):
    """Global exception handler."""
    logger.msg("unhandled_exception", error=str(exc), path=request.url.path)
    return JSONResponse(
        status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
        content={"error": "Internal server error", "detail": str(exc)},
    )


if __name__ == "__main__":
    import uvicorn

    uvicorn.run(
        "app.main:app",
        host=settings.service_host,
        port=settings.service_port,
        reload=settings.debug,
        log_config=None,  # Use our custom logging
    )
