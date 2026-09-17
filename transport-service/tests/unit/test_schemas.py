"""Unit tests for Pydantic schemas."""
import pytest
from pydantic import ValidationError
from app.schemas import (
    TransportCreate,
    TransportUpdate,
    TransportResponse,
    HealthResponse,
    ErrorResponse
)
from datetime import datetime


class TestTransportCreateSchema:
    """Test suite for TransportCreate schema."""

    def test_valid_minimal_data(self):
        """Test with minimal required data."""
        data = {
            "tracking_number": "TRK001",
            "origin": "New York",
            "destination": "Boston",
            "estimated_cost": 250.0
        }

        schema = TransportCreate(**data)

        assert schema.tracking_number == "TRK001"
        assert schema.origin == "New York"
        assert schema.destination == "Boston"
        assert schema.estimated_cost == 250.0
        assert schema.status == "pending"  # Default

    def test_valid_full_data(self):
        """Test with all fields."""
        data = {
            "tracking_number": "TRK002",
            "origin": "NYC",
            "destination": "LAX",
            "status": "in_transit",
            "distance_km": 2800.0,
            "estimated_cost": 500.0,
            "actual_cost": 450.0,
            "carrier_name": "CarrierCo",
            "vehicle_number": "VH001",
            "driver_name": "John Doe",
            "driver_contact": "5551234567",
            "scheduled_date": datetime.now(),
            "actual_departure": datetime.now(),
            "actual_arrival": datetime.now(),
            "is_active": True
        }

        schema = TransportCreate(**data)

        assert schema.tracking_number == "TRK002"
        assert schema.status == "in_transit"
        assert schema.carrier_name == "CarrierCo"

    def test_invalid_status(self):
        """Test invalid status value."""
        data = {
            "tracking_number": "TRK003",
            "origin": "A",
            "destination": "B",
            "status": "invalid_status",
            "estimated_cost": 100.0
        }

        with pytest.raises(ValidationError):
            TransportCreate(**data)

    def test_missing_required_field(self):
        """Test missing required field."""
        data = {
            "origin": "A",
            "destination": "B",
            "estimated_cost": 100.0
            # Missing tracking_number
        }

        with pytest.raises(ValidationError):
            TransportCreate(**data)

    def test_negative_cost(self):
        """Test negative cost."""
        data = {
            "tracking_number": "TRK004",
            "origin": "A",
            "destination": "B",
            "estimated_cost": -100.0
        }

        with pytest.raises(ValidationError):
            TransportCreate(**data)

    def test_zero_cost_valid(self):
        """Test zero cost is valid."""
        data = {
            "tracking_number": "TRK005",
            "origin": "A",
            "destination": "B",
            "estimated_cost": 0.0
        }

        schema = TransportCreate(**data)
        assert schema.estimated_cost == 0.0

    def test_empty_tracking_number(self):
        """Test empty tracking number."""
        data = {
            "tracking_number": "",
            "origin": "A",
            "destination": "B",
            "estimated_cost": 100.0
        }

        with pytest.raises(ValidationError):
            TransportCreate(**data)


class TestTransportUpdateSchema:
    """Test suite for TransportUpdate schema."""

    def test_update_single_field(self):
        """Test updating single field."""
        data = {"status": "delivered"}

        schema = TransportUpdate(**data)

        assert schema.status == "delivered"
        assert schema.tracking_number is None

    def test_update_multiple_fields(self):
        """Test updating multiple fields."""
        data = {
            "status": "in_transit",
            "actual_cost": 450.0,
            "vehicle_number": "VH002"
        }

        schema = TransportUpdate(**data)

        assert schema.status == "in_transit"
        assert schema.actual_cost == 450.0
        assert schema.vehicle_number == "VH002"

    def test_update_empty_is_valid(self):
        """Test empty update is valid."""
        data = {}

        schema = TransportUpdate(**data)

        assert schema.status is None
        assert schema.actual_cost is None

    def test_update_invalid_status(self):
        """Test invalid status in update."""
        data = {"status": "invalid"}

        with pytest.raises(ValidationError):
            TransportUpdate(**data)

    def test_update_exclude_unset(self):
        """Test model_dump with exclude_unset."""
        schema = TransportUpdate(status="delivered", actual_cost=500.0)

        dump = schema.model_dump(exclude_unset=True)

        assert dump["status"] == "delivered"
        assert dump["actual_cost"] == 500.0
        assert "tracking_number" not in dump


class TestTransportResponseSchema:
    """Test suite for TransportResponse schema."""

    def test_response_from_dict(self):
        """Test creating response from dict."""
        data = {
            "id": 1,
            "company_id": 1,
            "tracking_number": "TRK001",
            "origin": "NYC",
            "destination": "LAX",
            "status": "pending",
            "distance_km": 2800.0,
            "estimated_cost": 500.0,
            "actual_cost": None,
            "carrier_name": "CarrierCo",
            "vehicle_number": None,
            "driver_name": None,
            "driver_contact": None,
            "scheduled_date": None,
            "actual_departure": None,
            "actual_arrival": None,
            "created_at": datetime.now(),
            "updated_at": datetime.now(),
            "is_active": True
        }

        response = TransportResponse(**data)

        assert response.id == 1
        assert response.company_id == 1
        assert response.tracking_number == "TRK001"

    def test_response_has_required_fields(self):
        """Test response has required fields."""
        data = {
            "id": 1,
            "company_id": 1,
            "tracking_number": "TRK001",
            "origin": "A",
            "destination": "B",
            "status": "pending",
            "estimated_cost": 100.0,
            "created_at": datetime.now(),
            "updated_at": datetime.now()
        }

        response = TransportResponse(**data)

        assert hasattr(response, "id")
        assert hasattr(response, "company_id")
        assert hasattr(response, "tracking_number")


class TestHealthResponseSchema:
    """Test suite for HealthResponse schema."""

    def test_health_response(self):
        """Test health response schema."""
        data = {
            "status": "healthy",
            "version": "1.0.0",
            "database": "healthy",
            "rabbitmq": "healthy"
        }

        response = HealthResponse(**data)

        assert response.status == "healthy"
        assert response.version == "1.0.0"
        assert response.database == "healthy"
        assert response.rabbitmq == "healthy"

    def test_health_degraded(self):
        """Test degraded health status."""
        data = {
            "status": "degraded",
            "version": "1.0.0",
            "database": "unhealthy",
            "rabbitmq": "healthy"
        }

        response = HealthResponse(**data)

        assert response.status == "degraded"
        assert response.database == "unhealthy"


class TestErrorResponseSchema:
    """Test suite for ErrorResponse schema."""

    def test_error_response_minimal(self):
        """Test minimal error response."""
        data = {
            "error": "Not Found",
            "status_code": 404
        }

        response = ErrorResponse(**data)

        assert response.error == "Not Found"
        assert response.status_code == 404
        assert response.detail is None

    def test_error_response_with_detail(self):
        """Test error response with detail."""
        data = {
            "error": "Validation Error",
            "detail": "Invalid tracking number format",
            "status_code": 400
        }

        response = ErrorResponse(**data)

        assert response.error == "Validation Error"
        assert response.detail == "Invalid tracking number format"
        assert response.status_code == 400
