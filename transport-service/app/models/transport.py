"""Transport model with multi-tenancy support."""
from datetime import datetime
from sqlalchemy import Column, Integer, String, Float, DateTime, Boolean, Index
from sqlalchemy.orm import validates
from app.models import Base


class Transport(Base):
    """Transport model for managing transport/shipment records."""

    __tablename__ = "transports"

    # Primary key
    id = Column(Integer, primary_key=True, index=True)

    # Multi-tenancy: company_id for data isolation
    company_id = Column(Integer, nullable=False, index=True)

    # Core fields
    tracking_number = Column(String(255), unique=True, index=True, nullable=False)
    origin = Column(String(255), nullable=False)
    destination = Column(String(255), nullable=False)
    status = Column(String(50), default="pending", index=True)  # pending, in_transit, delivered, cancelled

    # Details
    distance_km = Column(Float, nullable=True)
    estimated_cost = Column(Float, nullable=False, default=0.0)
    actual_cost = Column(Float, nullable=True)

    # Carrier info
    carrier_name = Column(String(255), nullable=True)
    vehicle_number = Column(String(50), nullable=True)
    driver_name = Column(String(255), nullable=True)
    driver_contact = Column(String(20), nullable=True)

    # Timestamps and audit
    scheduled_date = Column(DateTime, nullable=True)
    actual_departure = Column(DateTime, nullable=True)
    actual_arrival = Column(DateTime, nullable=True)
    created_at = Column(DateTime, default=datetime.utcnow, nullable=False)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow, nullable=False)
    is_active = Column(Boolean, default=True, index=True)

    # Indexes for multi-tenancy isolation
    __table_args__ = (
        Index("idx_company_tracking", "company_id", "tracking_number"),
        Index("idx_company_status", "company_id", "status"),
        Index("idx_company_active", "company_id", "is_active"),
        Index("idx_company_date", "company_id", "created_at"),
    )

    @validates("status")
    def validate_status(self, key, value):
        """Validate status field."""
        valid_statuses = ["pending", "in_transit", "delivered", "cancelled"]
        if value not in valid_statuses:
            raise ValueError(f"Invalid status. Must be one of {valid_statuses}")
        return value

    def __repr__(self):
        """String representation."""
        return (
            f"<Transport(id={self.id}, company_id={self.company_id}, "
            f"tracking_number={self.tracking_number}, status={self.status})>"
        )

    def to_dict(self):
        """Convert to dictionary."""
        return {
            "id": self.id,
            "company_id": self.company_id,
            "tracking_number": self.tracking_number,
            "origin": self.origin,
            "destination": self.destination,
            "status": self.status,
            "distance_km": self.distance_km,
            "estimated_cost": self.estimated_cost,
            "actual_cost": self.actual_cost,
            "carrier_name": self.carrier_name,
            "vehicle_number": self.vehicle_number,
            "driver_name": self.driver_name,
            "driver_contact": self.driver_contact,
            "scheduled_date": self.scheduled_date,
            "actual_departure": self.actual_departure,
            "actual_arrival": self.actual_arrival,
            "created_at": self.created_at,
            "updated_at": self.updated_at,
            "is_active": self.is_active,
        }
