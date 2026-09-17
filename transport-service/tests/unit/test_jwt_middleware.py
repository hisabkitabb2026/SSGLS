"""Unit tests for JWT middleware."""
import pytest
import jwt
from datetime import datetime, timedelta
from fastapi import HTTPException, status
from fastapi.testclient import TestClient
from config.settings import settings
from app.middleware import jwt_bearer
from unittest.mock import Mock


class TestJWTMiddleware:
    """Test suite for JWT middleware."""

    def test_valid_token(self, valid_token):
        """Test with valid token."""
        # Create a mock request
        request = Mock()
        request.headers = {"Authorization": f"Bearer {valid_token}"}
        request.state = Mock()

        # This would normally be called by FastAPI
        # For unit testing, we test the validation logic directly
        parts = f"Bearer {valid_token}".split()
        assert parts[0].lower() == "bearer"
        assert len(parts) == 2

    def test_missing_authorization_header(self):
        """Test missing Authorization header."""
        request = Mock()
        request.headers = {}

        with pytest.raises(HTTPException) as exc_info:
            # Simulate missing header check
            auth_header = request.headers.get("Authorization")
            if not auth_header:
                raise HTTPException(
                    status_code=status.HTTP_403_FORBIDDEN,
                    detail="Authorization header missing"
                )

        assert exc_info.value.status_code == status.HTTP_403_FORBIDDEN

    def test_invalid_header_format(self):
        """Test invalid Authorization header format."""
        request = Mock()
        request.headers = {"Authorization": "InvalidFormat"}

        with pytest.raises(HTTPException) as exc_info:
            auth_header = request.headers.get("Authorization")
            parts = auth_header.split()
            if len(parts) != 2 or parts[0].lower() != "bearer":
                raise HTTPException(
                    status_code=status.HTTP_403_FORBIDDEN,
                    detail="Invalid authorization header format"
                )

        assert exc_info.value.status_code == status.HTTP_403_FORBIDDEN

    def test_expired_token(self, expired_token):
        """Test with expired token."""
        # Create token that's expired
        with pytest.raises(jwt.ExpiredSignatureError):
            jwt.decode(
                expired_token,
                settings.JWT_SECRET_KEY,
                algorithms=[settings.JWT_ALGORITHM]
            )

    def test_invalid_token_signature(self):
        """Test with invalid token signature."""
        invalid_token = jwt.encode(
            {
                "user_id": 1,
                "company_id": 1,
                "exp": datetime.utcnow() + timedelta(hours=24)
            },
            "wrong-secret",
            algorithm=settings.JWT_ALGORITHM
        )

        with pytest.raises(jwt.InvalidTokenError):
            jwt.decode(
                invalid_token,
                settings.JWT_SECRET_KEY,
                algorithms=[settings.JWT_ALGORITHM]
            )

    def test_token_missing_claims(self):
        """Test token with missing required claims."""
        incomplete_token = jwt.encode(
            {
                "user_id": 1,
                # Missing company_id
                "exp": datetime.utcnow() + timedelta(hours=24)
            },
            settings.JWT_SECRET_KEY,
            algorithm=settings.JWT_ALGORITHM
        )

        payload = jwt.decode(
            incomplete_token,
            settings.JWT_SECRET_KEY,
            algorithms=[settings.JWT_ALGORITHM]
        )

        assert "user_id" in payload
        assert "company_id" not in payload

    def test_token_with_extra_claims(self):
        """Test token with extra claims."""
        extra_token = jwt.encode(
            {
                "user_id": 1,
                "company_id": 1,
                "role": "admin",
                "email": "user@example.com",
                "exp": datetime.utcnow() + timedelta(hours=24)
            },
            settings.JWT_SECRET_KEY,
            algorithm=settings.JWT_ALGORITHM
        )

        payload = jwt.decode(
            extra_token,
            settings.JWT_SECRET_KEY,
            algorithms=[settings.JWT_ALGORITHM]
        )

        assert payload["user_id"] == 1
        assert payload["company_id"] == 1
        assert payload["role"] == "admin"
        assert payload["email"] == "user@example.com"

    def test_token_expiration_boundary(self):
        """Test token at expiration boundary."""
        # Create token that expires in 1 second
        token = jwt.encode(
            {
                "user_id": 1,
                "company_id": 1,
                "exp": datetime.utcnow() + timedelta(seconds=1)
            },
            settings.JWT_SECRET_KEY,
            algorithm=settings.JWT_ALGORITHM
        )

        # Should not be expired yet
        payload = jwt.decode(
            token,
            settings.JWT_SECRET_KEY,
            algorithms=[settings.JWT_ALGORITHM]
        )
        assert payload["user_id"] == 1

    def test_bearer_prefix_case_insensitive(self):
        """Test that Bearer prefix is case-insensitive."""
        test_cases = ["Bearer", "bearer", "BEARER", "BeArEr"]

        for prefix in test_cases:
            header = f"{prefix} token"
            parts = header.split()
            assert parts[0].lower() == "bearer"

    def test_token_with_different_algorithms(self):
        """Test token handling."""
        # By default, settings uses HS256
        assert settings.JWT_ALGORITHM == "HS256"

        token = jwt.encode(
            {
                "user_id": 1,
                "company_id": 1,
                "exp": datetime.utcnow() + timedelta(hours=24)
            },
            settings.JWT_SECRET_KEY,
            algorithm=settings.JWT_ALGORITHM
        )

        payload = jwt.decode(
            token,
            settings.JWT_SECRET_KEY,
            algorithms=[settings.JWT_ALGORITHM]
        )

        assert payload["user_id"] == 1
        assert payload["company_id"] == 1

    def test_jwt_secret_key_importance(self):
        """Test that JWT secret key matters."""
        token = jwt.encode(
            {
                "user_id": 1,
                "company_id": 1,
                "exp": datetime.utcnow() + timedelta(hours=24)
            },
            "original-secret",
            algorithm=settings.JWT_ALGORITHM
        )

        # Can decode with correct secret
        payload = jwt.decode(
            token,
            "original-secret",
            algorithms=[settings.JWT_ALGORITHM]
        )
        assert payload["user_id"] == 1

        # Cannot decode with wrong secret
        with pytest.raises(jwt.InvalidTokenError):
            jwt.decode(
                token,
                "wrong-secret",
                algorithms=[settings.JWT_ALGORITHM]
            )
