"""Database configuration and session management."""
from sqlalchemy import create_engine, event
from sqlalchemy.orm import sessionmaker, Session
from sqlalchemy.pool import NullPool
from config.settings import settings

# Create engine
engine = create_engine(
    settings.DATABASE_URL,
    echo=settings.DATABASE_ECHO,
    poolclass=NullPool,
)

# Session factory
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)


def get_db() -> Session:
    """Dependency for database session."""
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()


def init_db():
    """Initialize database tables."""
    from app.models import Base
    Base.metadata.create_all(bind=engine)
