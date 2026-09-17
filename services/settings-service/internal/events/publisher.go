package events

import (
	"encoding/json"
	"fmt"

	"github.com/invoiceshelf/settings-service/internal/config"
	amqp "github.com/rabbitmq/amqp091-go"
)

const (
	ExchangeName = "settings.events"
	ExchangeType = "topic"
)

// Publisher handles RabbitMQ event publishing
type Publisher struct {
	conn    *amqp.Connection
	channel *amqp.Channel
}

// NewPublisher creates a new RabbitMQ publisher
func NewPublisher(cfg *config.Config) (*Publisher, error) {
	// Connect to RabbitMQ
	conn, err := amqp.Dial(cfg.RabbitMQURL)
	if err != nil {
		return nil, fmt.Errorf("failed to connect to RabbitMQ: %w", err)
	}

	// Open channel
	ch, err := conn.Channel()
	if err != nil {
		conn.Close()
		return nil, fmt.Errorf("failed to open RabbitMQ channel: %w", err)
	}

	// Declare exchange
	err = ch.ExchangeDeclare(
		ExchangeName,  // name
		ExchangeType,  // type
		true,          // durable
		false,         // auto-deleted
		false,         // internal
		false,         // no-wait
		nil,           // arguments
	)
	if err != nil {
		ch.Close()
		conn.Close()
		return nil, fmt.Errorf("failed to declare exchange: %w", err)
	}

	return &Publisher{
		conn:    conn,
		channel: ch,
	}, nil
}

// Publish publishes an event to RabbitMQ
func (p *Publisher) Publish(eventType string, data interface{}) error {
	// Marshal event data to JSON
	body, err := json.Marshal(data)
	if err != nil {
		return fmt.Errorf("failed to marshal event data: %w", err)
	}

	// Publish message
	err = p.channel.Publish(
		ExchangeName, // exchange
		eventType,    // routing key
		false,        // mandatory
		false,        // immediate
		amqp.Publishing{
			ContentType:  "application/json",
			Body:         body,
			DeliveryMode: amqp.Persistent,
		},
	)

	if err != nil {
		return fmt.Errorf("failed to publish event: %w", err)
	}

	return nil
}

// Close closes the RabbitMQ connection
func (p *Publisher) Close() error {
	if p.channel != nil {
		p.channel.Close()
	}
	if p.conn != nil {
		return p.conn.Close()
	}
	return nil
}

// PublishBatch publishes multiple events
func (p *Publisher) PublishBatch(events map[string]interface{}) error {
	for eventType, data := range events {
		if err := p.Publish(eventType, data); err != nil {
			return err
		}
	}
	return nil
}
