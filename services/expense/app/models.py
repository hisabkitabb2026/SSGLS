"""SQLAlchemy models for expense tracking."""

from datetime import datetime
from decimal import Decimal

from sqlalchemy import (
    JSON,
    Column,
    DateTime,
    Enum,
    Index,
    Integer,
    Numeric,
    String,
    Text,
    Unicode,
    create_engine,
)
from sqlalchemy.orm import declarative_base, sessionmaker

Base = declarative_base()


class Expense(Base):
    """Expense model representing company expense records."""

    __tablename__ = "expenses"

    id = Column(Integer, primary_key=True)
    company_id = Column(Integer, nullable=False, index=True)
    user_id = Column(Integer, nullable=False, index=True)
    category = Column(String(50), nullable=False, index=True)
    amount = Column(Numeric(13, 2), nullable=False)
    currency_code = Column(String(3), nullable=False, default="USD")
    description = Column(Text, nullable=True)
    merchant_name = Column(String(255), nullable=True)
    expense_date = Column(DateTime, nullable=False, index=True)
    status = Column(
        String(20),
        nullable=False,
        default="pending",
        index=True,
    )
    payment_method = Column(String(50), nullable=True)
    receipt_url = Column(String(500), nullable=True)
    attachments = Column(JSON, nullable=True, default=list)
    metadata = Column(JSON, nullable=True, default=dict)
    created_at = Column(DateTime, nullable=False, default=datetime.utcnow)
    updated_at = Column(
        DateTime,
        nullable=False,
        default=datetime.utcnow,
        onupdate=datetime.utcnow,
    )

    __table_args__ = (
        Index(
            "idx_expenses_company_user_date",
            company_id,
            user_id,
            expense_date,
        ),
        Index("idx_expenses_company_status", company_id, status),
        Index("idx_expenses_company_category", company_id, category),
    )


class ExpenseApproval(Base):
    """Approval workflow tracking for expenses."""

    __tablename__ = "expense_approvals"

    id = Column(Integer, primary_key=True)
    expense_id = Column(Integer, nullable=False, index=True)
    company_id = Column(Integer, nullable=False, index=True)
    approver_id = Column(Integer, nullable=False)
    approval_level = Column(Integer, nullable=False, default=1)
    status = Column(
        String(20),
        nullable=False,
        default="pending",
    )
    comment = Column(Text, nullable=True)
    decision_date = Column(DateTime, nullable=True)
    created_at = Column(DateTime, nullable=False, default=datetime.utcnow)
    updated_at = Column(
        DateTime,
        nullable=False,
        default=datetime.utcnow,
        onupdate=datetime.utcnow,
    )

    __table_args__ = (
        Index("idx_approvals_expense_company", expense_id, company_id),
        Index("idx_approvals_approver", company_id, approver_id),
    )


class ExpenseCategory(Base):
    """Custom expense categories per company."""

    __tablename__ = "expense_categories"

    id = Column(Integer, primary_key=True)
    company_id = Column(Integer, nullable=False, index=True)
    name = Column(String(100), nullable=False)
    description = Column(Text, nullable=True)
    color_code = Column(String(7), nullable=True)
    is_active = Column(String(1), nullable=False, default="Y")
    created_at = Column(DateTime, nullable=False, default=datetime.utcnow)
    updated_at = Column(
        DateTime,
        nullable=False,
        default=datetime.utcnow,
        onupdate=datetime.utcnow,
    )

    __table_args__ = (
        Index("idx_categories_company_active", company_id, is_active),
    )


class DatabaseManager:
    """Database connection and session management."""

    _engine = None
    _SessionLocal = None

    @classmethod
    def initialize(cls, database_url: str, **kwargs):
        """Initialize database engine and session factory."""
        cls._engine = create_engine(
            database_url,
            pool_size=kwargs.get("pool_size", 20),
            max_overflow=kwargs.get("max_overflow", 40),
            echo=kwargs.get("echo", False),
            connect_args={"connect_timeout": 10},
        )
        cls._SessionLocal = sessionmaker(bind=cls._engine, expire_on_commit=False)

    @classmethod
    def get_session(cls):
        """Get a new database session."""
        if cls._SessionLocal is None:
            raise RuntimeError("Database not initialized. Call initialize() first.")
        return cls._SessionLocal()

    @classmethod
    def create_all(cls):
        """Create all tables in the database."""
        if cls._engine is None:
            raise RuntimeError("Database not initialized. Call initialize() first.")
        Base.metadata.create_all(cls._engine)

    @classmethod
    def drop_all(cls):
        """Drop all tables from the database (development only)."""
        if cls._engine is None:
            raise RuntimeError("Database not initialized. Call initialize() first.")
        Base.metadata.drop_all(cls._engine)

    @classmethod
    def get_engine(cls):
        """Get the database engine."""
        if cls._engine is None:
            raise RuntimeError("Database not initialized. Call initialize() first.")
        return cls._engine
