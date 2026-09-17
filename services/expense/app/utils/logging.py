"""Structured logging configuration."""

import json
import logging
import logging.config
from typing import Any

import structlog


def configure_logging(level: str = "INFO", format_type: str = "json"):
    """Configure structured logging with structlog."""
    level_int = getattr(logging, level.upper())

    if format_type == "json":
        formatter = structlog.processors.JSONRenderer()
    else:
        formatter = structlog.dev.ConsoleRenderer()

    structlog.configure(
        processors=[
            structlog.stdlib.filter_by_level,
            structlog.stdlib.add_logger_name,
            structlog.stdlib.add_log_level,
            structlog.stdlib.PositionalArgumentsFormatter(),
            structlog.processors.TimeStamper(fmt="iso"),
            structlog.processors.StackInfoRenderer(),
            structlog.processors.format_exc_info,
            structlog.processors.UnicodeDecoder(),
            formatter,
        ],
        context_class=dict,
        logger_factory=structlog.stdlib.LoggerFactory(),
        cache_logger_on_first_use=True,
    )

    logging.basicConfig(
        format="%(message)s",
        level=level_int,
        handlers=[
            logging.StreamHandler(),
        ],
    )

    # Configure root logger
    root_logger = logging.getLogger()
    root_logger.setLevel(level_int)


def get_logger(name: str) -> structlog.BoundLogger:
    """Get a bound logger instance."""
    return structlog.get_logger(name)


class LoggingMiddleware:
    """ASGI middleware for request/response logging."""

    def __init__(self, app, logger: structlog.BoundLogger = None):
        self.app = app
        self.logger = logger or get_logger("http")

    async def __call__(self, scope, receive, send):
        if scope["type"] != "http":
            await self.app(scope, receive, send)
            return

        request_id = self._extract_request_id(scope)
        method = scope.get("method", "")
        path = scope.get("path", "")

        self.logger.msg(
            "request_started",
            request_id=request_id,
            method=method,
            path=path,
            client=scope.get("client"),
        )

        async def send_wrapper(message):
            if message["type"] == "http.response.start":
                status_code = message.get("status", 0)
                self.logger.msg(
                    "request_completed",
                    request_id=request_id,
                    method=method,
                    path=path,
                    status_code=status_code,
                )
            await send(message)

        await self.app(scope, receive, send_wrapper)

    @staticmethod
    def _extract_request_id(scope: dict[str, Any]) -> str:
        """Extract request ID from headers or generate one."""
        headers = scope.get("headers", [])
        for header_name, header_value in headers:
            if header_name.lower() == b"x-request-id":
                return header_value.decode()
        # Generate a simple request ID if not provided
        import uuid
        return str(uuid.uuid4())
