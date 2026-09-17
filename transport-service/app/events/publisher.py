"""RabbitMQ event publisher."""
import pika
import json
from typing import Dict, Any, Optional
from config.settings import settings
from config.logging_config import logger


class EventPublisher:
    """Publish events to RabbitMQ."""

    def __init__(self):
        """Initialize RabbitMQ publisher."""
        self.connection: Optional[pika.BlockingConnection] = None
        self.channel: Optional[pika.channel.Channel] = None

    def connect(self):
        """Establish connection to RabbitMQ."""
        try:
            credentials = pika.PlainCredentials(
                settings.RABBITMQ_USER,
                settings.RABBITMQ_PASSWORD
            )
            parameters = pika.ConnectionParameters(
                host=settings.RABBITMQ_HOST,
                port=settings.RABBITMQ_PORT,
                virtual_host=settings.RABBITMQ_VHOST,
                credentials=credentials,
                heartbeat=600,
                blocked_connection_timeout=300,
            )
            self.connection = pika.BlockingConnection(parameters)
            self.channel = self.connection.channel()

            # Declare exchange and queue
            self.channel.exchange_declare(
                exchange=settings.RABBITMQ_EXCHANGE,
                exchange_type='direct',
                durable=True
            )
            self.channel.queue_declare(
                queue=settings.RABBITMQ_QUEUE,
                durable=True
            )
            self.channel.queue_bind(
                exchange=settings.RABBITMQ_EXCHANGE,
                queue=settings.RABBITMQ_QUEUE,
                routing_key='transport.*'
            )

            logger.info("Connected to RabbitMQ")
        except Exception as e:
            logger.error(f"Failed to connect to RabbitMQ: {str(e)}")
            raise

    def disconnect(self):
        """Close connection to RabbitMQ."""
        try:
            if self.connection and not self.connection.is_closed:
                self.connection.close()
            logger.info("Disconnected from RabbitMQ")
        except Exception as e:
            logger.error(f"Error closing RabbitMQ connection: {str(e)}")

    def publish(
        self,
        event_type: str,
        data: Dict[str, Any],
        routing_key: Optional[str] = None
    ) -> bool:
        """
        Publish event to RabbitMQ.

        Args:
            event_type: Type of event (e.g., 'transport.created')
            data: Event data dictionary
            routing_key: Optional custom routing key

        Returns:
            True if published successfully, False otherwise
        """
        try:
            if not self.channel or self.connection.is_closed:
                self.connect()

            # Default routing key based on event type
            if not routing_key:
                routing_key = f"transport.{event_type}"

            # Create message payload
            payload = {
                "event_type": event_type,
                "data": data
            }

            # Publish message
            self.channel.basic_publish(
                exchange=settings.RABBITMQ_EXCHANGE,
                routing_key=routing_key,
                body=json.dumps(payload),
                properties=pika.BasicProperties(
                    content_type='application/json',
                    delivery_mode=pika.spec.PERSISTENT_DELIVERY_MODE
                )
            )

            logger.info(f"Published event: {event_type} with routing_key: {routing_key}")
            return True

        except Exception as e:
            logger.error(f"Failed to publish event {event_type}: {str(e)}")
            return False

    def publish_created(self, transport_id: int, company_id: int, data: Dict[str, Any]) -> bool:
        """Publish transport created event."""
        return self.publish(
            "created",
            {"transport_id": transport_id, "company_id": company_id, **data}
        )

    def publish_updated(self, transport_id: int, company_id: int, data: Dict[str, Any]) -> bool:
        """Publish transport updated event."""
        return self.publish(
            "updated",
            {"transport_id": transport_id, "company_id": company_id, **data}
        )

    def publish_status_changed(
        self,
        transport_id: int,
        company_id: int,
        old_status: str,
        new_status: str
    ) -> bool:
        """Publish transport status changed event."""
        return self.publish(
            "status_changed",
            {
                "transport_id": transport_id,
                "company_id": company_id,
                "old_status": old_status,
                "new_status": new_status
            }
        )

    def publish_deleted(self, transport_id: int, company_id: int) -> bool:
        """Publish transport deleted event."""
        return self.publish(
            "deleted",
            {"transport_id": transport_id, "company_id": company_id}
        )


# Global publisher instance
publisher = EventPublisher()
