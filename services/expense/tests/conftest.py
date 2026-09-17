"""Pytest configuration and shared fixtures."""

import os
from datetime import datetime, timedelta
from decimal import Decimal

import pytest
from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker

from app.main import app
from app.middleware.jwt_middleware import JWTHandler
from app.models import Base, DatabaseManager, Expense, ExpenseApproval, ExpenseCategory
from app.services.event_publisher import EventPublisher


# Test database setup
TEST_DATABASE_URL = "sqlite:///:memory:"


@pytest.fixture(scope="session")
def test_db_engine():
    """Create test database engine."""
    engine = create_engine(TEST_DATABASE_URL, connect_args={"check_same_thread": False})
    Base.metadata.create_all(engine)
    yield engine
    Base.metadata.drop_all(engine)


@pytest.fixture
def test_db(test_db_engine):
    """Get a test database session."""
    TestSession = sessionmaker(bind=test_db_engine)
    session = TestSession()
    yield session
    session.rollback()
    session.close()


@pytest.fixture
def mock_event_publisher(monkeypatch):
    """Mock event publisher."""
    class MockPublisher:
        def __init__(self):
            self.events = []
            self.connected = True

        def connect(self):
            return True

        def disconnect(self):
            pass

        def is_connected(self):
            return self.connected

        def publish_event(self, event_type, data, routing_key=None):
            self.events.append({"type": event_type, "data": data})
            return True

        def publish_expense_created(self, expense_id, company_id, user_id):
            return self.publish_event("expense.created", {
                "expense_id": expense_id,
                "company_id": company_id,
                "user_id": user_id,
            })

        def publish_expense_updated(self, expense_id, company_id, changes):
            return self.publish_event("expense.updated", {
                "expense_id": expense_id,
                "company_id": company_id,
                "changes": changes,
            })

        def publish_expense_deleted(self, expense_id, company_id):
            return self.publish_event("expense.deleted", {
                "expense_id": expense_id,
                "company_id": company_id,
            })

        def publish_expense_approved(self, expense_id, company_id, approver_id):
            return self.publish_event("expense.approved", {
                "expense_id": expense_id,
                "company_id": company_id,
                "approver_id": approver_id,
            })

        def publish_expense_rejected(self, expense_id, company_id, approver_id, reason):
            return self.publish_event("expense.rejected", {
                "expense_id": expense_id,
                "company_id": company_id,
                "approver_id": approver_id,
                "reason": reason,
            })

    publisher = MockPublisher()

    def mock_get_publisher():
        return publisher

    from app.services import event_publisher
    monkeypatch.setattr(event_publisher, "get_event_publisher", mock_get_publisher)

    return publisher


@pytest.fixture
def test_client(test_db, mock_event_publisher, monkeypatch):
    """Create FastAPI test client."""
    def override_get_db():
        return test_db

    app.dependency_overrides[__import__('app.api.routes', fromlist=['get_db']).get_db] = override_get_db

    yield TestClient(app)

    app.dependency_overrides.clear()


@pytest.fixture
def jwt_handler():
    """Get JWT handler."""
    return JWTHandler()


@pytest.fixture
def valid_token(jwt_handler):
    """Create a valid JWT token."""
    token = jwt_handler.create_token({
        "user_id": 1,
        "company_id": 1,
        "permissions": ["admin", "approve_expenses"],
    })
    return token


@pytest.fixture
def auth_header(valid_token):
    """Get authorization header."""
    return {"Authorization": f"Bearer {valid_token}"}


@pytest.fixture
def sample_expense_data():
    """Create sample expense data."""
    return {
        "category": "travel",
        "amount": Decimal("150.00"),
        "currency_code": "USD",
        "description": "Business trip",
        "merchant_name": "Airlines Inc",
        "expense_date": datetime.utcnow(),
        "payment_method": "credit_card",
        "receipt_url": "https://example.com/receipt.pdf",
    }


@pytest.fixture
def sample_expense(test_db, sample_expense_data):
    """Create a sample expense in database."""
    expense = Expense(
        company_id=1,
        user_id=1,
        category=sample_expense_data["category"],
        amount=sample_expense_data["amount"],
        currency_code=sample_expense_data["currency_code"],
        description=sample_expense_data["description"],
        merchant_name=sample_expense_data["merchant_name"],
        expense_date=sample_expense_data["expense_date"],
        payment_method=sample_expense_data["payment_method"],
        receipt_url=sample_expense_data["receipt_url"],
        status="pending",
    )
    test_db.add(expense)
    test_db.commit()
    test_db.refresh(expense)
    return expense


@pytest.fixture
def sample_category(test_db):
    """Create a sample expense category."""
    category = ExpenseCategory(
        company_id=1,
        name="Travel",
        description="Travel expenses",
        color_code="#FF5733",
        is_active="Y",
    )
    test_db.add(category)
    test_db.commit()
    test_db.refresh(category)
    return category


@pytest.fixture
def multiple_expenses(test_db, sample_expense_data):
    """Create multiple sample expenses."""
    expenses = []
    for i in range(5):
        expense = Expense(
            company_id=1,
            user_id=1,
            category=sample_expense_data["category"],
            amount=Decimal("100.00") + Decimal(i * 10),
            currency_code="USD",
            description=f"Expense {i+1}",
            merchant_name="Test Merchant",
            expense_date=datetime.utcnow() - timedelta(days=i),
            status="pending",
        )
        test_db.add(expense)
        expenses.append(expense)

    test_db.commit()
    for expense in expenses:
        test_db.refresh(expense)

    return expenses
