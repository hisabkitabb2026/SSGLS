"""Prometheus metrics for monitoring."""
from prometheus_client import Counter, Histogram, Gauge
import time
from functools import wraps

# Request metrics
http_requests_total = Counter(
    "http_requests_total",
    "Total HTTP requests",
    ["method", "endpoint", "status_code"]
)

http_request_duration_seconds = Histogram(
    "http_request_duration_seconds",
    "HTTP request duration",
    ["method", "endpoint"]
)

# Transport metrics
transport_operations_total = Counter(
    "transport_operations_total",
    "Total transport operations",
    ["operation", "status"]
)

transport_by_status = Gauge(
    "transport_by_status",
    "Number of transports by status",
    ["company_id", "status"]
)

transport_total_cost = Gauge(
    "transport_total_cost",
    "Total transport cost",
    ["company_id"]
)

# Database metrics
database_connections = Gauge(
    "database_connections",
    "Number of active database connections"
)

# RabbitMQ metrics
rabbitmq_messages_published = Counter(
    "rabbitmq_messages_published",
    "Total RabbitMQ messages published",
    ["event_type", "status"]
)

rabbitmq_connection_errors = Counter(
    "rabbitmq_connection_errors",
    "RabbitMQ connection errors"
)


def track_request(method, endpoint):
    """Decorator to track HTTP request metrics."""
    def decorator(func):
        @wraps(func)
        async def async_wrapper(*args, **kwargs):
            start_time = time.time()
            try:
                result = await func(*args, **kwargs)
                duration = time.time() - start_time
                http_request_duration_seconds.labels(
                    method=method,
                    endpoint=endpoint
                ).observe(duration)
                return result
            except Exception as e:
                duration = time.time() - start_time
                http_request_duration_seconds.labels(
                    method=method,
                    endpoint=endpoint
                ).observe(duration)
                raise

        @wraps(func)
        def sync_wrapper(*args, **kwargs):
            start_time = time.time()
            try:
                result = func(*args, **kwargs)
                duration = time.time() - start_time
                http_request_duration_seconds.labels(
                    method=method,
                    endpoint=endpoint
                ).observe(duration)
                return result
            except Exception as e:
                duration = time.time() - start_time
                http_request_duration_seconds.labels(
                    method=method,
                    endpoint=endpoint
                ).observe(duration)
                raise

        if hasattr(func, "__name__"):
            # Async function
            if "async" in str(func):
                return async_wrapper
        return sync_wrapper

    return decorator
