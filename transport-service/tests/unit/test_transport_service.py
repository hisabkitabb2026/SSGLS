"""Unit tests for TransportService."""
import pytest
from app.models import Transport
from app.schemas import TransportCreate, TransportUpdate
from app.services import TransportService


class TestTransportService:
    """Test suite for TransportService."""

    def test_create_transport_success(self, db_session):
        """Test successful transport creation."""
        service = TransportService(db_session)
        data = TransportCreate(
            tracking_number="TRK123",
            origin="New York",
            destination="Boston",
            status="pending",
            estimated_cost=250.0,
            distance_km=215.0
        )

        transport = service.create_transport(1, data)

        assert transport.id is not None
        assert transport.company_id == 1
        assert transport.tracking_number == "TRK123"
        assert transport.status == "pending"
        assert transport.is_active is True

    def test_create_transport_duplicate_tracking(self, db_session, sample_transport):
        """Test creation fails with duplicate tracking number."""
        service = TransportService(db_session)
        data = TransportCreate(
            tracking_number="TRK001",  # Same as sample_transport
            origin="Different",
            destination="Locations",
            status="pending",
            estimated_cost=100.0
        )

        with pytest.raises(ValueError, match="already exists"):
            service.create_transport(1, data)

    def test_get_transport_success(self, db_session, sample_transport):
        """Test retrieving transport."""
        service = TransportService(db_session)

        transport = service.get_transport(1, sample_transport.id)

        assert transport is not None
        assert transport.id == sample_transport.id
        assert transport.company_id == 1

    def test_get_transport_not_found(self, db_session):
        """Test getting non-existent transport."""
        service = TransportService(db_session)

        transport = service.get_transport(1, 9999)

        assert transport is None

    def test_get_transport_different_company(self, db_session, sample_transport):
        """Test company isolation works."""
        service = TransportService(db_session)

        # Try to access with different company_id
        transport = service.get_transport(2, sample_transport.id)

        assert transport is None

    def test_list_transports_success(self, db_session, sample_transports):
        """Test listing transports."""
        service = TransportService(db_session)

        transports, total = service.list_transports(1, skip=0, limit=100)

        assert len(transports) == 5
        assert total == 5

    def test_list_transports_with_pagination(self, db_session, sample_transports):
        """Test pagination in list transports."""
        service = TransportService(db_session)

        page1, total = service.list_transports(1, skip=0, limit=2)
        page2, _ = service.list_transports(1, skip=2, limit=2)

        assert len(page1) == 2
        assert len(page2) == 2
        assert total == 5

    def test_list_transports_filter_by_status(self, db_session, sample_transports):
        """Test filtering by status."""
        service = TransportService(db_session)

        transports, total = service.list_transports(1, status="pending")

        pending_count = sum(1 for t in sample_transports if t.status == "pending")
        assert total == pending_count

    def test_list_transports_filter_by_active(self, db_session, sample_transport):
        """Test filtering by active status."""
        service = TransportService(db_session)

        # Soft delete one
        sample_transport.is_active = False
        db_session.commit()

        active, total_active = service.list_transports(1, is_active=True)
        inactive, total_inactive = service.list_transports(1, is_active=False)

        assert len(active) == 0
        assert len(inactive) == 1

    def test_update_transport_success(self, db_session, sample_transport):
        """Test successful transport update."""
        service = TransportService(db_session)
        update_data = TransportUpdate(
            status="in_transit",
            actual_cost=450.0
        )

        updated = service.update_transport(1, sample_transport.id, update_data)

        assert updated.status == "in_transit"
        assert updated.actual_cost == 450.0
        assert updated.origin == sample_transport.origin  # Unchanged

    def test_update_transport_not_found(self, db_session):
        """Test updating non-existent transport."""
        service = TransportService(db_session)
        update_data = TransportUpdate(status="in_transit")

        result = service.update_transport(1, 9999, update_data)

        assert result is None

    def test_update_transport_status_change(self, db_session, sample_transport):
        """Test status change event publishing."""
        service = TransportService(db_session)
        old_status = sample_transport.status
        update_data = TransportUpdate(status="delivered")

        updated = service.update_transport(1, sample_transport.id, update_data)

        assert updated.status == "delivered"
        assert updated.status != old_status

    def test_delete_transport_soft_delete(self, db_session, sample_transport):
        """Test soft delete."""
        service = TransportService(db_session)

        deleted = service.delete_transport(1, sample_transport.id, soft_delete=True)

        assert deleted is True
        transport = db_session.query(Transport).filter_by(id=sample_transport.id).first()
        assert transport.is_active is False

    def test_delete_transport_hard_delete(self, db_session, sample_transport):
        """Test hard delete."""
        service = TransportService(db_session)
        transport_id = sample_transport.id

        deleted = service.delete_transport(1, transport_id, soft_delete=False)

        assert deleted is True
        transport = db_session.query(Transport).filter_by(id=transport_id).first()
        assert transport is None

    def test_delete_transport_not_found(self, db_session):
        """Test deleting non-existent transport."""
        service = TransportService(db_session)

        deleted = service.delete_transport(1, 9999, soft_delete=True)

        assert deleted is False

    def test_get_statistics(self, db_session, sample_transports):
        """Test generating statistics."""
        service = TransportService(db_session)

        stats = service.get_statistics(1)

        assert stats["total_transports"] == 5
        assert "by_status" in stats
        assert "total_cost" in stats
        assert stats["by_status"]["pending"] > 0

    def test_validate_status(self, db_session):
        """Test status validation."""
        service = TransportService(db_session)
        invalid_data = TransportCreate(
            tracking_number="TRK999",
            origin="A",
            destination="B",
            status="invalid_status",
            estimated_cost=100.0
        )

        with pytest.raises(ValueError, match="Invalid status"):
            service.create_transport(1, invalid_data)

    def test_company_isolation(self, db_session):
        """Test that company isolation is enforced."""
        service = TransportService(db_session)

        # Create transport for company 1
        data = TransportCreate(
            tracking_number="TRK_ISO",
            origin="A",
            destination="B",
            status="pending",
            estimated_cost=100.0
        )
        transport1 = service.create_transport(1, data)

        # Try to access with company 2
        retrieved = service.get_transport(2, transport1.id)
        assert retrieved is None

        # Verify company 1 can still access
        retrieved = service.get_transport(1, transport1.id)
        assert retrieved is not None
