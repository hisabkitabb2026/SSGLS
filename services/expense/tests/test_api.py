"""API endpoint tests."""

from datetime import datetime
from decimal import Decimal

import pytest
from fastapi import status


class TestExpenseCreation:
    """Tests for expense creation endpoint."""

    def test_create_expense_success(self, test_client, auth_header, sample_expense_data):
        """Test successful expense creation."""
        response = test_client.post(
            "/api/v1/companies/1/expenses",
            json={
                "category": sample_expense_data["category"],
                "amount": str(sample_expense_data["amount"]),
                "currency_code": sample_expense_data["currency_code"],
                "description": sample_expense_data["description"],
                "merchant_name": sample_expense_data["merchant_name"],
                "expense_date": sample_expense_data["expense_date"].isoformat(),
                "payment_method": sample_expense_data["payment_method"],
                "receipt_url": sample_expense_data["receipt_url"],
            },
            headers=auth_header,
        )

        assert response.status_code == status.HTTP_201_CREATED
        data = response.json()
        assert data["id"] == 1
        assert data["company_id"] == 1
        assert data["user_id"] == 1
        assert data["category"] == sample_expense_data["category"]
        assert data["status"] == "pending"

    def test_create_expense_missing_required_field(self, test_client, auth_header):
        """Test expense creation with missing required field."""
        response = test_client.post(
            "/api/v1/companies/1/expenses",
            json={
                "amount": "100.00",
                "currency_code": "USD",
                # Missing category
            },
            headers=auth_header,
        )

        assert response.status_code == status.HTTP_422_UNPROCESSABLE_ENTITY

    def test_create_expense_invalid_amount(self, test_client, auth_header):
        """Test expense creation with invalid amount."""
        response = test_client.post(
            "/api/v1/companies/1/expenses",
            json={
                "category": "travel",
                "amount": "-100.00",  # Negative amount
                "expense_date": datetime.utcnow().isoformat(),
            },
            headers=auth_header,
        )

        assert response.status_code == status.HTTP_422_UNPROCESSABLE_ENTITY

    def test_create_expense_without_auth(self, test_client, sample_expense_data):
        """Test expense creation without authentication."""
        response = test_client.post(
            "/api/v1/companies/1/expenses",
            json={
                "category": sample_expense_data["category"],
                "amount": str(sample_expense_data["amount"]),
                "expense_date": sample_expense_data["expense_date"].isoformat(),
            },
        )

        assert response.status_code == status.HTTP_403_UNPROCESSABLE_ENTITY


class TestExpenseRetrieval:
    """Tests for expense retrieval endpoints."""

    def test_get_expense_success(self, test_client, auth_header, sample_expense):
        """Test successful expense retrieval."""
        response = test_client.get(
            f"/api/v1/companies/1/expenses/{sample_expense.id}",
            headers=auth_header,
        )

        assert response.status_code == status.HTTP_200_OK
        data = response.json()
        assert data["id"] == sample_expense.id
        assert data["company_id"] == 1
        assert data["category"] == sample_expense.category

    def test_get_expense_not_found(self, test_client, auth_header):
        """Test retrieval of non-existent expense."""
        response = test_client.get(
            "/api/v1/companies/1/expenses/999",
            headers=auth_header,
        )

        assert response.status_code == status.HTTP_404_NOT_FOUND

    def test_get_expense_wrong_company(self, test_client, sample_expense, jwt_handler):
        """Test retrieval with wrong company_id."""
        token = jwt_handler.create_token({
            "user_id": 1,
            "company_id": 2,  # Different company
            "permissions": ["admin"],
        })
        headers = {"Authorization": f"Bearer {token}"}

        response = test_client.get(
            f"/api/v1/companies/1/expenses/{sample_expense.id}",
            headers=headers,
        )

        assert response.status_code == status.HTTP_403_FORBIDDEN

    def test_list_expenses_success(self, test_client, auth_header, multiple_expenses):
        """Test successful expenses listing."""
        response = test_client.get(
            "/api/v1/companies/1/expenses",
            headers=auth_header,
        )

        assert response.status_code == status.HTTP_200_OK
        data = response.json()
        assert data["total"] == 5
        assert len(data["items"]) == 5
        assert data["skip"] == 0
        assert data["limit"] == 50
        assert data["has_more"] is False

    def test_list_expenses_with_pagination(self, test_client, auth_header, multiple_expenses):
        """Test expenses listing with pagination."""
        response = test_client.get(
            "/api/v1/companies/1/expenses?skip=0&limit=2",
            headers=auth_header,
        )

        assert response.status_code == status.HTTP_200_OK
        data = response.json()
        assert data["total"] == 5
        assert len(data["items"]) == 2
        assert data["has_more"] is True

    def test_list_expenses_with_filters(self, test_client, auth_header, multiple_expenses):
        """Test expenses listing with filters."""
        response = test_client.get(
            "/api/v1/companies/1/expenses?category=travel&status=pending",
            headers=auth_header,
        )

        assert response.status_code == status.HTTP_200_OK
        data = response.json()
        assert all(item["category"] == "travel" for item in data["items"])
        assert all(item["status"] == "pending" for item in data["items"])


class TestExpenseUpdate:
    """Tests for expense update endpoint."""

    def test_update_expense_success(self, test_client, auth_header, sample_expense):
        """Test successful expense update."""
        response = test_client.patch(
            f"/api/v1/companies/1/expenses/{sample_expense.id}",
            json={
                "amount": "200.00",
                "description": "Updated description",
            },
            headers=auth_header,
        )

        assert response.status_code == status.HTTP_200_OK
        data = response.json()
        assert Decimal(data["amount"]) == Decimal("200.00")
        assert data["description"] == "Updated description"

    def test_update_expense_not_found(self, test_client, auth_header):
        """Test update of non-existent expense."""
        response = test_client.patch(
            "/api/v1/companies/1/expenses/999",
            json={"amount": "200.00"},
            headers=auth_header,
        )

        assert response.status_code == status.HTTP_404_NOT_FOUND

    def test_update_expense_invalid_data(self, test_client, auth_header, sample_expense):
        """Test update with invalid data."""
        response = test_client.patch(
            f"/api/v1/companies/1/expenses/{sample_expense.id}",
            json={
                "amount": "-100.00",  # Invalid negative amount
            },
            headers=auth_header,
        )

        assert response.status_code == status.HTTP_422_UNPROCESSABLE_ENTITY


class TestExpenseDelete:
    """Tests for expense deletion endpoint."""

    def test_delete_expense_success(self, test_client, auth_header, sample_expense):
        """Test successful expense deletion."""
        response = test_client.delete(
            f"/api/v1/companies/1/expenses/{sample_expense.id}",
            headers=auth_header,
        )

        assert response.status_code == status.HTTP_204_NO_CONTENT

        # Verify deletion
        response = test_client.get(
            f"/api/v1/companies/1/expenses/{sample_expense.id}",
            headers=auth_header,
        )
        assert response.status_code == status.HTTP_404_NOT_FOUND

    def test_delete_expense_not_found(self, test_client, auth_header):
        """Test deletion of non-existent expense."""
        response = test_client.delete(
            "/api/v1/companies/1/expenses/999",
            headers=auth_header,
        )

        assert response.status_code == status.HTTP_404_NOT_FOUND


class TestExpenseApproval:
    """Tests for expense approval endpoints."""

    def test_approve_expense_success(self, test_client, auth_header, sample_expense):
        """Test successful expense approval."""
        response = test_client.post(
            f"/api/v1/companies/1/expenses/{sample_expense.id}/approve",
            headers=auth_header,
        )

        assert response.status_code == status.HTTP_200_OK
        data = response.json()
        assert data["status"] == "approved"

    def test_approve_expense_without_permission(self, test_client, jwt_handler, sample_expense):
        """Test approval without required permission."""
        token = jwt_handler.create_token({
            "user_id": 1,
            "company_id": 1,
            "permissions": [],  # No approve_expenses permission
        })
        headers = {"Authorization": f"Bearer {token}"}

        response = test_client.post(
            f"/api/v1/companies/1/expenses/{sample_expense.id}/approve",
            headers=headers,
        )

        assert response.status_code == status.HTTP_403_FORBIDDEN

    def test_reject_expense_success(self, test_client, auth_header, sample_expense):
        """Test successful expense rejection."""
        response = test_client.post(
            f"/api/v1/companies/1/expenses/{sample_expense.id}/reject",
            json={"status": "rejected", "comment": "Invalid receipt"},
            headers=auth_header,
        )

        assert response.status_code == status.HTTP_200_OK
        data = response.json()
        assert data["status"] == "rejected"


class TestHealthCheck:
    """Tests for health check endpoint."""

    def test_health_check_success(self, test_client):
        """Test health check endpoint."""
        response = test_client.get("/health")

        assert response.status_code == status.HTTP_200_OK
        data = response.json()
        assert data["status"] in ["healthy", "degraded"]
        assert data["service_name"] == "expense-service"
        assert "version" in data
        assert "database" in data
        assert "rabbitmq" in data


class TestMetricsEndpoint:
    """Tests for metrics endpoint."""

    def test_metrics_endpoint_success(self, test_client):
        """Test metrics endpoint."""
        response = test_client.get("/metrics")

        assert response.status_code == status.HTTP_200_OK
        assert b"service_info" in response.content
        assert b"service_requests_total" in response.content


class TestTokenGeneration:
    """Tests for token generation endpoint."""

    def test_generate_token_success(self, test_client):
        """Test token generation."""
        response = test_client.post(
            "/auth/token",
            params={
                "user_id": 1,
                "company_id": 1,
                "permissions": ["admin"],
            },
        )

        assert response.status_code == status.HTTP_200_OK
        data = response.json()
        assert "access_token" in data
        assert data["token_type"] == "bearer"
        assert "expires_in" in data
