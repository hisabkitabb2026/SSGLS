"""API endpoints for expense management."""

from datetime import datetime
from typing import Optional

from fastapi import APIRouter, Depends, Header, HTTPException, Query, status
from sqlalchemy.orm import Session

from app.middleware.jwt_middleware import AuthContext, JWTHandler, validate_company_context
from app.models import DatabaseManager
from app.schemas import (
    ApprovalUpdate,
    ErrorResponse,
    ExpenseCreate,
    ExpensePaginatedResponse,
    ExpenseResponse,
    ExpenseUpdate,
)
from app.services.expense_service import ExpenseService
from app.utils.logging import get_logger

logger = get_logger(__name__)

router = APIRouter(prefix="/api/v1", tags=["expenses"])


def get_db() -> Session:
    """Get database session."""
    return DatabaseManager.get_session()


def get_auth_context(
    authorization: str = Header(...),
) -> AuthContext:
    """Extract and validate JWT token from header."""
    try:
        token = JWTHandler().extract_token_from_header(authorization)
        payload = JWTHandler().verify_token(token)

        return AuthContext(
            user_id=payload.get("user_id"),
            company_id=payload.get("company_id"),
            permissions=payload.get("permissions", []),
        )
    except HTTPException:
        raise
    except Exception as e:
        logger.msg("auth_context_extraction_failed", error=str(e))
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid authentication",
            headers={"WWW-Authenticate": "Bearer"},
        )


@router.post(
    "/companies/{company_id}/expenses",
    response_model=ExpenseResponse,
    status_code=status.HTTP_201_CREATED,
)
async def create_expense(
    company_id: int,
    expense_data: ExpenseCreate,
    auth: AuthContext = Depends(get_auth_context),
    db: Session = Depends(get_db),
):
    """Create a new expense."""
    await validate_company_context(company_id, auth)

    try:
        service = ExpenseService(db)
        expense = service.create_expense(company_id, auth.user_id, expense_data)
        return expense
    except Exception as e:
        logger.msg("expense_creation_error", error=str(e))
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to create expense",
        )


@router.get(
    "/companies/{company_id}/expenses/{expense_id}",
    response_model=ExpenseResponse,
)
async def get_expense(
    company_id: int,
    expense_id: int,
    auth: AuthContext = Depends(get_auth_context),
    db: Session = Depends(get_db),
):
    """Get a specific expense."""
    await validate_company_context(company_id, auth)

    try:
        service = ExpenseService(db)
        expense = service.get_expense(company_id, expense_id)

        if not expense:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Expense not found",
            )

        return expense
    except HTTPException:
        raise
    except Exception as e:
        logger.msg("expense_retrieval_error", error=str(e))
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to retrieve expense",
        )


@router.get(
    "/companies/{company_id}/expenses",
    response_model=ExpensePaginatedResponse,
)
async def list_expenses(
    company_id: int,
    skip: int = Query(0, ge=0),
    limit: int = Query(50, ge=1, le=500),
    category: Optional[str] = None,
    status: Optional[str] = None,
    user_id: Optional[int] = None,
    auth: AuthContext = Depends(get_auth_context),
    db: Session = Depends(get_db),
):
    """List expenses with pagination and filtering."""
    await validate_company_context(company_id, auth)

    try:
        service = ExpenseService(db)
        expenses, total = service.list_expenses(
            company_id,
            skip=skip,
            limit=limit,
            category=category,
            status=status,
            user_id=user_id if "admin" in auth.permissions else auth.user_id,
        )

        return ExpensePaginatedResponse(
            items=expenses,
            total=total,
            skip=skip,
            limit=limit,
            has_more=(skip + limit) < total,
        )
    except Exception as e:
        logger.msg("expense_list_error", error=str(e))
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to list expenses",
        )


@router.patch(
    "/companies/{company_id}/expenses/{expense_id}",
    response_model=ExpenseResponse,
)
async def update_expense(
    company_id: int,
    expense_id: int,
    update_data: ExpenseUpdate,
    auth: AuthContext = Depends(get_auth_context),
    db: Session = Depends(get_db),
):
    """Update an expense."""
    await validate_company_context(company_id, auth)

    try:
        service = ExpenseService(db)
        expense = service.update_expense(company_id, expense_id, update_data)

        if not expense:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Expense not found",
            )

        return expense
    except HTTPException:
        raise
    except Exception as e:
        logger.msg("expense_update_error", error=str(e))
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to update expense",
        )


@router.delete(
    "/companies/{company_id}/expenses/{expense_id}",
    status_code=status.HTTP_204_NO_CONTENT,
)
async def delete_expense(
    company_id: int,
    expense_id: int,
    auth: AuthContext = Depends(get_auth_context),
    db: Session = Depends(get_db),
):
    """Delete an expense."""
    await validate_company_context(company_id, auth)

    try:
        service = ExpenseService(db)
        success = service.delete_expense(company_id, expense_id)

        if not success:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Expense not found",
            )

        return None
    except HTTPException:
        raise
    except Exception as e:
        logger.msg("expense_deletion_error", error=str(e))
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to delete expense",
        )


@router.post(
    "/companies/{company_id}/expenses/{expense_id}/approve",
    response_model=ExpenseResponse,
)
async def approve_expense(
    company_id: int,
    expense_id: int,
    auth: AuthContext = Depends(get_auth_context),
    db: Session = Depends(get_db),
):
    """Approve an expense."""
    await validate_company_context(company_id, auth)

    if "approve_expenses" not in auth.permissions:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Not authorized to approve expenses",
        )

    try:
        service = ExpenseService(db)
        success = service.approve_expense(company_id, expense_id, auth.user_id)

        if not success:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Expense not found",
            )

        expense = service.get_expense(company_id, expense_id)
        return expense
    except HTTPException:
        raise
    except Exception as e:
        logger.msg("expense_approval_error", error=str(e))
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to approve expense",
        )


@router.post(
    "/companies/{company_id}/expenses/{expense_id}/reject",
    response_model=ExpenseResponse,
)
async def reject_expense(
    company_id: int,
    expense_id: int,
    data: ApprovalUpdate,
    auth: AuthContext = Depends(get_auth_context),
    db: Session = Depends(get_db),
):
    """Reject an expense."""
    await validate_company_context(company_id, auth)

    if "approve_expenses" not in auth.permissions:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Not authorized to reject expenses",
        )

    try:
        service = ExpenseService(db)
        success = service.reject_expense(
            company_id,
            expense_id,
            auth.user_id,
            data.comment or "No reason provided",
        )

        if not success:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Expense not found",
            )

        expense = service.get_expense(company_id, expense_id)
        return expense
    except HTTPException:
        raise
    except Exception as e:
        logger.msg("expense_rejection_error", error=str(e))
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to reject expense",
        )


@router.get("/companies/{company_id}/expenses/statistics/summary")
async def get_expense_statistics(
    company_id: int,
    user_id: Optional[int] = None,
    auth: AuthContext = Depends(get_auth_context),
    db: Session = Depends(get_db),
):
    """Get expense statistics for a company."""
    await validate_company_context(company_id, auth)

    try:
        service = ExpenseService(db)

        # Non-admin users can only see their own statistics
        if "admin" not in auth.permissions:
            user_id = auth.user_id

        stats = service.get_expense_statistics(company_id, user_id)
        return stats
    except Exception as e:
        logger.msg("expense_statistics_error", error=str(e))
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Failed to retrieve statistics",
        )
