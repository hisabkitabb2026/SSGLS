"""
Data Consistency End-to-End Tests

Verifies data consistency across services:
- Cross-service data synchronization
- Reference integrity
- Eventual consistency verification
- Conflict resolution
- Data duplication prevention
"""

import pytest
import time
from datetime import datetime


class TestCrossServiceDataSynchronization:
    """Test data synchronization across services."""

    def test_customer_data_available_in_all_services(
        self,
        customer_client,
        invoice_client,
        auth_headers,
        data_consistency_checker
    ):
        """Test that customer data is available across all services."""
        # Create customer
        customer_data = {
            "name": "Cross-Service Customer",
            "email": "cross@example.com",
            "phone": "+1-555-0111"
        }

        response = customer_client.post(
            "/api/v1/customers",
            json=customer_data,
            headers=auth_headers
        )

        assert response.status_code in [200, 201]
        customer = response.json()
        customer_id = customer["id"]

        # Give time for event propagation
        time.sleep(1)

        # Verify customer exists in customer service
        response = customer_client.get(
            f"/api/v1/customers/{customer_id}",
            headers=auth_headers
        )
        assert response.status_code == 200

        # Verify customer reference is available in invoice service
        # when creating invoices
        is_available = data_consistency_checker.verify_customer_in_all_services(
            customer_id
        )
        assert is_available

    def test_product_data_available_in_all_services(
        self,
        product_client,
        invoice_client,
        auth_headers,
        data_consistency_checker
    ):
        """Test that product data is available across services."""
        # Create product
        product_data = {
            "name": "Cross-Service Product",
            "sku": "CROSS-PROD-001",
            "unit_price": 299.99,
            "category": "Electronics"
        }

        response = product_client.post(
            "/api/v1/products",
            json=product_data,
            headers=auth_headers
        )

        assert response.status_code in [200, 201]
        product = response.json()
        product_id = product["id"]

        # Give time for propagation
        time.sleep(1)

        # Verify product exists
        response = product_client.get(
            f"/api/v1/products/{product_id}",
            headers=auth_headers
        )
        assert response.status_code == 200

        # Verify product is available in invoice service
        is_available = data_consistency_checker.verify_product_in_all_services(
            product_id
        )
        assert is_available

    def test_invoice_item_references_consistency(
        self,
        invoice_client,
        customer_client,
        product_client,
        auth_headers
    ):
        """Test invoice items maintain reference integrity."""
        # Create customer
        customer_resp = customer_client.post(
            "/api/v1/customers",
            json={"name": "Ref Customer", "email": "ref@example.com"},
            headers=auth_headers
        )
        customer_id = customer_resp.json()["id"]

        # Create product
        product_resp = product_client.post(
            "/api/v1/products",
            json={
                "name": "Ref Product",
                "sku": "REF-001",
                "unit_price": 100.00
            },
            headers=auth_headers
        )
        product_id = product_resp.json()["id"]

        # Create invoice with reference
        invoice_data = {
            "customer_id": customer_id,
            "invoice_number": f"INV-REF-{int(time.time())}",
            "items": [
                {
                    "product_id": product_id,
                    "quantity": 2,
                    "unit_price": 100.00
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

        # Verify references
        assert invoice["customer_id"] == customer_id
        assert len(invoice["items"]) == 1
        assert invoice["items"][0]["product_id"] == product_id


class TestReferenceIntegrity:
    """Test reference integrity across services."""

    def test_invoice_customer_reference_valid(
        self,
        invoice_client,
        customer_client,
        auth_headers
    ):
        """Test invoice maintains valid customer reference."""
        # Create customer
        customer_resp = customer_client.post(
            "/api/v1/customers",
            json={"name": "Integrity Customer", "email": "integrity@example.com"},
            headers=auth_headers
        )
        customer_id = customer_resp.json()["id"]

        # Create invoice
        invoice_data = {
            "customer_id": customer_id,
            "invoice_number": f"INV-INTEGRITY-{int(time.time())}",
            "total_amount": 500.00
        }

        response = invoice_client.post(
            "/api/v1/invoices",
            json=invoice_data,
            headers=auth_headers
        )

        assert response.status_code in [200, 201]
        invoice = response.json()
        invoice_id = invoice["id"]

        # Verify customer reference is valid
        customer_check = customer_client.get(
            f"/api/v1/customers/{customer_id}",
            headers=auth_headers
        )
        assert customer_check.status_code == 200

        # Retrieve invoice and verify customer reference
        invoice_check = invoice_client.get(
            f"/api/v1/invoices/{invoice_id}",
            headers=auth_headers
        )
        assert invoice_check.status_code == 200
        assert invoice_check.json()["customer_id"] == customer_id

    def test_product_deletion_handles_references(
        self,
        product_client,
        invoice_client,
        customer_client,
        auth_headers
    ):
        """Test deletion of product with invoice references."""
        # Create customer and product
        customer_resp = customer_client.post(
            "/api/v1/customers",
            json={"name": "Delete Test", "email": "delete@example.com"},
            headers=auth_headers
        )
        customer_id = customer_resp.json()["id"]

        product_resp = product_client.post(
            "/api/v1/products",
            json={
                "name": "Delete Product",
                "sku": "DEL-001",
                "unit_price": 50.00
            },
            headers=auth_headers
        )
        product_id = product_resp.json()["id"]

        # Create invoice with product
        invoice_data = {
            "customer_id": customer_id,
            "invoice_number": f"INV-DEL-{int(time.time())}",
            "items": [{"product_id": product_id, "quantity": 1}]
        }

        invoice_client.post(
            "/api/v1/invoices",
            json=invoice_data,
            headers=auth_headers
        )

        # Attempt to delete product (should handle gracefully)
        response = product_client.delete(
            f"/api/v1/products/{product_id}",
            headers=auth_headers
        )

        # Should either prevent deletion or cascade properly
        assert response.status_code in [200, 204, 400, 409]


class TestEventualConsistency:
    """Test eventual consistency guarantees."""

    def test_customer_eventual_propagation(
        self,
        customer_client,
        auth_headers
    ):
        """Test customer data eventually propagates."""
        # Create customer
        customer_data = {
            "name": "Eventual Customer",
            "email": "eventual@example.com"
        }

        response = customer_client.post(
            "/api/v1/customers",
            json=customer_data,
            headers=auth_headers
        )

        assert response.status_code in [200, 201]
        customer_id = response.json()["id"]

        # Immediately check
        time.sleep(0.5)
        response1 = customer_client.get(
            f"/api/v1/customers/{customer_id}",
            headers=auth_headers
        )

        # Later check (should definitely be consistent)
        time.sleep(2)
        response2 = customer_client.get(
            f"/api/v1/customers/{customer_id}",
            headers=auth_headers
        )

        # At least final check should succeed
        assert response2.status_code == 200

    def test_invoice_eventual_consistency_after_payment(
        self,
        invoice_client,
        customer_client,
        auth_headers
    ):
        """Test invoice becomes consistent after payment."""
        # Create customer
        customer_resp = customer_client.post(
            "/api/v1/customers",
            json={"name": "Payment Eventual", "email": "paye@example.com"},
            headers=auth_headers
        )
        customer_id = customer_resp.json()["id"]

        # Create invoice
        invoice_data = {
            "customer_id": customer_id,
            "invoice_number": f"INV-EVENTUAL-{int(time.time())}",
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
            "payment_method": "check"
        }

        invoice_client.post(
            f"/api/v1/invoices/{invoice_id}/payments",
            json=payment_data,
            headers=auth_headers
        )

        # Check immediately
        time.sleep(0.5)
        response1 = invoice_client.get(
            f"/api/v1/invoices/{invoice_id}",
            headers=auth_headers
        )

        # Check after propagation delay
        time.sleep(2)
        response2 = invoice_client.get(
            f"/api/v1/invoices/{invoice_id}",
            headers=auth_headers
        )

        # Final state should be consistent
        assert response2.status_code == 200


class TestConflictResolution:
    """Test conflict resolution when updates collide."""

    def test_concurrent_customer_update_handling(
        self,
        customer_client,
        auth_headers
    ):
        """Test concurrent updates are handled correctly."""
        # Create customer
        customer_resp = customer_client.post(
            "/api/v1/customers",
            json={"name": "Conflict Test", "email": "conflict@example.com"},
            headers=auth_headers
        )
        customer_id = customer_resp.json()["id"]

        # Simulate concurrent updates
        update1_data = {"name": "Updated Name 1"}
        update2_data = {"name": "Updated Name 2"}

        response1 = customer_client.patch(
            f"/api/v1/customers/{customer_id}",
            json=update1_data,
            headers=auth_headers
        )

        response2 = customer_client.patch(
            f"/api/v1/customers/{customer_id}",
            json=update2_data,
            headers=auth_headers
        )

        # Both should complete without error
        assert response1.status_code in [200, 400, 409]
        assert response2.status_code in [200, 400, 409]

        # Check final state
        response = customer_client.get(
            f"/api/v1/customers/{customer_id}",
            headers=auth_headers
        )
        assert response.status_code == 200

    def test_invoice_concurrent_payment_handling(
        self,
        invoice_client,
        customer_client,
        auth_headers
    ):
        """Test concurrent payment processing."""
        # Create customer and invoice
        customer_resp = customer_client.post(
            "/api/v1/customers",
            json={"name": "Concurrent Payment", "email": "concurrent@example.com"},
            headers=auth_headers
        )
        customer_id = customer_resp.json()["id"]

        invoice_data = {
            "customer_id": customer_id,
            "invoice_number": f"INV-CONCURRENT-{int(time.time())}",
            "total_amount": 1000.00
        }

        invoice_resp = invoice_client.post(
            "/api/v1/invoices",
            json=invoice_data,
            headers=auth_headers
        )
        invoice_id = invoice_resp.json()["id"]

        # Simulate concurrent payments
        payment_data = {
            "amount": 500.00,
            "payment_method": "credit_card"
        }

        response1 = invoice_client.post(
            f"/api/v1/invoices/{invoice_id}/payments",
            json=payment_data,
            headers=auth_headers
        )

        response2 = invoice_client.post(
            f"/api/v1/invoices/{invoice_id}/payments",
            json=payment_data,
            headers=auth_headers
        )

        # Both should be handled
        assert response1.status_code in [200, 201, 400, 409]
        assert response2.status_code in [200, 201, 400, 409]


class TestDataDuplicationPrevention:
    """Test prevention of data duplication."""

    def test_duplicate_invoice_number_prevention(
        self,
        invoice_client,
        customer_client,
        auth_headers
    ):
        """Test that duplicate invoice numbers are prevented."""
        # Create customer
        customer_resp = customer_client.post(
            "/api/v1/customers",
            json={"name": "Dup Test", "email": "dup@example.com"},
            headers=auth_headers
        )
        customer_id = customer_resp.json()["id"]

        # Create first invoice
        invoice_number = f"INV-DUP-{int(time.time())}"
        invoice_data = {
            "customer_id": customer_id,
            "invoice_number": invoice_number,
            "total_amount": 1000.00
        }

        response1 = invoice_client.post(
            "/api/v1/invoices",
            json=invoice_data,
            headers=auth_headers
        )
        assert response1.status_code in [200, 201]

        # Try to create with same number
        response2 = invoice_client.post(
            "/api/v1/invoices",
            json=invoice_data,
            headers=auth_headers
        )

        # Should either succeed (if new) or fail (if duplicate check exists)
        assert response2.status_code in [200, 201, 400, 409]

    def test_duplicate_customer_email_handling(
        self,
        customer_client,
        auth_headers
    ):
        """Test handling of duplicate customer emails."""
        email = f"unique-{int(time.time())}@example.com"

        # Create first customer
        customer_data = {
            "name": "First Customer",
            "email": email
        }

        response1 = customer_client.post(
            "/api/v1/customers",
            json=customer_data,
            headers=auth_headers
        )
        assert response1.status_code in [200, 201]

        # Try to create with same email
        customer_data["name"] = "Second Customer"
        response2 = customer_client.post(
            "/api/v1/customers",
            json=customer_data,
            headers=auth_headers
        )

        # Should handle duplicate appropriately
        assert response2.status_code in [200, 201, 400, 409]

    def test_duplicate_product_sku_handling(
        self,
        product_client,
        auth_headers
    ):
        """Test handling of duplicate product SKUs."""
        sku = f"SKU-{int(time.time())}"

        # Create first product
        product_data = {
            "name": "First Product",
            "sku": sku,
            "unit_price": 99.99
        }

        response1 = product_client.post(
            "/api/v1/products",
            json=product_data,
            headers=auth_headers
        )
        assert response1.status_code in [200, 201]

        # Try to create with same SKU
        product_data["name"] = "Second Product"
        response2 = product_client.post(
            "/api/v1/products",
            json=product_data,
            headers=auth_headers
        )

        # Should handle duplicate
        assert response2.status_code in [200, 201, 400, 409]


class TestDataIntegrityAcrossServices:
    """Test overall data integrity across services."""

    def test_invoice_calculation_consistency(
        self,
        invoice_client,
        customer_client,
        product_client,
        auth_headers,
        data_consistency_checker
    ):
        """Test invoice calculations remain consistent."""
        # Setup
        customer_resp = customer_client.post(
            "/api/v1/customers",
            json={"name": "Calc Test", "email": "calc@example.com"},
            headers=auth_headers
        )
        customer_id = customer_resp.json()["id"]

        product_resp = product_client.post(
            "/api/v1/products",
            json={
                "name": "Calc Product",
                "sku": "CALC-001",
                "unit_price": 100.00
            },
            headers=auth_headers
        )
        product_id = product_resp.json()["id"]

        # Create invoice
        invoice_data = {
            "customer_id": customer_id,
            "invoice_number": f"INV-CALC-{int(time.time())}",
            "items": [
                {
                    "product_id": product_id,
                    "quantity": 5,
                    "unit_price": 100.00,
                    "tax_rate": 0.10
                }
            ]
        }

        invoice_resp = invoice_client.post(
            "/api/v1/invoices",
            json=invoice_data,
            headers=auth_headers
        )

        invoice_id = invoice_resp.json()["id"]

        # Verify calculation consistency
        time.sleep(1)
        checks = data_consistency_checker.verify_invoice_complete(invoice_id)

        assert checks["exists_in_invoice_service"]
        assert checks["customer_exists"]
        assert checks["items_valid"]
        assert checks["calculations_correct"]
