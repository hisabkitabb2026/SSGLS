"""Main FastAPI application."""
from fastapi import FastAPI, Response
from fastapi.middleware.cors import CORSMiddleware
from prometheus_client import generate_latest, CONTENT_TYPE_LATEST
from config.settings import settings
from config.logging_config import setup_logging, logger
from config.database import init_db
from app.routes import transport_router
from app.schemas import HealthResponse
from app.events import publisher

# Setup logging
setup_logging()

# Initialize FastAPI app
app = FastAPI(
    title=settings.APP_NAME,
    version=settings.APP_VERSION,
    description="Transport Microservice API",
    docs_url="/api/docs",
    redoc_url="/api/redoc",
    openapi_url="/api/openapi.json"
)

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


@app.on_event("startup")
async def startup_event():
    """Initialize on application startup."""
    logger.info(f"Starting {settings.APP_NAME} v{settings.APP_VERSION}")

    # Initialize database
    try:
        init_db()
        logger.info("Database initialized successfully")
    except Exception as e:
        logger.error(f"Failed to initialize database: {str(e)}")
        raise

    # Initialize RabbitMQ connection
    try:
        publisher.connect()
        logger.info("RabbitMQ connection established")
    except Exception as e:
        logger.warning(f"RabbitMQ connection failed: {str(e)}")
        # Don't fail startup, RabbitMQ is optional for basic operations


@app.on_event("shutdown")
async def shutdown_event():
    """Cleanup on application shutdown."""
    logger.info("Shutting down application")
    try:
        publisher.disconnect()
        logger.info("RabbitMQ connection closed")
    except Exception as e:
        logger.warning(f"Error closing RabbitMQ: {str(e)}")


# Health check endpoint
@app.get(
    "/health",
    response_model=HealthResponse,
    tags=["health"]
)
async def health_check():
    """Health check endpoint."""
    # Check database
    try:
        from config.database import SessionLocal
        db = SessionLocal()
        db.execute("SELECT 1")
        db.close()
        db_status = "healthy"
    except Exception as e:
        logger.warning(f"Database health check failed: {str(e)}")
        db_status = "unhealthy"

    # Check RabbitMQ
    if publisher.connection and not publisher.connection.is_closed:
        rabbitmq_status = "healthy"
    else:
        rabbitmq_status = "unhealthy"

    return HealthResponse(
        status="healthy" if db_status == "healthy" else "degraded",
        version=settings.APP_VERSION,
        database=db_status,
        rabbitmq=rabbitmq_status
    )


# Metrics endpoint
@app.get(
    "/metrics",
    tags=["metrics"],
    response_class=Response
)
async def metrics():
    """Prometheus metrics endpoint."""
    return Response(
        content=generate_latest(),
        media_type=CONTENT_TYPE_LATEST
    )


# API routes
app.include_router(transport_router)


# Root endpoint
@app.get("/", tags=["root"])
async def root():
    """Root endpoint."""
    return {
        "service": settings.APP_NAME,
        "version": settings.APP_VERSION,
        "status": "running",
        "docs": "/api/docs"
    }


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "app.main:app",
        host=settings.HOST,
        port=settings.PORT,
        reload=settings.DEBUG
    )
