"""Structured logging configuration."""
import logging
import logging.config
import json
from pythonjsonlogger import jsonlogger
from config.settings import settings


class CustomJsonFormatter(jsonlogger.JsonFormatter):
    """Custom JSON formatter with additional fields."""

    def add_fields(self, log_record, record, message_dict):
        """Add custom fields to log record."""
        super(CustomJsonFormatter, self).add_fields(
            log_record, record, message_dict
        )
        log_record["app"] = settings.APP_NAME
        log_record["version"] = settings.APP_VERSION
        log_record["level"] = record.levelname


def setup_logging():
    """Configure structured JSON logging."""
    if settings.LOG_FORMAT == "json":
        handler = logging.StreamHandler()
        formatter = CustomJsonFormatter("%(timestamp)s %(level)s %(message)s")
        handler.setFormatter(formatter)

        root_logger = logging.getLogger()
        root_logger.handlers = [handler]
        root_logger.setLevel(settings.LOG_LEVEL)
    else:
        logging.basicConfig(
            level=settings.LOG_LEVEL,
            format="%(asctime)s - %(name)s - %(levelname)s - %(message)s"
        )


logger = logging.getLogger(__name__)
