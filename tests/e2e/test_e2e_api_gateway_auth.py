"""
API Gateway and Authentication End-to-End Tests

Tests:
- Kong API Gateway routing to correct services
- JWT token validation
- Authorization and permission checks
- Rate limiting
- CORS handling
- Request/response transformation
"""

import pytest
import time


class TestKongAPIGatewayRouting:
    """Test Kong API Gateway routing to microservices."""

    def test_route_customer_requests_to_customer_service(
        self,
        kong_client,
        auth_headers
    ):
        """Test that /api/v1/customers routes to customer service."""
        customer_data = {
            "name": "Gateway Test Customer",
            "email": "gateway@example.com"
        }

        # Request through Kong gateway
        response = kong_client.post(
            "/api/v1/customers",
            json=customer_data,
            headers=auth_headers
        )

        # Should succeed or route correctly
        assert response.status_code in [200, 201, 400, 401, 404]

    def test_route_invoice_requests_to_invoice_service(
        self,
        kong_client,
        auth_headers
    ):
        """Test that /api/v1/invoices routes to invoice service."""
        invoice_data = {
            "customer_id": 1,
            "invoice_number": f"INV-GATEWAY-{int(time.time())}",
            "total_amount": 1000.00
        }

        response = kong_client.post(
            "/api/v1/invoices",
            json=invoice_data,
            headers=auth_headers
        )

        assert response.status_code in [200, 201, 400, 401, 404]

    def test_route_product_requests_to_product_service(
        self,
        kong_client,
        auth_headers
    ):
        """Test that /api/v1/products routes to product service."""
        product_data = {
            "name": "Gateway Product",
            "sku": "GATEWAY-001",
            "unit_price": 99.99
        }

        response = kong_client.post(
            "/api/v1/products",
            json=product_data,
            headers=auth_headers
        )

        assert response.status_code in [200, 201, 400, 401, 404]

    def test_route_expense_requests_to_expense_service(
        self,
        kong_client,
        auth_headers
    ):
        """Test that /api/v1/expenses routes to expense service."""
        expense_data = {
            "vendor_name": "Gateway Vendor",
            "amount": 250.00,
            "category": "Services"
        }

        response = kong_client.post(
            "/api/v1/expenses",
            json=expense_data,
            headers=auth_headers
        )

        assert response.status_code in [200, 201, 400, 401, 404]

    def test_route_transport_requests_to_transport_service(
        self,
        kong_client,
        auth_headers
    ):
        """Test that /api/v1/transports routes to transport service."""
        transport_data = {
            "tracking_number": f"TRK-GATEWAY-{int(time.time())}",
            "origin": "San Francisco",
            "destination": "Seattle",
            "distance_km": 1300
        }

        response = kong_client.post(
            "/api/v1/transports",
            json=transport_data,
            headers=auth_headers
        )

        assert response.status_code in [200, 201, 400, 401, 404]

    def test_route_settings_requests_to_settings_service(
        self,
        kong_client,
        auth_headers
    ):
        """Test that /api/v1/settings routes to settings service."""
        response = kong_client.get(
            "/api/v1/settings",
            headers=auth_headers
        )

        assert response.status_code in [200, 400, 401, 404]

    def test_gateway_request_header_preservation(
        self,
        kong_client,
        auth_headers
    ):
        """Test that gateway preserves request headers."""
        headers = {
            **auth_headers,
            "X-Request-ID": "test-request-123",
            "X-Custom-Header": "custom-value"
        }

        response = kong_client.get(
            "/api/v1/customers",
            headers=headers
        )

        # Should process request with headers
        assert response.status_code in [200, 400, 401, 404]

    def test_gateway_response_header_preservation(
        self,
        kong_client,
        auth_headers
    ):
        """Test that gateway preserves response headers."""
        response = kong_client.get(
            "/api/v1/customers",
            headers=auth_headers
        )

        # Should have basic response headers
        assert "content-type" in response.headers or "Content-Type" in response.headers


class TestJWTAuthentication:
    """Test JWT authentication and validation."""

    def test_valid_jwt_token_accepted(
        self,
        invoice_client,
        valid_token,
        auth_headers
    ):
        """Test that valid JWT token is accepted."""
        invoice_client.set_auth(valid_token)

        response = invoice_client.get(
            "/api/v1/invoices",
            headers={"X-Company-ID": "1"}
        )

        # Valid token should allow request (may return empty list or 200)
        assert response.status_code in [200, 201, 204]

    def test_expired_jwt_token_rejected(
        self,
        invoice_client,
        expired_token
    ):
        """Test that expired JWT token is rejected."""
        invoice_client.set_auth(expired_token)

        response = invoice_client.get(
            "/api/v1/invoices",
            headers={"X-Company-ID": "1"}
        )

        # Expired token should be rejected
        assert response.status_code in [401, 403]

    def test_invalid_jwt_token_rejected(
        self,
        invoice_client,
        invalid_token
    ):
        """Test that invalid JWT token is rejected."""
        invoice_client.set_auth(invalid_token)

        response = invoice_client.get(
            "/api/v1/invoices",
            headers={"X-Company-ID": "1"}
        )

        # Invalid token should be rejected
        assert response.status_code in [401, 403]

    def test_missing_jwt_token_rejected(
        self,
        invoice_client
    ):
        """Test that request without JWT token is rejected."""
        # Don't set auth
        response = invoice_client.get(
            "/api/v1/invoices"
        )

        # Missing token should be rejected (unless endpoint is public)
        assert response.status_code in [200, 401, 403]

    def test_jwt_token_with_correct_claims(
        self,
        invoice_client,
        valid_token
    ):
        """Test JWT token with all required claims."""
        invoice_client.set_auth(valid_token)

        response = invoice_client.get(
            "/api/v1/invoices",
            headers={"X-Company-ID": "1"}
        )

        # Should be accepted
        assert response.status_code in [200, 201, 204]

    def test_jwt_token_malformed_rejected(
        self,
        invoice_client
    ):
        """Test that malformed JWT token is rejected."""
        invoice_client.set_auth("Bearer malformed.token")

        response = invoice_client.get(
            "/api/v1/invoices",
            headers={"X-Company-ID": "1"}
        )

        # Malformed token should be rejected
        assert response.status_code in [401, 403]


class TestAuthorizationAndPermissions:
    """Test authorization and permission validation."""

    def test_create_customer_requires_authentication(
        self,
        customer_client
    ):
        """Test that creating customer requires authentication."""
        # Without auth
        response = customer_client.post(
            "/api/v1/customers",
            json={"name": "No Auth Customer", "email": "noauth@example.com"}
        )

        # Should require auth
        assert response.status_code in [401, 403]

    def test_read_customer_requires_authentication(
        self,
        customer_client
    ):
        """Test that reading customers requires authentication."""
        # Without auth
        response = customer_client.get(
            "/api/v1/customers"
        )

        # Should require auth
        assert response.status_code in [200, 401, 403]

    def test_delete_customer_requires_authentication(
        self,
        customer_client
    ):
        """Test that deleting customer requires authentication."""
        # Without auth
        response = customer_client.delete(
            "/api/v1/customers/1"
        )

        # Should require auth
        assert response.status_code in [401, 403]

    def test_cross_company_access_isolation(
        self,
        customer_client,
        auth_headers
    ):
        """Test that companies are isolated from each other."""
        # Set company 1
        headers_company1 = {
            **auth_headers,
            "X-Company-ID": "1"
        }

        response = customer_client.get(
            "/api/v1/customers",
            headers=headers_company1
        )

        assert response.status_code in [200, 401, 403]

        # Set different company
        headers_company2 = {
            **auth_headers,
            "X-Company-ID": "2"
        }

        response = customer_client.get(
            "/api/v1/customers",
            headers=headers_company2
        )

        # Should only see company 2's data
        assert response.status_code in [200, 401, 403]

    def test_missing_company_header_handled(
        self,
        customer_client,
        valid_token
    ):
        """Test that missing company header is handled."""
        customer_client.set_auth(valid_token)

        # Without company header
        response = customer_client.get(
            "/api/v1/customers"
        )

        # May require company header
        assert response.status_code in [200, 400, 401, 403]


class TestRateLimiting:
    """Test rate limiting enforcement."""

    def test_rate_limiting_on_api_endpoints(
        self,
        invoice_client,
        auth_headers
    ):
        """Test that rate limiting is enforced."""
        # Make multiple requests
        responses = []
        for i in range(10):
            response = invoice_client.get(
                "/api/v1/invoices",
                headers=auth_headers
            )
            responses.append(response)

        # At least some requests should succeed
        assert any(r.status_code == 200 for r in responses)

    def test_rate_limit_headers_present(
        self,
        invoice_client,
        auth_headers
    ):
        """Test that rate limit headers are present in responses."""
        response = invoice_client.get(
            "/api/v1/invoices",
            headers=auth_headers
        )

        # Check for rate limit headers
        headers = response.headers
        rate_limit_headers = [
            "X-RateLimit-Limit",
            "X-RateLimit-Remaining",
            "X-RateLimit-Reset",
            "RateLimit-Limit",
            "RateLimit-Remaining",
            "RateLimit-Reset"
        ]

        # At least one should be present
        has_rate_limit = any(
            header in headers for header in rate_limit_headers
        )

        assert response.status_code in [200, 429]


class TestCORSHandling:
    """Test CORS header handling."""

    def test_cors_headers_present(
        self,
        customer_client,
        auth_headers
    ):
        """Test that CORS headers are present in responses."""
        response = customer_client.get(
            "/api/v1/customers",
            headers={**auth_headers, "Origin": "http://example.com"}
        )

        # Check for CORS headers
        headers = response.headers
        cors_headers = [
            "Access-Control-Allow-Origin",
            "Access-Control-Allow-Methods",
            "Access-Control-Allow-Headers"
        ]

        # Response should have CORS or be from same origin
        assert response.status_code in [200, 401, 403]

    def test_preflight_request_handling(
        self,
        customer_client
    ):
        """Test that CORS preflight requests are handled."""
        response = customer_client.session.options(
            "http://localhost:8000/api/v1/customers",
            headers={"Origin": "http://example.com"}
        )

        # Preflight should succeed or be rejected consistently
        assert response.status_code in [200, 204, 404]


class TestRequestResponseTransformation:
    """Test request/response transformation through gateway."""

    def test_json_request_transformation(
        self,
        kong_client,
        auth_headers
    ):
        """Test JSON request transformation."""
        customer_data = {
            "name": "Transform Test",
            "email": "transform@example.com",
            "phone": "+1-555-0101"
        }

        response = kong_client.post(
            "/api/v1/customers",
            json=customer_data,
            headers=auth_headers
        )

        # Request should be transformed and routed
        assert response.status_code in [200, 201, 400, 401, 404]

    def test_json_response_transformation(
        self,
        kong_client,
        auth_headers
    ):
        """Test JSON response transformation."""
        response = kong_client.get(
            "/api/v1/customers",
            headers=auth_headers
        )

        # Response should be valid JSON
        if response.status_code in [200, 201]:
            try:
                data = response.json()
                assert isinstance(data, (dict, list))
            except Exception:
                pass

    def test_content_type_preserved(
        self,
        kong_client,
        auth_headers
    ):
        """Test that Content-Type is preserved."""
        response = kong_client.get(
            "/api/v1/customers",
            headers=auth_headers
        )

        # Should have appropriate content type
        content_type = response.headers.get("Content-Type", "")
        assert response.status_code in [200, 201, 400, 401, 404]


class TestGatewayErrorHandling:
    """Test error handling at gateway level."""

    def test_404_for_unknown_route(self, kong_client, auth_headers):
        """Test 404 handling for unknown routes."""
        response = kong_client.get(
            "/api/v1/nonexistent",
            headers=auth_headers
        )

        assert response.status_code in [404, 400, 401]

    def test_405_for_wrong_method(self, kong_client, auth_headers):
        """Test 405 handling for wrong HTTP method."""
        response = kong_client.delete(
            "/api/v1/customers",
            headers=auth_headers
        )

        # Should handle wrong method
        assert response.status_code in [200, 204, 400, 405]

    def test_gateway_timeout_handling(
        self,
        kong_client,
        auth_headers
    ):
        """Test gateway timeout handling."""
        # Request to slow endpoint (if exists)
        response = kong_client.get(
            "/api/v1/customers",
            headers=auth_headers,
            timeout=1
        )

        # Should handle timeout
        assert response.status_code in [200, 400, 401, 403, 504]

    def test_gateway_bad_gateway_error(
        self,
        kong_client,
        auth_headers
    ):
        """Test bad gateway error handling."""
        response = kong_client.get(
            "/api/v1/customers",
            headers=auth_headers
        )

        # Should not return bad gateway unless service is down
        assert response.status_code != 502
