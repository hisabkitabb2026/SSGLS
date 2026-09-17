"""Pytest configuration and fixtures."""
import os
import pytest
import jwt
from datetime import datetime, timedelta
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker, Session
from fastapi.testclient import TestClient
from config.settings import settings
from config.database import get_db, SessionLocal
from app.models import Base, Transport
from app.main import app

# Use SQLite in-memory for testing
TEST_DATABASE_URL = "sqlite:///:memory:"

# Create test engine
engine = create_engine(
    TEST_DATABASE_URL,
    connect_args={"check_same_thread": False},
    echo=False
)

TestingSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)


def override_get_db():
    """Override database dependency for testing."""
    try:
        db = TestingSessionLocal()
        yield db
    finally:
        db.close()


@pytest.fixture(scope="session")
def test_db():
    """Create test database and tables."""
    Base.metadata.create_all(bind=engine)
    yield engine
    Base.metadata.drop_all(bind=engine)


@pytest.fixture
def db_session(test_db):
    """Create a fresh database session for each test."""
    connection = engine.connect()
    transaction = connection.begin()
    session = TestingSessionLocal(bind=connection)

    yield session

    session.close()
    transaction.rollback()
    connection.close()


@pytest.fixture
def client(db_session):
    """Create test client with overridden database dependency."""
    app.dependency_overrides[get_db] = lambda: db_session
    client = TestClient(app)
    yield client
    app.dependency_overrides.clear()


@pytest.fixture
def valid_token():
    """Create a valid JWT token for testing."""
    payload = {
        "user_id": 1,
        "company_id": 1,
        "exp": datetime.utcnow() + timedelta(hours=24)
    }
    token = jwt.encode(
        payload,
        settings.JWT_SECRET_KEY,
        algorithm=settings.JWT_ALGORITHM
    )
    return token


@pytest.fixture
def expired_token():
    """Create an expired JWT token for testing."""
    payload = {
        "user_id": 1,
        "company_id": 1,
        "exp": datetime.utcnow() - timedelta(hours=1)
    }
    token = jwt.encode(
        payload,
        settings.JWT_SECRET_KEY,
        algorithm=settings.JWT_ALGORITHM
    )
    return token


@pytest.fixture
def auth_headers(valid_token):
    """Create authorization headers with valid token."""
    return {"Authorization": f"Bearer {valid_token}"}


@pytest.fixture
def sample_transport(db_session):
    """Create a sample transport record."""
    transport = Transport(
        company_id=1,
        tracking_number="TRK001",
        origin="New York",
        destination="Los Angeles",
        status="pending",
        distance_km=2800.0,
        estimated_cost=500.0,
        carrier_name="CarrierCo",
        vehicle_number="VH001"
    )
    db_session.add(transport)
    db_session.commit()
    db_session.refresh(transport)
    return transport


@pytest.fixture
def sample_transports(db_session):
    """Create multiple sample transport records."""
    transports = []
    for i in range(5):
        transport = Transport(
            company_id=1,
            tracking_number=f"TRK00{i+1}",
            origin=f"City {i}",
            destination=f"City {i+10}",
            status=["pending", "in_transit", "delivered"][i % 3],
            distance_km=1000.0 + (i * 100),
            estimated_cost=300.0 + (i * 50),
            carrier_name=f"Carrier{i}",
            vehicle_number=f"VH{i:03d}"
        )
        transports.append(transport)
        db_session.add(transport)
    db_session.commit()
    for t in transports:
        db_session.refresh(t)
    return transports
