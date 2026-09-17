"""Business logic for expense management."""

from datetime import datetime
from decimal import Decimal
from typing import Any, Optional

from sqlalchemy import and_, desc
from sqlalchemy.orm import Session

from app.models import Expense, ExpenseApproval, ExpenseCategory
from app.schemas import (
    ApprovalUpdate,
    ExpenseCreate,
    ExpenseResponse,
    ExpenseUpdate,
)
from app.services.event_publisher import get_event_publisher
from app.utils.logging import get_logger

logger = get_logger(__name__)


class ExpenseService:
    """Service for managing expenses with company isolation."""

    def __init__(self, session: Session):
        self.session = session
        self.publisher = get_event_publisher()

    def create_expense(
        self,
        company_id: int,
        user_id: int,
        expense_data: ExpenseCreate,
    ) -> ExpenseResponse:
        """Create a new expense."""
        try:
            expense = Expense(
                company_id=company_id,
                user_id=user_id,
                category=expense_data.category,
                amount=expense_data.amount,
                currency_code=expense_data.currency_code,
                description=expense_data.description,
                merchant_name=expense_data.merchant_name,
                expense_date=expense_data.expense_date,
                payment_method=expense_data.payment_method,
                receipt_url=expense_data.receipt_url,
                metadata=expense_data.metadata or {},
                status="pending",
            )

            self.session.add(expense)
            self.session.flush()  # Get the ID without committing
            expense_id = expense.id

            self.session.commit()

            logger.msg(
                "expense_created",
                expense_id=expense_id,
                company_id=company_id,
                user_id=user_id,
                amount=str(expense_data.amount),
            )

            # Publish event
            self.publisher.publish_expense_created(expense_id, company_id, user_id)

            return ExpenseResponse.from_orm(expense)

        except Exception as e:
            self.session.rollback()
            logger.msg(
                "expense_creation_failed",
                company_id=company_id,
                error=str(e),
            )
            raise

    def get_expense(
        self,
        company_id: int,
        expense_id: int,
    ) -> Optional[ExpenseResponse]:
        """Get a single expense by ID."""
        expense = self.session.query(Expense).filter(
            and_(
                Expense.id == expense_id,
                Expense.company_id == company_id,
            )
        ).first()

        return ExpenseResponse.from_orm(expense) if expense else None

    def list_expenses(
        self,
        company_id: int,
        skip: int = 0,
        limit: int = 50,
        category: Optional[str] = None,
        status: Optional[str] = None,
        user_id: Optional[int] = None,
    ) -> tuple[list[ExpenseResponse], int]:
        """List expenses with optional filters."""
        query = self.session.query(Expense).filter(Expense.company_id == company_id)

        if category:
            query = query.filter(Expense.category == category)

        if status:
            query = query.filter(Expense.status == status)

        if user_id:
            query = query.filter(Expense.user_id == user_id)

        total = query.count()

        expenses = (
            query.order_by(desc(Expense.created_at))
            .offset(skip)
            .limit(limit)
            .all()
        )

        return [ExpenseResponse.from_orm(e) for e in expenses], total

    def update_expense(
        self,
        company_id: int,
        expense_id: int,
        update_data: ExpenseUpdate,
    ) -> Optional[ExpenseResponse]:
        """Update an expense."""
        try:
            expense = self.session.query(Expense).filter(
                and_(
                    Expense.id == expense_id,
                    Expense.company_id == company_id,
                )
            ).first()

            if not expense:
                return None

            changes = {}
            update_fields = update_data.model_dump(exclude_unset=True)

            for field, value in update_fields.items():
                if hasattr(expense, field) and value is not None:
                    old_value = getattr(expense, field)
                    setattr(expense, field, value)
                    if old_value != value:
                        changes[field] = {"old": str(old_value), "new": str(value)}

            if changes:
                expense.updated_at = datetime.utcnow()
                self.session.commit()

                logger.msg(
                    "expense_updated",
                    expense_id=expense_id,
                    company_id=company_id,
                    changes=changes,
                )

                # Publish event
                self.publisher.publish_expense_updated(expense_id, company_id, changes)

            return ExpenseResponse.from_orm(expense)

        except Exception as e:
            self.session.rollback()
            logger.msg(
                "expense_update_failed",
                expense_id=expense_id,
                company_id=company_id,
                error=str(e),
            )
            raise

    def delete_expense(self, company_id: int, expense_id: int) -> bool:
        """Delete an expense."""
        try:
            expense = self.session.query(Expense).filter(
                and_(
                    Expense.id == expense_id,
                    Expense.company_id == company_id,
                )
            ).first()

            if not expense:
                return False

            self.session.delete(expense)
            self.session.commit()

            logger.msg(
                "expense_deleted",
                expense_id=expense_id,
                company_id=company_id,
            )

            # Publish event
            self.publisher.publish_expense_deleted(expense_id, company_id)

            return True

        except Exception as e:
            self.session.rollback()
            logger.msg(
                "expense_deletion_failed",
                expense_id=expense_id,
                company_id=company_id,
                error=str(e),
            )
            raise

    def approve_expense(
        self,
        company_id: int,
        expense_id: int,
        approver_id: int,
    ) -> bool:
        """Approve an expense."""
        try:
            expense = self.session.query(Expense).filter(
                and_(
                    Expense.id == expense_id,
                    Expense.company_id == company_id,
                )
            ).first()

            if not expense:
                return False

            expense.status = "approved"
            expense.updated_at = datetime.utcnow()

            # Create approval record
            approval = ExpenseApproval(
                expense_id=expense_id,
                company_id=company_id,
                approver_id=approver_id,
                status="approved",
                decision_date=datetime.utcnow(),
            )

            self.session.add(approval)
            self.session.commit()

            logger.msg(
                "expense_approved",
                expense_id=expense_id,
                company_id=company_id,
                approver_id=approver_id,
            )

            # Publish event
            self.publisher.publish_expense_approved(expense_id, company_id, approver_id)

            return True

        except Exception as e:
            self.session.rollback()
            logger.msg(
                "expense_approval_failed",
                expense_id=expense_id,
                company_id=company_id,
                error=str(e),
            )
            raise

    def reject_expense(
        self,
        company_id: int,
        expense_id: int,
        approver_id: int,
        reason: str,
    ) -> bool:
        """Reject an expense."""
        try:
            expense = self.session.query(Expense).filter(
                and_(
                    Expense.id == expense_id,
                    Expense.company_id == company_id,
                )
            ).first()

            if not expense:
                return False

            expense.status = "rejected"
            expense.updated_at = datetime.utcnow()

            # Create approval record
            approval = ExpenseApproval(
                expense_id=expense_id,
                company_id=company_id,
                approver_id=approver_id,
                status="rejected",
                comment=reason,
                decision_date=datetime.utcnow(),
            )

            self.session.add(approval)
            self.session.commit()

            logger.msg(
                "expense_rejected",
                expense_id=expense_id,
                company_id=company_id,
                approver_id=approver_id,
                reason=reason,
            )

            # Publish event
            self.publisher.publish_expense_rejected(
                expense_id,
                company_id,
                approver_id,
                reason,
            )

            return True

        except Exception as e:
            self.session.rollback()
            logger.msg(
                "expense_rejection_failed",
                expense_id=expense_id,
                company_id=company_id,
                error=str(e),
            )
            raise

    def get_expense_statistics(
        self,
        company_id: int,
        user_id: Optional[int] = None,
    ) -> dict[str, Any]:
        """Get expense statistics for a company or user."""
        query = self.session.query(Expense).filter(Expense.company_id == company_id)

        if user_id:
            query = query.filter(Expense.user_id == user_id)

        total_expenses = query.count()
        total_amount = sum(e.amount for e in query.all()) or Decimal("0")

        status_breakdown = {}
        for status in ["pending", "approved", "rejected", "paid"]:
            count = query.filter(Expense.status == status).count()
            status_breakdown[status] = count

        category_breakdown = {}
        for expense in query.all():
            if expense.category not in category_breakdown:
                category_breakdown[expense.category] = {
                    "count": 0,
                    "total": Decimal("0"),
                }
            category_breakdown[expense.category]["count"] += 1
            category_breakdown[expense.category]["total"] += expense.amount

        return {
            "total_expenses": total_expenses,
            "total_amount": float(total_amount),
            "status_breakdown": status_breakdown,
            "category_breakdown": {
                k: {
                    "count": v["count"],
                    "total": float(v["total"]),
                }
                for k, v in category_breakdown.items()
            },
        }
