"""
End-to-End User Journey Tests

Tests complete user workflows:
- Create company → customer → product → invoice
- Create and process expenses
- Create and track transport documents
- Process payments
- Generate reports
"""

import pytest
import time
from datetime import datetime, timedelta


class TestCompanyCreationJourney:
    """Test company setup and initialization."""

    def test_create_company_complete_workflow(
        self,
        settings_client,
        sample_company_data,
        auth_headers
    ):
        """Test complete company creation and setup."""
        # Create company
        response = settings_client.post(
            "/api/v1/companies",
            json=sample_company_data,
            headers=auth_headers
        )

        assert response.status_code in [200, 201], f"Failed to create company: {response.text}"
        company = response.json()
        company_id = company["id"]

        # Verify company was created
        response = settings_client.get(
            f"/api/v1/companies/{company_id}",
            headers=auth_headers
        )
        assert response.status_code == 200
        retrieved_company = response.json()

        # Verify all fields
        assert retrieved_company["name"] == sample_company_data["name"]
        assert retrieved_company["email"] == sample_company_data["email"]
        assert retrieved_company["currency_code"] == sample_company_data["currency_code"]

    def test_company_settings_configuration(self, settings_client, auth_headers):
        """Test company settings can be configured."""
        company_id = 1

        settings_data = {
            "logo_url": "https://example.com/logo.png",
            "invoice_prefix": "INV",
            "invoice_number_format": "{year}{month}{seq:04d}",
            "tax_treatment": "inclusive",
            "language": "en",
            "timezone": "UTC"
        }

        response = settings_client.post(
            f"/api/v1/companies/{company_id}/settings",
            json=settings_data,
            headers=auth_headers
        )

        assert response.status_code in [200, 201]

        # Retrieve settings
        response = settings_client.get(
            f"/api/v1/companies/{company_id}/settings",
            headers=auth_headers
        )

        assert response.status_code == 200
        settings = response.json()
        assert settings["invoice_prefix"] == "INV"


class TestCustomerCreationJourney:
    """Test customer creation and management."""

    def test_create_customer_workflow(
        self,
        customer_client,
        sample_customer_data,
        auth_headers
    ):
        """Test creating a new customer."""
        response = customer_client.post(
            "/api/v1/customers",
            json=sample_customer_data,
            headers=auth_headers
        )

        assert response.status_code in [200, 201]
        customer = response.json()
        customer_id = customer["id"]

        # Verify customer exists
        response = customer_client.get(
            f"/api/v1/customers/{customer_id}",
            headers=auth_headers
        )

        assert response.status_code == 200
        retrieved = response.json()
        assert retrieved["name"] == sample_customer_data["name"]
        assert retrieved["email"] == sample_customer_data["email"]

    def test_bulk_create_customers(self, customer_client, auth_headers):
        """Test bulk customer creation."""
        customers = [
            {
                "name": f"Customer {i}",
                "email": f"customer{i}@example.com",
                "phone": f"+1-555-010{i}",
                "city": "New York"
            }
            for i in range(5)
        ]

        created_ids = []
        for customer_data in customers:
            response = customer_client.post(
                "/api/v1/customers",
                json=customer_data,
                headers=auth_headers
            )
            assert response.status_code in [200, 201]
            created_ids.append(response.json()["id"])

        # Verify all were created
        response = customer_client.get(
            "/api/v1/customers",
            headers=auth_headers
        )
        assert response.status_code == 200
        all_customers = response.json()
        assert len(all_customers) >= 5


class TestProductCatalogJourney:
    """Test product management workflow."""

    def test_create_product_catalog(self, product_client, sample_product_data, auth_headers):
        """Test creating products."""
        response = product_client.post(
            "/api/v1/products",
            json=sample_product_data,
            headers=auth_headers
        )

        assert response.status_code in [200, 201]
        product = response.json()
        product_id = product["id"]

        # Verify product
        response = product_client.get(
            f"/api/v1/products/{product_id}",
            headers=auth_headers
        )

        assert response.status_code == 200
        assert response.json()["name"] == sample_product_data["name"]
        assert response.json()["unit_price"] == sample_product_data["unit_price"]

    def test_create_multiple_product_variants(self, product_client, auth_headers):
        """Test creating multiple product variants."""
        base_product = {
            "name": "Laptop",
            "sku": "LAPTOP-001",
            "unit_price": 1500.00
        }

        variants = [
            {**base_product, "name": "Laptop - 8GB RAM", "sku": "LAPTOP-8GB"},
            {**base_product, "name": "Laptop - 16GB RAM", "sku": "LAPTOP-16GB"},
            {**base_product, "name": "Laptop - 32GB RAM", "sku": "LAPTOP-32GB"},
        ]

        created_ids = []
        for variant in variants:
            response = product_client.post(
                "/api/v1/products",
                json=variant,
                headers=auth_headers
            )
            assert response.status_code in [200, 201]
            created_ids.append(response.json()["id"])

        assert len(created_ids) == 3


class TestInvoiceCreationJourney:
    """Test complete invoice lifecycle."""

    def test_create_and_publish_invoice(
        self,
        invoice_client,
        customer_client,
        product_client,
        sample_customer_data,
        sample_product_data,
        sample_invoice_data,
        auth_headers
    ):
        """Test creating and publishing an invoice."""
        # Create customer
        resp = customer_client.post(
            "/api/v1/customers",
            json=sample_customer_data,
            headers=auth_headers
        )
        customer_id = resp.json()["id"]

        # Create product
        resp = product_client.post(
            "/api/v1/products",
            json=sample_product_data,
            headers=auth_headers
        )
        product_id = resp.json()["id"]

        # Create invoice
        invoice_data = {
            **sample_invoice_data,
            "customer_id": customer_id,
            "items": [
                {
                    "product_id": product_id,
                    "description": sample_product_data["name"],
                    "quantity": 1,
                    "unit_price": sample_product_data["unit_price"],
                    "tax_rate": 0.10
                }
            ]
        }

        response = invoice_client.post(
            "/api/v1/invoices",
            json=invoice_data,
            headers=auth_headers
        )

        assert response.status_code in [200, 201]
        invoice = response.json()
        invoice_id = invoice["id"]
        assert invoice["status"] == "draft"

        # Publish invoice
        response = invoice_client.post(
            f"/api/v1/invoices/{invoice_id}/publish",
            headers=auth_headers
        )

        assert response.status_code in [200, 201]
        published_invoice = response.json()
        assert published_invoice["status"] == "published"

    def test_invoice_with_multiple_items(
        self,
        invoice_client,
        customer_client,
        product_client,
        auth_headers
    ):
        """Test invoice with multiple line items."""
        # Create customer
        customer_resp = customer_client.post(
            "/api/v1/customers",
            json={"name": "Multi-item Customer", "email": "multi@example.com"},
            headers=auth_headers
        )
        customer_id = customer_resp.json()["id"]

        # Create multiple products
        products = []
        for i in range(3):
            product_resp = product_client.post(
                "/api/v1/products",
                json={
                    "name": f"Product {i}",
                    "sku": f"PROD-{i:03d}",
                    "unit_price": (i + 1) * 100.0
                },
                headers=auth_headers
            )
            products.append(product_resp.json())

        # Create invoice with all products
        invoice_data = {
            "customer_id": customer_id,
            "invoice_number": f"INV-{int(time.time())}",
            "invoice_date": datetime.now().isoformat(),
            "due_date": (datetime.now() + timedelta(days=30)).isoformat(),
            "items": [
                {
                    "product_id": p["id"],
                    "quantity": 2,
                    "unit_price": p["unit_price"],
                    "tax_rate": 0.10
                }
                for p in products
            ]
        }

        response = invoice_client.post(
            "/api/v1/invoices",
            json=invoice_data,
            headers=auth_headers
        )

        assert response.status_code in [200, 201]
        invoice = response.json()
        assert len(invoice["items"]) == 3

    def test_record_payment_on_invoice(
        self,
        invoice_client,
        customer_client,
        sample_customer_data,
        auth_headers
    ):
        """Test recording payments on invoices."""
        # Create customer and invoice first
        customer_resp = customer_client.post(
            "/api/v1/customers",
            json=sample_customer_data,
            headers=auth_headers
        )
        customer_id = customer_resp.json()["id"]

        # Create invoice
        invoice_data = {
            "customer_id": customer_id,
            "invoice_number": f"INV-PAY-{int(time.time())}",
            "total_amount": 1000.00,
            "amount_due": 1000.00
        }

        invoice_resp = invoice_client.post(
            "/api/v1/invoices",
            json=invoice_data,
            headers=auth_headers
        )
        invoice_id = invoice_resp.json()["id"]

        # Record payment
        payment_data = {
            "amount": 500.00,
            "payment_method": "credit_card",
            "transaction_id": "TXN-12345",
            "payment_date": datetime.now().isoformat()
        }

        response = invoice_client.post(
            f"/api/v1/invoices/{invoice_id}/payments",
            json=payment_data,
            headers=auth_headers
        )

        assert response.status_code in [200, 201]
        payment = response.json()
        assert payment["amount"] == 500.00


class TestExpenseJourney:
    """Test expense creation and management."""

    def test_create_and_categorize_expense(
        self,
        expense_client,
        sample_expense_data,
        auth_headers
    ):
        """Test creating expenses with categorization."""
        response = expense_client.post(
            "/api/v1/expenses",
            json=sample_expense_data,
            headers=auth_headers
        )

        assert response.status_code in [200, 201]
        expense = response.json()
        expense_id = expense["id"]

        # Verify expense
        response = expense_client.get(
            f"/api/v1/expenses/{expense_id}",
            headers=auth_headers
        )

        assert response.status_code == 200
        retrieved = response.json()
        assert retrieved["amount"] == sample_expense_data["amount"]
        assert retrieved["category"] == sample_expense_data["category"]

    def test_bulk_expense_import(self, expense_client, auth_headers):
        """Test importing multiple expenses."""
        expenses = [
            {
                "vendor_name": f"Vendor {i}",
                "amount": 100.0 * (i + 1),
                "category": ["Office Supplies", "Travel", "Utilities"][i % 3],
                "expense_date": datetime.now().isoformat()
            }
            for i in range(5)
        ]

        created_ids = []
        for expense_data in expenses:
            response = expense_client.post(
                "/api/v1/expenses",
                json=expense_data,
                headers=auth_headers
            )
            assert response.status_code in [200, 201]
            created_ids.append(response.json()["id"])

        assert len(created_ids) == 5

    def test_expense_approval_workflow(self, expense_client, auth_headers):
        """Test expense approval process."""
        # Create expense
        expense_data = {
            "vendor_name": "Office Supplies",
            "amount": 500.00,
            "category": "Office Supplies",
            "status": "pending"
        }

        expense_resp = expense_client.post(
            "/api/v1/expenses",
            json=expense_data,
            headers=auth_headers
        )
        expense_id = expense_resp.json()["id"]

        # Approve expense
        response = expense_client.post(
            f"/api/v1/expenses/{expense_id}/approve",
            json={"approver_notes": "Approved by manager"},
            headers=auth_headers
        )

        assert response.status_code in [200, 201]
        approved_expense = response.json()
        assert approved_expense["status"] == "approved"


class TestTransportJourney:
    """Test transport document creation and tracking."""

    def test_create_and_track_transport_document(
        self,
        transport_client,
        sample_transport_data,
        auth_headers
    ):
        """Test creating a transport document and tracking updates."""
        # Create transport document
        response = transport_client.post(
            "/api/v1/transports",
            json=sample_transport_data,
            headers=auth_headers
        )

        assert response.status_code in [200, 201]
        transport = response.json()
        transport_id = transport["id"]

        # Verify creation
        response = transport_client.get(
            f"/api/v1/transports/{transport_id}",
            headers=auth_headers
        )

        assert response.status_code == 200
        assert response.json()["status"] == "pending"

        # Update status to in_transit
        response = transport_client.patch(
            f"/api/v1/transports/{transport_id}",
            json={"status": "in_transit"},
            headers=auth_headers
        )

        assert response.status_code == 200
        assert response.json()["status"] == "in_transit"

        # Update status to delivered
        response = transport_client.patch(
            f"/api/v1/transports/{transport_id}",
            json={"status": "delivered"},
            headers=auth_headers
        )

        assert response.status_code == 200
        assert response.json()["status"] == "delivered"

    def test_batch_transport_creation(self, transport_client, auth_headers):
        """Test creating multiple transport documents."""
        transports = [
            {
                "tracking_number": f"TRK-{i:04d}",
                "origin": f"City {i}",
                "destination": f"City {i+10}",
                "distance_km": 1000 + (i * 100),
                "estimated_cost": 500.0 + (i * 50)
            }
            for i in range(5)
        ]

        created_ids = []
        for transport_data in transports:
            response = transport_client.post(
                "/api/v1/transports",
                json=transport_data,
                headers=auth_headers
            )
            assert response.status_code in [200, 201]
            created_ids.append(response.json()["id"])

        assert len(created_ids) == 5

    def test_transport_cost_estimation(self, transport_client, auth_headers):
        """Test transport cost estimation."""
        transport_data = {
            "origin": "New York",
            "destination": "Los Angeles",
            "distance_km": 2800
        }

        response = transport_client.post(
            "/api/v1/transports/estimate-cost",
            json=transport_data,
            headers=auth_headers
        )

        assert response.status_code in [200, 201]
        estimate = response.json()
        assert "estimated_cost" in estimate
        assert estimate["estimated_cost"] > 0


class TestFullEndToEndWorkflow:
    """Test complete workflow across multiple domains."""

    def test_complete_business_cycle(
        self,
        invoice_client,
        customer_client,
        product_client,
        expense_client,
        transport_client,
        auth_headers,
        sample_customer_data,
        sample_product_data,
        sample_expense_data,
        sample_transport_data
    ):
        """Test a complete business cycle: customer → product → invoice → expense → transport."""
        # Step 1: Create customer
        customer_resp = customer_client.post(
            "/api/v1/customers",
            json=sample_customer_data,
            headers=auth_headers
        )
        assert customer_resp.status_code in [200, 201]
        customer_id = customer_resp.json()["id"]

        # Step 2: Create product
        product_resp = product_client.post(
            "/api/v1/products",
            json=sample_product_data,
            headers=auth_headers
        )
        assert product_resp.status_code in [200, 201]
        product_id = product_resp.json()["id"]

        # Step 3: Create invoice
        invoice_data = {
            "customer_id": customer_id,
            "invoice_number": f"INV-FULL-{int(time.time())}",
            "items": [
                {
                    "product_id": product_id,
                    "quantity": 10,
                    "unit_price": sample_product_data["unit_price"]
                }
            ]
        }
        invoice_resp = invoice_client.post(
            "/api/v1/invoices",
            json=invoice_data,
            headers=auth_headers
        )
        assert invoice_resp.status_code in [200, 201]
        invoice_id = invoice_resp.json()["id"]

        # Step 4: Create expense
        expense_resp = expense_client.post(
            "/api/v1/expenses",
            json=sample_expense_data,
            headers=auth_headers
        )
        assert expense_resp.status_code in [200, 201]
        expense_id = expense_resp.json()["id"]

        # Step 5: Create transport document
        transport_resp = transport_client.post(
            "/api/v1/transports",
            json=sample_transport_data,
            headers=auth_headers
        )
        assert transport_resp.status_code in [200, 201]
        transport_id = transport_resp.json()["id"]

        # Verify all resources were created
        assert customer_id is not None
        assert product_id is not None
        assert invoice_id is not None
        assert expense_id is not None
        assert transport_id is not None
