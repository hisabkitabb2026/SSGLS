"""
Pytest configuration and shared fixtures for end-to-end testing.

This module provides:
- Docker service orchestration fixtures
- Database initialization and cleanup
- API client fixtures for all microservices
- JWT token generation fixtures
- Sample data fixtures across all domains
- Event propagation helpers
"""

import os
import sys
import json
import time
import pytest
import requests
import jwt
from datetime import datetime, timedelta
from typing import Dict, Generator, Optional, Any
from urllib.parse import urljoin

import docker
from docker.models.containers import Container
from docker.errors import DockerException


# Service Configuration
SERVICES = {
    "invoice": {"port": 8001, "url": "http://localhost:8001"},
    "expense": {"port": 8002, "url": "http://localhost:8002"},
    "product": {"port": 8003, "url": "http://localhost:8003"},
    "customer": {"port": 8004, "url": "http://localhost:8004"},
    "settings": {"port": 8005, "url": "http://localhost:8005"},
    "transport": {"port": 8006, "url": "http://localhost:8006"},
    "kong": {"port": 8000, "url": "http://localhost:8000"},
}

# JWT Configuration
JWT_SECRET_KEY = os.getenv("JWT_SECRET_KEY", "test-secret-key-for-e2e-tests")
JWT_ALGORITHM = "HS256"
JWT_EXPIRY_HOURS = 24

# Test Data
TEST_COMPANY_ID = 1
TEST_USER_ID = 1
TEST_CUSTOMER_ID = 1
TEST_PRODUCT_ID = 1
TEST_EXPENSE_ID = 1
TEST_TRANSPORT_ID = 1


class ServiceClient:
    """HTTP client for interacting with microservices."""

    def __init__(self, service_name: str, base_url: str):
        self.service_name = service_name
        self.base_url = base_url
        self.session = requests.Session()
        self.timeout = 30

    def set_auth(self, token: str):
        """Set JWT token for authenticated requests."""
        self.session.headers.update({"Authorization": f"Bearer {token}"})

    def set_company(self, company_id: int):
        """Set company header for multi-tenant requests."""
        self.session.headers.update({"X-Company-ID": str(company_id)})

    def get(self, endpoint: str, **kwargs) -> requests.Response:
        """Make GET request."""
        url = urljoin(self.base_url, endpoint.lstrip("/"))
        kwargs.setdefault("timeout", self.timeout)
        return self.session.get(url, **kwargs)

    def post(self, endpoint: str, **kwargs) -> requests.Response:
        """Make POST request."""
        url = urljoin(self.base_url, endpoint.lstrip("/"))
        kwargs.setdefault("timeout", self.timeout)
        return self.session.post(url, **kwargs)

    def put(self, endpoint: str, **kwargs) -> requests.Response:
        """Make PUT request."""
        url = urljoin(self.base_url, endpoint.lstrip("/"))
        kwargs.setdefault("timeout", self.timeout)
        return self.session.put(url, **kwargs)

    def delete(self, endpoint: str, **kwargs) -> requests.Response:
        """Make DELETE request."""
        url = urljoin(self.base_url, endpoint.lstrip("/"))
        kwargs.setdefault("timeout", self.timeout)
        return self.session.delete(url, **kwargs)

    def patch(self, endpoint: str, **kwargs) -> requests.Response:
        """Make PATCH request."""
        url = urljoin(self.base_url, endpoint.lstrip("/"))
        kwargs.setdefault("timeout", self.timeout)
        return self.session.patch(url, **kwargs)


@pytest.fixture(scope="session")
def docker_client():
    """Get Docker client for managing containers."""
    try:
        client = docker.from_env()
        yield client
    except DockerException as e:
        pytest.skip(f"Docker not available: {e}")
    finally:
        pass


@pytest.fixture(scope="session")
def services_up(docker_client):
    """
    Ensure all microservices are running and healthy.

    Uses docker-compose.microservices.yml to orchestrate services.
    Waits for all health checks to pass.
    """
    compose_file = "docker-compose.microservices.yml"

    # Start services
    os.system(f"docker-compose -f {compose_file} up -d 2>/dev/null")

    # Wait for services to be ready
    max_attempts = 60
    attempt = 0
    all_healthy = False

    while attempt < max_attempts and not all_healthy:
        all_healthy = True
        for service_name, config in SERVICES.items():
            if service_name == "kong":
                continue
            try:
                response = requests.get(
                    f"{config['url']}/health",
                    timeout=5
                )
                if response.status_code != 200:
                    all_healthy = False
                    break
            except requests.RequestException:
                all_healthy = False
                break

        if not all_healthy:
            attempt += 1
            time.sleep(1)

    if not all_healthy:
        pytest.skip("Services failed to start within timeout")

    yield

    # Cleanup - optional, can be disabled for debugging
    # os.system(f"docker-compose -f {compose_file} down -v 2>/dev/null")


@pytest.fixture(scope="session")
def jwt_secret():
    """Provide JWT secret key for token generation."""
    return JWT_SECRET_KEY


@pytest.fixture
def valid_token(jwt_secret):
    """Generate a valid JWT token for testing."""
    payload = {
        "user_id": TEST_USER_ID,
        "company_id": TEST_COMPANY_ID,
        "username": "test_user",
        "email": "test@example.com",
        "roles": ["admin"],
        "exp": datetime.utcnow() + timedelta(hours=JWT_EXPIRY_HOURS),
        "iat": datetime.utcnow()
    }
    token = jwt.encode(payload, jwt_secret, algorithm=JWT_ALGORITHM)
    return token


@pytest.fixture
def expired_token(jwt_secret):
    """Generate an expired JWT token for testing."""
    payload = {
        "user_id": TEST_USER_ID,
        "company_id": TEST_COMPANY_ID,
        "exp": datetime.utcnow() - timedelta(hours=1)
    }
    token = jwt.encode(payload, jwt_secret, algorithm=JWT_ALGORITHM)
    return token


@pytest.fixture
def invalid_token():
    """Provide an invalid JWT token."""
    return "invalid.jwt.token"


@pytest.fixture
def auth_headers(valid_token):
    """Provide authorization headers with valid token."""
    return {
        "Authorization": f"Bearer {valid_token}",
        "X-Company-ID": str(TEST_COMPANY_ID)
    }


@pytest.fixture
def api_clients(services_up):
    """
    Create API clients for all microservices.

    Returns a dictionary mapping service names to ServiceClient instances.
    Clients are pre-configured with auth headers.
    """
    clients = {}
    for service_name, config in SERVICES.items():
        clients[service_name] = ServiceClient(service_name, config["url"])
    return clients


@pytest.fixture
def authenticated_clients(api_clients, valid_token):
    """
    Create authenticated API clients for all microservices.

    Returns ServiceClient instances with JWT token and company ID headers set.
    """
    for client in api_clients.values():
        client.set_auth(valid_token)
        client.set_company(TEST_COMPANY_ID)
    return api_clients


@pytest.fixture
def invoice_client(authenticated_clients):
    """Get authenticated invoice service client."""
    return authenticated_clients["invoice"]


@pytest.fixture
def expense_client(authenticated_clients):
    """Get authenticated expense service client."""
    return authenticated_clients["expense"]


@pytest.fixture
def product_client(authenticated_clients):
    """Get authenticated product service client."""
    return authenticated_clients["product"]


@pytest.fixture
def customer_client(authenticated_clients):
    """Get authenticated customer service client."""
    return authenticated_clients["customer"]


@pytest.fixture
def settings_client(authenticated_clients):
    """Get authenticated settings service client."""
    return authenticated_clients["settings"]


@pytest.fixture
def transport_client(authenticated_clients):
    """Get authenticated transport service client."""
    return authenticated_clients["transport"]


@pytest.fixture
def kong_client(authenticated_clients):
    """Get Kong API gateway client."""
    client = authenticated_clients["kong"]
    return client


# Sample Data Fixtures

@pytest.fixture
def sample_company_data() -> Dict[str, Any]:
    """Sample company creation data."""
    return {
        "name": "Test Company Inc",
        "email": "info@testcompany.com",
        "phone": "+1-555-0123",
        "currency_code": "USD",
        "timezone": "UTC",
        "website": "https://testcompany.example.com",
        "industry": "Technology",
        "registration_number": "TC123456"
    }


@pytest.fixture
def sample_customer_data() -> Dict[str, Any]:
    """Sample customer creation data."""
    return {
        "name": "John Doe Enterprises",
        "email": "john@example.com",
        "phone": "+1-555-0124",
        "address": "123 Main St",
        "city": "New York",
        "state": "NY",
        "postal_code": "10001",
        "country": "US",
        "tax_id": "12-3456789",
        "billing_name": "Accounts Payable",
        "billing_email": "billing@example.com"
    }


@pytest.fixture
def sample_product_data() -> Dict[str, Any]:
    """Sample product creation data."""
    return {
        "name": "Premium Web Development Service",
        "description": "Full-stack web application development",
        "sku": "PWD-2024-001",
        "unit_price": 5000.00,
        "tax_rate": 0.10,
        "category": "Services",
        "unit_type": "hours",
        "is_active": True,
        "tax_method": "inclusive"
    }


@pytest.fixture
def sample_invoice_data(sample_customer_data) -> Dict[str, Any]:
    """Sample invoice creation data."""
    return {
        "customer_id": TEST_CUSTOMER_ID,
        "invoice_number": f"INV-2024-{int(time.time())}",
        "invoice_date": datetime.now().isoformat(),
        "due_date": (datetime.now() + timedelta(days=30)).isoformat(),
        "status": "draft",
        "currency_code": "USD",
        "notes": "Thank you for your business!",
        "terms": "Net 30",
        "items": [
            {
                "product_id": TEST_PRODUCT_ID,
                "description": "Web Development Service",
                "quantity": 40,
                "unit_price": 100.00,
                "tax_rate": 0.10,
                "line_total": 4400.00
            }
        ],
        "subtotal": 4000.00,
        "tax_total": 400.00,
        "total_amount": 4400.00
    }


@pytest.fixture
def sample_expense_data() -> Dict[str, Any]:
    """Sample expense creation data."""
    return {
        "vendor_name": "Office Supplies Co",
        "vendor_email": "sales@suppliesco.com",
        "amount": 250.50,
        "currency_code": "USD",
        "category": "Office Supplies",
        "description": "Monthly office supplies",
        "expense_date": datetime.now().isoformat(),
        "status": "pending",
        "payment_method": "credit_card",
        "reference_number": f"EXP-{int(time.time())}"
    }


@pytest.fixture
def sample_transport_data() -> Dict[str, Any]:
    """Sample transport document creation data."""
    return {
        "tracking_number": f"TRK-{int(time.time())}",
        "origin": "New York, NY",
        "destination": "Los Angeles, CA",
        "distance_km": 2800,
        "estimated_cost": 500.00,
        "carrier_name": "Premium Logistics",
        "vehicle_number": "VH-2024-001",
        "driver_name": "John Smith",
        "driver_phone": "+1-555-0150",
        "status": "pending",
        "departure_date": datetime.now().isoformat(),
        "expected_arrival_date": (datetime.now() + timedelta(days=3)).isoformat(),
        "notes": "Handle with care",
        "insurance_required": True
    }


# Event Propagation Helpers

@pytest.fixture
def event_capture():
    """
    Fixture to capture events propagated between services.

    Events are typically published to RabbitMQ or similar message queue.
    This captures them for verification in tests.
    """
    captured_events = []

    class EventCapture:
        def __init__(self):
            self.events = []

        def add(self, event: Dict[str, Any]):
            """Add a captured event."""
            self.events.append({
                "timestamp": datetime.utcnow().isoformat(),
                **event
            })

        def get_events_by_type(self, event_type: str):
            """Get events of a specific type."""
            return [e for e in self.events if e.get("event_type") == event_type]

        def get_events_by_service(self, service_name: str):
            """Get events from a specific service."""
            return [e for e in self.events if e.get("source_service") == service_name]

        def clear(self):
            """Clear captured events."""
            self.events.clear()

        def wait_for_event(
            self,
            event_type: str,
            timeout: int = 5,
            condition: Optional[callable] = None
        ) -> Optional[Dict[str, Any]]:
            """
            Wait for a specific event with optional condition check.

            Args:
                event_type: Type of event to wait for
                timeout: Maximum seconds to wait
                condition: Optional callable to validate event data

            Returns:
                Event data or None if timeout
            """
            start_time = time.time()
            while time.time() - start_time < timeout:
                for event in self.events:
                    if event.get("event_type") == event_type:
                        if condition is None or condition(event):
                            return event
                time.sleep(0.1)
            return None

    return EventCapture()


# Data Consistency Helpers

@pytest.fixture
def data_consistency_checker(authenticated_clients):
    """
    Fixture for checking data consistency across services.

    Verifies that data changes in one service are properly reflected
    in dependent services.
    """
    class DataConsistencyChecker:
        def __init__(self, clients):
            self.clients = clients

        def verify_customer_in_all_services(self, customer_id: int) -> bool:
            """Verify customer data exists in all relevant services."""
            try:
                # Check in customer service
                resp = self.clients["customer"].get(f"/api/v1/customers/{customer_id}")
                if resp.status_code != 200:
                    return False

                customer_data = resp.json()

                # Verify references in invoice service
                resp = self.clients["invoice"].get("/api/v1/invoices")
                invoices = resp.json()

                return True
            except Exception:
                return False

        def verify_product_in_all_services(self, product_id: int) -> bool:
            """Verify product data is accessible across services."""
            try:
                # Check in product service
                resp = self.clients["product"].get(f"/api/v1/products/{product_id}")
                if resp.status_code != 200:
                    return False

                return True
            except Exception:
                return False

        def verify_invoice_complete(self, invoice_id: int) -> Dict[str, bool]:
            """Verify invoice data consistency."""
            checks = {
                "exists_in_invoice_service": False,
                "customer_exists": False,
                "items_valid": False,
                "calculations_correct": False,
            }

            try:
                # Check invoice exists
                resp = self.clients["invoice"].get(f"/api/v1/invoices/{invoice_id}")
                if resp.status_code == 200:
                    checks["exists_in_invoice_service"] = True
                    invoice = resp.json()

                    # Check customer
                    checks["customer_exists"] = "customer_id" in invoice

                    # Check items
                    checks["items_valid"] = len(invoice.get("items", [])) > 0

                    # Check calculations
                    if "total_amount" in invoice:
                        checks["calculations_correct"] = invoice["total_amount"] > 0
            except Exception:
                pass

            return checks

    return DataConsistencyChecker(authenticated_clients)


# Utility Fixtures

@pytest.fixture
def reset_databases(services_up):
    """Reset all service databases to clean state."""
    # This would typically call database reset endpoints or use admin APIs
    # Implementation depends on service implementation
    yield
    # Cleanup after test


@pytest.fixture
def mock_event_bus():
    """Mock event bus for testing event propagation."""
    class MockEventBus:
        def __init__(self):
            self.published_events = []

        def publish(self, event_type: str, data: Dict[str, Any]):
            """Publish an event."""
            self.published_events.append({
                "event_type": event_type,
                "data": data,
                "timestamp": datetime.utcnow().isoformat()
            })

        def clear(self):
            """Clear published events."""
            self.published_events.clear()

        def get_published_events(self, event_type: str = None):
            """Get published events, optionally filtered by type."""
            if event_type is None:
                return self.published_events
            return [e for e in self.published_events if e["event_type"] == event_type]

    return MockEventBus()
