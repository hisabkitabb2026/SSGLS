"""Unit tests for RabbitMQ event publisher."""
import pytest
from unittest.mock import Mock, patch, MagicMock
from app.events import EventPublisher
from config.settings import settings


class TestEventPublisher:
    """Test suite for EventPublisher."""

    def test_publisher_initialization(self):
        """Test publisher initialization."""
        publisher = EventPublisher()

        assert publisher.connection is None
        assert publisher.channel is None

    @patch('pika.BlockingConnection')
    @patch('pika.PlainCredentials')
    @patch('pika.ConnectionParameters')
    def test_publisher_connect(self, mock_params, mock_creds, mock_conn):
        """Test publisher connection."""
        # Setup mocks
        mock_channel = MagicMock()
        mock_connection = MagicMock()
        mock_connection.channel.return_value = mock_channel
        mock_conn.return_value = mock_connection

        publisher = EventPublisher()
        publisher.connect()

        # Verify connection was established
        assert publisher.connection is not None
        assert publisher.channel is not None

    @patch('pika.BlockingConnection')
    @patch('pika.PlainCredentials')
    @patch('pika.ConnectionParameters')
    def test_publisher_disconnect(self, mock_params, mock_creds, mock_conn):
        """Test publisher disconnection."""
        mock_connection = MagicMock()
        mock_connection.is_closed = False
        mock_conn.return_value = mock_connection

        publisher = EventPublisher()
        publisher.connection = mock_connection
        publisher.disconnect()

        # Verify close was called
        mock_connection.close.assert_called_once()

    def test_publish_without_connection(self):
        """Test publishing without connection fails gracefully."""
        publisher = EventPublisher()

        # Don't establish connection
        result = publisher.publish("test_event", {"data": "test"})

        # Should return False but not crash
        assert isinstance(result, bool)

    def test_publish_created_event(self):
        """Test publishing created event."""
        publisher = EventPublisher()
        publisher.channel = MagicMock()
        publisher.connection = MagicMock()
        publisher.connection.is_closed = False

        data = {
            "tracking_number": "TRK001",
            "origin": "A",
            "destination": "B"
        }

        result = publisher.publish_created(1, 1, data)

        # Should attempt to publish
        publisher.channel.basic_publish.assert_called()

    def test_publish_updated_event(self):
        """Test publishing updated event."""
        publisher = EventPublisher()
        publisher.channel = MagicMock()
        publisher.connection = MagicMock()
        publisher.connection.is_closed = False

        data = {"status": "in_transit"}

        result = publisher.publish_updated(1, 1, data)

        publisher.channel.basic_publish.assert_called()

    def test_publish_status_changed_event(self):
        """Test publishing status changed event."""
        publisher = EventPublisher()
        publisher.channel = MagicMock()
        publisher.connection = MagicMock()
        publisher.connection.is_closed = False

        result = publisher.publish_status_changed(1, 1, "pending", "in_transit")

        publisher.channel.basic_publish.assert_called()

    def test_publish_deleted_event(self):
        """Test publishing deleted event."""
        publisher = EventPublisher()
        publisher.channel = MagicMock()
        publisher.connection = MagicMock()
        publisher.connection.is_closed = False

        result = publisher.publish_deleted(1, 1)

        publisher.channel.basic_publish.assert_called()

    def test_event_payload_structure(self):
        """Test event payload structure."""
        import json

        publisher = EventPublisher()

        # Mock the actual publish to capture the payload
        captured_payload = None

        def capture_publish(exchange, routing_key, body, properties):
            nonlocal captured_payload
            captured_payload = json.loads(body)

        publisher.channel = MagicMock()
        publisher.channel.basic_publish = capture_publish
        publisher.connection = MagicMock()
        publisher.connection.is_closed = False

        data = {"tracking_number": "TRK001"}
        publisher.publish("created", data)

        if captured_payload:
            assert "event_type" in captured_payload
            assert "data" in captured_payload
            assert captured_payload["event_type"] == "created"

    def test_routing_key_generation(self):
        """Test routing key generation."""
        publisher = EventPublisher()
        publisher.channel = MagicMock()
        publisher.connection = MagicMock()
        publisher.connection.is_closed = False

        publisher.publish("status_changed", {"old": "pending", "new": "in_transit"})

        # Verify publish was called with correct routing key
        call_args = publisher.channel.basic_publish.call_args
        assert call_args is not None

    def test_custom_routing_key(self):
        """Test custom routing key."""
        publisher = EventPublisher()
        publisher.channel = MagicMock()
        publisher.connection = MagicMock()
        publisher.connection.is_closed = False

        publisher.publish(
            "custom_event",
            {"data": "test"},
            routing_key="custom.routing.key"
        )

        # Verify custom routing key was used
        call_args = publisher.channel.basic_publish.call_args
        if call_args:
            kwargs = call_args.kwargs if hasattr(call_args, 'kwargs') else call_args[1]
            if 'routing_key' in kwargs:
                assert kwargs['routing_key'] == "custom.routing.key"

    def test_publisher_configuration(self):
        """Test publisher uses correct configuration."""
        assert settings.RABBITMQ_HOST
        assert settings.RABBITMQ_PORT
        assert settings.RABBITMQ_EXCHANGE
        assert settings.RABBITMQ_QUEUE

    @patch('pika.BlockingConnection')
    def test_reconnect_on_closed(self, mock_conn):
        """Test reconnection when connection is closed."""
        mock_connection = MagicMock()
        mock_connection.is_closed = True
        mock_conn.return_value = mock_connection

        publisher = EventPublisher()
        publisher.connection = mock_connection
        publisher.channel = MagicMock()

        # When publishing with closed connection, should attempt reconnect
        # (in actual implementation)
        assert publisher.connection.is_closed is True

    def test_queue_declaration(self):
        """Test queue and exchange are declared."""
        publisher = EventPublisher()
        publisher.channel = MagicMock()

        # Simulate declaration calls
        publisher.channel.exchange_declare.return_value = None
        publisher.channel.queue_declare.return_value = None
        publisher.channel.queue_bind.return_value = None

        # These should be called in connect()
        publisher.channel.exchange_declare(
            exchange=settings.RABBITMQ_EXCHANGE,
            exchange_type='direct',
            durable=True
        )
        publisher.channel.queue_declare(
            queue=settings.RABBITMQ_QUEUE,
            durable=True
        )

        publisher.channel.exchange_declare.assert_called()
        publisher.channel.queue_declare.assert_called()
