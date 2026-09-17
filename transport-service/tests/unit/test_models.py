"""Unit tests for database models."""
import pytest
from datetime import datetime
from app.models import Transport


class TestTransportModel:
    """Test suite for Transport model."""

    def test_transport_creation(self):
        """Test creating a transport instance."""
        transport = Transport(
            company_id=1,
            tracking_number="TRK001",
            origin="New York",
            destination="Los Angeles",
            status="pending",
            distance_km=2800.0,
            estimated_cost=500.0
        )

        assert transport.company_id == 1
        assert transport.tracking_number == "TRK001"
        assert transport.status == "pending"
        assert transport.is_active is True
        assert isinstance(transport.created_at, datetime)

    def test_transport_defaults(self):
        """Test default values."""
        transport = Transport(
            company_id=1,
            tracking_number="TRK002",
            origin="A",
            destination="B",
            estimated_cost=100.0
        )

        assert transport.status == "pending"
        assert transport.is_active is True
        assert transport.actual_cost is None

    def test_transport_validate_status(self):
        """Test status validation."""
        transport = Transport(
            company_id=1,
            tracking_number="TRK003",
            origin="A",
            destination="B",
            estimated_cost=100.0
        )

        # Valid statuses
        valid_statuses = ["pending", "in_transit", "delivered", "cancelled"]
        for status in valid_statuses:
            transport.status = status
            assert transport.status == status

        # Invalid status
        with pytest.raises(ValueError, match="Invalid status"):
            transport.status = "invalid"

    def test_transport_to_dict(self):
        """Test converting transport to dictionary."""
        transport = Transport(
            company_id=1,
            tracking_number="TRK004",
            origin="Origin",
            destination="Destination",
            status="in_transit",
            distance_km=100.0,
            estimated_cost=200.0,
            actual_cost=250.0,
            carrier_name="CarrierCo",
            vehicle_number="VH001"
        )

        data = transport.to_dict()

        assert isinstance(data, dict)
        assert data["company_id"] == 1
        assert data["tracking_number"] == "TRK004"
        assert data["status"] == "in_transit"
        assert data["distance_km"] == 100.0
        assert data["estimated_cost"] == 200.0
        assert data["actual_cost"] == 250.0

    def test_transport_repr(self):
        """Test string representation."""
        transport = Transport(
            company_id=1,
            tracking_number="TRK005",
            origin="A",
            destination="B",
            estimated_cost=100.0
        )

        repr_str = repr(transport)
        assert "Transport" in repr_str
        assert "TRK005" in repr_str

    def test_transport_updated_at_changes(self, db_session):
        """Test updated_at timestamp changes."""
        transport = Transport(
            company_id=1,
            tracking_number="TRK006",
            origin="A",
            destination="B",
            estimated_cost=100.0
        )
        db_session.add(transport)
        db_session.commit()
        db_session.refresh(transport)

        original_updated_at = transport.updated_at

        # Modify and save
        transport.status = "in_transit"
        db_session.commit()
        db_session.refresh(transport)

        assert transport.updated_at >= original_updated_at

    def test_transport_audit_fields(self, db_session):
        """Test audit fields are set correctly."""
        before = datetime.utcnow()

        transport = Transport(
            company_id=1,
            tracking_number="TRK007",
            origin="A",
            destination="B",
            estimated_cost=100.0
        )
        db_session.add(transport)
        db_session.commit()
        db_session.refresh(transport)

        after = datetime.utcnow()

        assert before <= transport.created_at <= after
        assert before <= transport.updated_at <= after

    def test_transport_financial_fields(self):
        """Test financial field handling."""
        transport = Transport(
            company_id=1,
            tracking_number="TRK008",
            origin="A",
            destination="B",
            distance_km=500.0,
            estimated_cost=250.50,
            actual_cost=275.75
        )

        assert transport.distance_km == 500.0
        assert transport.estimated_cost == 250.50
        assert transport.actual_cost == 275.75

    def test_transport_driver_info(self):
        """Test driver information fields."""
        transport = Transport(
            company_id=1,
            tracking_number="TRK009",
            origin="A",
            destination="B",
            estimated_cost=100.0,
            driver_name="John Doe",
            driver_contact="5551234567",
            vehicle_number="VH001"
        )

        assert transport.driver_name == "John Doe"
        assert transport.driver_contact == "5551234567"
        assert transport.vehicle_number == "VH001"

    def test_transport_location_fields(self):
        """Test location fields."""
        transport = Transport(
            company_id=1,
            tracking_number="TRK010",
            origin="New York, NY",
            destination="Los Angeles, CA",
            estimated_cost=500.0
        )

        assert transport.origin == "New York, NY"
        assert transport.destination == "Los Angeles, CA"
        assert len(transport.origin) > 0
        assert len(transport.destination) > 0
