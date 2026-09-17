"""Pydantic schemas for request/response validation."""

from datetime import datetime
from decimal import Decimal
from typing import Any, Optional

from pydantic import BaseModel, Field


class ExpenseBase(BaseModel):
    """Base expense schema with common fields."""

    category: str = Field(..., min_length=1, max_length=50)
    amount: Decimal = Field(..., gt=0, decimal_places=2)
    currency_code: str = Field(default="USD", min_length=3, max_length=3)
    description: Optional[str] = Field(None, max_length=500)
    merchant_name: Optional[str] = Field(None, max_length=255)
    expense_date: datetime
    payment_method: Optional[str] = Field(None, max_length=50)
    receipt_url: Optional[str] = Field(None, max_length=500)
    metadata: Optional[dict[str, Any]] = Field(default_factory=dict)


class ExpenseCreate(ExpenseBase):
    """Schema for creating new expenses."""

    pass


class ExpenseUpdate(BaseModel):
    """Schema for updating expenses."""

    category: Optional[str] = Field(None, min_length=1, max_length=50)
    amount: Optional[Decimal] = Field(None, gt=0, decimal_places=2)
    currency_code: Optional[str] = Field(None, min_length=3, max_length=3)
    description: Optional[str] = Field(None, max_length=500)
    merchant_name: Optional[str] = Field(None, max_length=255)
    expense_date: Optional[datetime] = None
    payment_method: Optional[str] = Field(None, max_length=50)
    receipt_url: Optional[str] = Field(None, max_length=500)
    status: Optional[str] = Field(None, regex="^(pending|approved|rejected|paid)$")
    metadata: Optional[dict[str, Any]] = None


class ExpenseResponse(ExpenseBase):
    """Schema for expense response."""

    id: int
    company_id: int
    user_id: int
    status: str
    attachments: list[str] = Field(default_factory=list)
    created_at: datetime
    updated_at: datetime

    class Config:
        from_attributes = True


class ExpensePaginatedResponse(BaseModel):
    """Paginated expenses response."""

    items: list[ExpenseResponse]
    total: int
    skip: int
    limit: int
    has_more: bool


class ExpenseCategoryBase(BaseModel):
    """Base expense category schema."""

    name: str = Field(..., min_length=1, max_length=100)
    description: Optional[str] = Field(None, max_length=500)
    color_code: Optional[str] = Field(None, regex="^#[0-9a-fA-F]{6}$")


class ExpenseCategoryCreate(ExpenseCategoryBase):
    """Schema for creating expense categories."""

    pass


class ExpenseCategoryUpdate(BaseModel):
    """Schema for updating expense categories."""

    name: Optional[str] = Field(None, min_length=1, max_length=100)
    description: Optional[str] = Field(None, max_length=500)
    color_code: Optional[str] = Field(None, regex="^#[0-9a-fA-F]{6}$")
    is_active: Optional[str] = Field(None, regex="^[YN]$")


class ExpenseCategoryResponse(ExpenseCategoryBase):
    """Schema for expense category response."""

    id: int
    company_id: int
    is_active: str
    created_at: datetime
    updated_at: datetime

    class Config:
        from_attributes = True


class ApprovalBase(BaseModel):
    """Base approval schema."""

    approver_id: int
    approval_level: int = 1
    comment: Optional[str] = Field(None, max_length=500)


class ApprovalCreate(ApprovalBase):
    """Schema for creating approvals."""

    pass


class ApprovalUpdate(BaseModel):
    """Schema for updating approvals."""

    status: str = Field(..., regex="^(approved|rejected)$")
    comment: Optional[str] = Field(None, max_length=500)


class ApprovalResponse(BaseModel):
    """Schema for approval response."""

    id: int
    expense_id: int
    company_id: int
    approver_id: int
    approval_level: int
    status: str
    comment: Optional[str]
    decision_date: Optional[datetime]
    created_at: datetime
    updated_at: datetime

    class Config:
        from_attributes = True


class HealthResponse(BaseModel):
    """Health check response."""

    status: str
    service_name: str
    version: str
    timestamp: datetime
    database: str
    rabbitmq: str


class TokenResponse(BaseModel):
    """JWT token response."""

    access_token: str
    token_type: str = "bearer"
    expires_in: int


class ErrorResponse(BaseModel):
    """Error response schema."""

    error: str
    detail: Optional[str] = None
    status_code: int
    timestamp: datetime
