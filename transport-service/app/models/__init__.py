"""Database models."""
from sqlalchemy.orm import declarative_base

Base = declarative_base()

from app.models.transport import Transport  # noqa: E402

__all__ = ["Base", "Transport"]
