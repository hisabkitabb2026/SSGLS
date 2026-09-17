"""Pydantic schemas for request/response validation."""
from pydantic import BaseModel, Field, validator
from datetime import datetime
from typing import Optional


class TransportBase(BaseModel):
    """Base transport schema."""

    tracking_number: str = Field(..., min_length=1, max_length=255)
    origin: str = Field(..., min_length=1, max_length=255)
    destination: str = Field(..., min_length=1, max_length=255)
    status: Optional[str] = Field("pending", max_length=50)
    distance_km: Optional[float] = None
    estimated_cost: float = Field(..., ge=0)
    actual_cost: Optional[float] = Field(None, ge=0)
    carrier_name: Optional[str] = Field(None, max_length=255)
    vehicle_number: Optional[str] = Field(None, max_length=50)
    driver_name: Optional[str] = Field(None, max_length=255)
    driver_contact: Optional[str] = Field(None, max_length=20)
    scheduled_date: Optional[datetime] = None
    actual_departure: Optional[datetime] = None
    actual_arrival: Optional[datetime] = None
    is_active: Optional[bool] = True

    @validator("status")
    def validate_status(cls, v):
        """Validate status."""
        if v is None:
            return "pending"
        valid_statuses = ["pending", "in_transit", "delivered", "cancelled"]
        if v not in valid_statuses:
            raise ValueError(f"Invalid status. Must be one of {valid_statuses}")
        return v


class TransportCreate(TransportBase):
    """Transport creation schema."""
    pass


class TransportUpdate(BaseModel):
    """Transport update schema (all fields optional)."""

    tracking_number: Optional[str] = Field(None, min_length=1, max_length=255)
    origin: Optional[str] = Field(None, min_length=1, max_length=255)
    destination: Optional[str] = Field(None, min_length=1, max_length=255)
    status: Optional[str] = Field(None, max_length=50)
    distance_km: Optional[float] = None
    estimated_cost: Optional[float] = Field(None, ge=0)
    actual_cost: Optional[float] = Field(None, ge=0)
    carrier_name: Optional[str] = Field(None, max_length=255)
    vehicle_number: Optional[str] = Field(None, max_length=50)
    driver_name: Optional[str] = Field(None, max_length=255)
    driver_contact: Optional[str] = Field(None, max_length=20)
    scheduled_date: Optional[datetime] = None
    actual_departure: Optional[datetime] = None
    actual_arrival: Optional[datetime] = None
    is_active: Optional[bool] = None

    @validator("status")
    def validate_status(cls, v):
        """Validate status."""
        if v is None:
            return None
        valid_statuses = ["pending", "in_transit", "delivered", "cancelled"]
        if v not in valid_statuses:
            raise ValueError(f"Invalid status. Must be one of {valid_statuses}")
        return v


class TransportResponse(TransportBase):
    """Transport response schema."""

    id: int
    company_id: int
    created_at: datetime
    updated_at: datetime

    class Config:
        """Pydantic config."""
        from_attributes = True


class HealthResponse(BaseModel):
    """Health check response."""

    status: str
    version: str
    database: str
    rabbitmq: str


class ErrorResponse(BaseModel):
    """Error response schema."""

    error: str
    detail: Optional[str] = None
    status_code: int
