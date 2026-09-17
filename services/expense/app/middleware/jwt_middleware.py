"""JWT authentication middleware."""

from datetime import datetime, timedelta, timezone
from typing import Optional

import jwt
from fastapi import HTTPException, status
from jwt import DecodeError, ExpiredSignatureError

from app.config import get_settings


class JWTHandler:
    """JWT token generation and validation."""

    def __init__(self):
        self.settings = get_settings()

    def create_token(
        self,
        data: dict,
        expires_delta: Optional[timedelta] = None,
    ) -> str:
        """Create a JWT token."""
        to_encode = data.copy()

        if expires_delta:
            expire = datetime.now(timezone.utc) + expires_delta
        else:
            expire = datetime.now(timezone.utc) + timedelta(
                hours=self.settings.jwt_expiration_hours
            )

        to_encode.update({"exp": expire})

        encoded_jwt = jwt.encode(
            to_encode,
            self.settings.jwt_secret_key,
            algorithm=self.settings.jwt_algorithm,
        )

        return encoded_jwt

    def verify_token(self, token: str) -> dict:
        """Verify and decode JWT token."""
        try:
            payload = jwt.decode(
                token,
                self.settings.jwt_secret_key,
                algorithms=[self.settings.jwt_algorithm],
            )
            return payload
        except ExpiredSignatureError:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Token has expired",
                headers={"WWW-Authenticate": "Bearer"},
            )
        except DecodeError as e:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail=f"Invalid token: {str(e)}",
                headers={"WWW-Authenticate": "Bearer"},
            )
        except Exception as e:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail=f"Token validation failed: {str(e)}",
                headers={"WWW-Authenticate": "Bearer"},
            )

    def extract_token_from_header(self, auth_header: str) -> str:
        """Extract token from Authorization header."""
        try:
            scheme, credentials = auth_header.split()
            if scheme.lower() != "bearer":
                raise ValueError("Invalid authentication scheme")
            return credentials
        except ValueError as e:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail=f"Invalid authorization header: {str(e)}",
                headers={"WWW-Authenticate": "Bearer"},
            )


class AuthContext:
    """Context object for authenticated request."""

    def __init__(self, user_id: int, company_id: int, permissions: list[str] = None):
        self.user_id = user_id
        self.company_id = company_id
        self.permissions = permissions or []


async def validate_company_context(
    company_id: int,
    auth_context: AuthContext,
) -> None:
    """Validate that the authenticated user belongs to the requested company."""
    if auth_context.company_id != company_id:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Not authorized to access this company's data",
        )
