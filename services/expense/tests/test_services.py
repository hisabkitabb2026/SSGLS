"""Service layer tests."""

from datetime import datetime
from decimal import Decimal

import pytest

from app.models import Expense, ExpenseApproval
from app.schemas import ExpenseCreate, ExpenseUpdate
from app.services.expense_service import ExpenseService


class TestExpenseService:
    """Tests for ExpenseService."""

    def test_create_expense(self, test_db, sample_expense_data, mock_event_publisher):
        """Test expense creation through service."""
        service = ExpenseService(test_db)

        expense_data = ExpenseCreate(**sample_expense_data)
        result = service.create_expense(1, 1, expense_data)

        assert result.id == 1
        assert result.company_id == 1
        assert result.user_id == 1
        assert result.category == sample_expense_data["category"]
        assert result.status == "pending"

        # Verify event was published
        assert len(mock_event_publisher.events) == 1
        assert mock_event_publisher.events[0]["type"] == "expense.created"

    def test_create_expense_with_metadata(self, test_db, sample_expense_data, mock_event_publisher):
        """Test expense creation with metadata."""
        sample_expense_data["metadata"] = {"project_id": 123, "cost_center": "IT"}
        service = ExpenseService(test_db)

        expense_data = ExpenseCreate(**sample_expense_data)
        result = service.create_expense(1, 1, expense_data)

        assert result.metadata == {"project_id": 123, "cost_center": "IT"}

    def test_get_expense(self, test_db, sample_expense):
        """Test expense retrieval."""
        service = ExpenseService(test_db)

        result = service.get_expense(1, sample_expense.id)

        assert result is not None
        assert result.id == sample_expense.id
        assert result.category == sample_expense.category

    def test_get_expense_wrong_company(self, test_db, sample_expense):
        """Test retrieval with wrong company_id."""
        service = ExpenseService(test_db)

        result = service.get_expense(2, sample_expense.id)

        assert result is None

    def test_list_expenses(self, test_db, multiple_expenses):
        """Test listing expenses."""
        service = ExpenseService(test_db)

        expenses, total = service.list_expenses(1)

        assert total == 5
        assert len(expenses) == 5

    def test_list_expenses_with_pagination(self, test_db, multiple_expenses):
        """Test listing with pagination."""
        service = ExpenseService(test_db)

        expenses, total = service.list_expenses(1, skip=0, limit=2)

        assert total == 5
        assert len(expenses) == 2

    def test_list_expenses_with_category_filter(self, test_db, multiple_expenses):
        """Test listing with category filter."""
        service = ExpenseService(test_db)

        expenses, total = service.list_expenses(1, category="travel")

        assert total == 5
        assert all(e.category == "travel" for e in expenses)

    def test_list_expenses_with_status_filter(self, test_db, test_client, auth_header, sample_expense):
        """Test listing with status filter."""
        test_db.query(Expense).filter(Expense.id == sample_expense.id).update(
            {"status": "approved"}
        )
        test_db.commit()

        service = ExpenseService(test_db)
        expenses, total = service.list_expenses(1, status="approved")

        assert total == 1
        assert expenses[0].status == "approved"

    def test_update_expense(self, test_db, sample_expense, mock_event_publisher):
        """Test expense update."""
        service = ExpenseService(test_db)

        update_data = ExpenseUpdate(
            amount=Decimal("250.00"),
            description="Updated description",
        )
        result = service.update_expense(1, sample_expense.id, update_data)

        assert result is not None
        assert result.amount == Decimal("250.00")
        assert result.description == "Updated description"

        # Verify event was published
        assert len(mock_event_publisher.events) == 1
        assert mock_event_publisher.events[0]["type"] == "expense.updated"

    def test_update_nonexistent_expense(self, test_db):
        """Test updating non-existent expense."""
        service = ExpenseService(test_db)

        update_data = ExpenseUpdate(amount=Decimal("250.00"))
        result = service.update_expense(1, 999, update_data)

        assert result is None

    def test_delete_expense(self, test_db, sample_expense, mock_event_publisher):
        """Test expense deletion."""
        service = ExpenseService(test_db)

        result = service.delete_expense(1, sample_expense.id)

        assert result is True

        # Verify deletion
        deleted = test_db.query(Expense).filter(Expense.id == sample_expense.id).first()
        assert deleted is None

        # Verify event was published
        assert len(mock_event_publisher.events) == 1
        assert mock_event_publisher.events[0]["type"] == "expense.deleted"

    def test_delete_nonexistent_expense(self, test_db):
        """Test deleting non-existent expense."""
        service = ExpenseService(test_db)

        result = service.delete_expense(1, 999)

        assert result is False

    def test_approve_expense(self, test_db, sample_expense, mock_event_publisher):
        """Test expense approval."""
        service = ExpenseService(test_db)

        result = service.approve_expense(1, sample_expense.id, 2)

        assert result is True

        # Verify status changed
        updated = test_db.query(Expense).filter(Expense.id == sample_expense.id).first()
        assert updated.status == "approved"

        # Verify approval record created
        approval = test_db.query(ExpenseApproval).filter(
            ExpenseApproval.expense_id == sample_expense.id
        ).first()
        assert approval is not None
        assert approval.approver_id == 2
        assert approval.status == "approved"

        # Verify event was published
        assert len(mock_event_publisher.events) == 1
        assert mock_event_publisher.events[0]["type"] == "expense.approved"

    def test_reject_expense(self, test_db, sample_expense, mock_event_publisher):
        """Test expense rejection."""
        service = ExpenseService(test_db)

        result = service.reject_expense(1, sample_expense.id, 2, "Invalid receipt")

        assert result is True

        # Verify status changed
        updated = test_db.query(Expense).filter(Expense.id == sample_expense.id).first()
        assert updated.status == "rejected"

        # Verify approval record created
        approval = test_db.query(ExpenseApproval).filter(
            ExpenseApproval.expense_id == sample_expense.id
        ).first()
        assert approval is not None
        assert approval.approver_id == 2
        assert approval.status == "rejected"
        assert approval.comment == "Invalid receipt"

        # Verify event was published
        assert len(mock_event_publisher.events) == 1
        assert mock_event_publisher.events[0]["type"] == "expense.rejected"

    def test_get_expense_statistics(self, test_db, test_client, auth_header, multiple_expenses):
        """Test statistics retrieval."""
        # Update some expenses with different statuses
        expenses = test_db.query(Expense).all()
        expenses[0].status = "approved"
        expenses[1].status = "rejected"
        test_db.commit()

        service = ExpenseService(test_db)
        stats = service.get_expense_statistics(1)

        assert stats["total_expenses"] == 5
        assert stats["status_breakdown"]["pending"] == 3
        assert stats["status_breakdown"]["approved"] == 1
        assert stats["status_breakdown"]["rejected"] == 1
        assert "total_amount" in stats
        assert "category_breakdown" in stats

    def test_get_expense_statistics_by_user(self, test_db, test_client, auth_header, sample_expense):
        """Test statistics retrieval by user."""
        service = ExpenseService(test_db)
        stats = service.get_expense_statistics(1, user_id=1)

        assert stats["total_expenses"] == 1
        assert "status_breakdown" in stats


class TestEventPublisher:
    """Tests for event publishing."""

    def test_publish_expense_created(self, mock_event_publisher):
        """Test expense.created event publishing."""
        result = mock_event_publisher.publish_expense_created(1, 1, 1)

        assert result is True
        assert len(mock_event_publisher.events) == 1
        event = mock_event_publisher.events[0]
        assert event["type"] == "expense.created"
        assert event["data"]["expense_id"] == 1

    def test_publish_expense_updated(self, mock_event_publisher):
        """Test expense.updated event publishing."""
        changes = {"amount": {"old": "100", "new": "200"}}
        result = mock_event_publisher.publish_expense_updated(1, 1, changes)

        assert result is True
        assert len(mock_event_publisher.events) == 1
        event = mock_event_publisher.events[0]
        assert event["type"] == "expense.updated"
        assert event["data"]["changes"] == changes

    def test_publish_expense_deleted(self, mock_event_publisher):
        """Test expense.deleted event publishing."""
        result = mock_event_publisher.publish_expense_deleted(1, 1)

        assert result is True
        assert len(mock_event_publisher.events) == 1

    def test_publish_expense_approved(self, mock_event_publisher):
        """Test expense.approved event publishing."""
        result = mock_event_publisher.publish_expense_approved(1, 1, 2)

        assert result is True
        assert len(mock_event_publisher.events) == 1
        event = mock_event_publisher.events[0]
        assert event["type"] == "expense.approved"
        assert event["data"]["approver_id"] == 2

    def test_publish_expense_rejected(self, mock_event_publisher):
        """Test expense.rejected event publishing."""
        result = mock_event_publisher.publish_expense_rejected(1, 1, 2, "Invalid")

        assert result is True
        assert len(mock_event_publisher.events) == 1
        event = mock_event_publisher.events[0]
        assert event["type"] == "expense.rejected"
