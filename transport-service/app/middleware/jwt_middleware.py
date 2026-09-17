"""JWT authentication middleware."""
import jwt
from typing import Optional
from fastapi import Request, HTTPException, status
from config.settings import settings
from config.logging_config import logger


class JWTBearer:
    """JWT Bearer token validator."""

    async def __call__(self, request: Request) -> dict:
        """Validate JWT token from Authorization header."""
        try:
            # Extract token from Authorization header
            auth_header = request.headers.get("Authorization")
            if not auth_header:
                raise HTTPException(
                    status_code=status.HTTP_403_FORBIDDEN,
                    detail="Authorization header missing"
                )

            # Expected format: "Bearer <token>"
            parts = auth_header.split()
            if len(parts) != 2 or parts[0].lower() != "bearer":
                raise HTTPException(
                    status_code=status.HTTP_403_FORBIDDEN,
                    detail="Invalid authorization header format"
                )

            token = parts[1]

            # Decode and validate token
            try:
                payload = jwt.decode(
                    token,
                    settings.JWT_SECRET_KEY,
                    algorithms=[settings.JWT_ALGORITHM]
                )
            except jwt.ExpiredSignatureError:
                logger.warning("JWT token expired")
                raise HTTPException(
                    status_code=status.HTTP_401_UNAUTHORIZED,
                    detail="Token expired"
                )
            except jwt.InvalidTokenError as e:
                logger.warning(f"Invalid JWT token: {str(e)}")
                raise HTTPException(
                    status_code=status.HTTP_401_UNAUTHORIZED,
                    detail="Invalid token"
                )

            # Extract user and company info
            user_id = payload.get("user_id")
            company_id = payload.get("company_id")

            if not user_id or not company_id:
                raise HTTPException(
                    status_code=status.HTTP_403_FORBIDDEN,
                    detail="Token missing required claims"
                )

            request.state.user_id = user_id
            request.state.company_id = company_id

            return {
                "user_id": user_id,
                "company_id": company_id,
                "payload": payload
            }

        except HTTPException:
            raise
        except Exception as e:
            logger.error(f"JWT validation error: {str(e)}")
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Token validation error"
            )


jwt_bearer = JWTBearer()


async def get_current_user(request: Request) -> dict:
    """Get current authenticated user from request."""
    auth_data = await jwt_bearer(request)
    return auth_data
