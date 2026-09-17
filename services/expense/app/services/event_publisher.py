"""RabbitMQ event publishing service."""

import json
from datetime import datetime
from typing import Any, Optional

import pika
import pika.exceptions

from app.config import get_settings
from app.utils.logging import get_logger

logger = get_logger(__name__)


class EventPublisher:
    """Publish events to RabbitMQ."""

    def __init__(self):
        self.settings = get_settings()
        self.connection: Optional[pika.BlockingConnection] = None
        self.channel: Optional[pika.channel.Channel] = None

    def connect(self) -> bool:
        """Establish connection to RabbitMQ."""
        try:
            credentials = pika.PlainCredentials(
                self.settings.rabbitmq_user,
                self.settings.rabbitmq_password,
            )
            parameters = pika.ConnectionParameters(
                host=self.settings.rabbitmq_host,
                port=self.settings.rabbitmq_port,
                virtual_host=self.settings.rabbitmq_vhost,
                credentials=credentials,
                connection_attempts=3,
                retry_delay=2,
            )
            self.connection = pika.BlockingConnection(parameters)
            self.channel = self.connection.channel()

            # Declare exchange
            self.channel.exchange_declare(
                exchange=self.settings.rabbitmq_exchange_name,
                exchange_type="topic",
                durable=True,
            )

            logger.msg("rabbitmq_connected", host=self.settings.rabbitmq_host)
            return True

        except pika.exceptions.AMQPConnectionError as e:
            logger.msg("rabbitmq_connection_failed", error=str(e))
            return False
        except Exception as e:
            logger.msg("rabbitmq_connection_error", error=str(e))
            return False

    def disconnect(self):
        """Close RabbitMQ connection."""
        try:
            if self.connection and not self.connection.is_closed:
                self.connection.close()
                logger.msg("rabbitmq_disconnected")
        except Exception as e:
            logger.msg("rabbitmq_disconnect_error", error=str(e))

    def is_connected(self) -> bool:
        """Check if connected to RabbitMQ."""
        return (
            self.connection is not None
            and not self.connection.is_closed
            and self.channel is not None
        )

    def publish_event(
        self,
        event_type: str,
        data: dict[str, Any],
        routing_key: Optional[str] = None,
    ) -> bool:
        """Publish event to RabbitMQ."""
        if not self.is_connected():
            logger.msg("publish_failed_not_connected", event_type=event_type)
            return False

        try:
            if routing_key is None:
                routing_key = f"expense.{event_type.lower()}"

            message = {
                "event_type": event_type,
                "timestamp": datetime.utcnow().isoformat(),
                "data": data,
            }

            self.channel.basic_publish(
                exchange=self.settings.rabbitmq_exchange_name,
                routing_key=routing_key,
                body=json.dumps(message),
                properties=pika.BasicProperties(
                    content_type="application/json",
                    delivery_mode=pika.spec.PERSISTENT_DELIVERY_MODE,
                ),
            )

            logger.msg(
                "event_published",
                event_type=event_type,
                routing_key=routing_key,
            )
            return True

        except Exception as e:
            logger.msg(
                "event_publish_error",
                event_type=event_type,
                error=str(e),
            )
            return False

    def publish_expense_created(self, expense_id: int, company_id: int, user_id: int):
        """Publish expense.created event."""
        return self.publish_event(
            "expense.created",
            {
                "expense_id": expense_id,
                "company_id": company_id,
                "user_id": user_id,
            },
        )

    def publish_expense_updated(
        self,
        expense_id: int,
        company_id: int,
        changes: dict[str, Any],
    ):
        """Publish expense.updated event."""
        return self.publish_event(
            "expense.updated",
            {
                "expense_id": expense_id,
                "company_id": company_id,
                "changes": changes,
            },
        )

    def publish_expense_deleted(self, expense_id: int, company_id: int):
        """Publish expense.deleted event."""
        return self.publish_event(
            "expense.deleted",
            {
                "expense_id": expense_id,
                "company_id": company_id,
            },
        )

    def publish_expense_approved(
        self,
        expense_id: int,
        company_id: int,
        approver_id: int,
    ):
        """Publish expense.approved event."""
        return self.publish_event(
            "expense.approved",
            {
                "expense_id": expense_id,
                "company_id": company_id,
                "approver_id": approver_id,
            },
        )

    def publish_expense_rejected(
        self,
        expense_id: int,
        company_id: int,
        approver_id: int,
        reason: str,
    ):
        """Publish expense.rejected event."""
        return self.publish_event(
            "expense.rejected",
            {
                "expense_id": expense_id,
                "company_id": company_id,
                "approver_id": approver_id,
                "reason": reason,
            },
        )


# Global instance
_publisher: Optional[EventPublisher] = None


def get_event_publisher() -> EventPublisher:
    """Get or create event publisher instance."""
    global _publisher
    if _publisher is None:
        _publisher = EventPublisher()
    return _publisher
