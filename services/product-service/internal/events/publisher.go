package events

import (
	"context"
	"encoding/json"
	"fmt"

	amqp "github.com/rabbitmq/amqp091-go"
	"go.uber.org/zap"
)

const (
	ExchangeName = "invoiceshelf.products"
	ExchangeType = "topic"
)

const (
	EventProductCreated   = "product.created"
	EventProductUpdated   = "product.updated"
	EventProductDeleted   = "product.deleted"
	EventProductStockLow  = "product.stock.low"
)

type Event struct {
	EventType string                 `json:"event_type"`
	Timestamp int64                  `json:"timestamp"`
	CompanyID int64                  `json:"company_id"`
	Data      map[string]interface{} `json:"data"`
}

type Publisher struct {
	conn   *amqp.Connection
	logger *zap.Logger
}

func NewPublisher(conn *amqp.Connection, logger *zap.Logger) (*Publisher, error) {
	ch, err := conn.Channel()
	if err != nil {
		return nil, fmt.Errorf("failed to open channel: %w", err)
	}
	defer ch.Close()

	// Declare exchange
	err = ch.ExchangeDeclare(
		ExchangeName,
		ExchangeType,
		true,  // durable
		false, // auto-delete
		false, // internal
		false, // no-wait
		nil,   // arguments
	)
	if err != nil {
		return nil, fmt.Errorf("failed to declare exchange: %w", err)
	}

	return &Publisher{
		conn:   conn,
		logger: logger,
	}, nil
}

func (p *Publisher) Publish(ctx context.Context, eventType string, companyID int64, data map[string]interface{}) error {
	ch, err := p.conn.Channel()
	if err != nil {
		p.logger.Error("failed to open channel", zap.Error(err))
		return fmt.Errorf("failed to open channel: %w", err)
	}
	defer ch.Close()

	event := Event{
		EventType: eventType,
		Timestamp: getCurrentTimestamp(),
		CompanyID: companyID,
		Data:      data,
	}

	body, err := json.Marshal(event)
	if err != nil {
		p.logger.Error("failed to marshal event", zap.Error(err))
		return fmt.Errorf("failed to marshal event: %w", err)
	}

	routingKey := getRoutingKey(eventType)
	err = ch.PublishWithContext(
		ctx,
		ExchangeName, // exchange
		routingKey,   // routing key
		false,        // mandatory
		false,        // immediate
		amqp.Publishing{
			ContentType:  "application/json",
			Body:         body,
			DeliveryMode: amqp.Persistent,
		},
	)

	if err != nil {
		p.logger.Error("failed to publish event", zap.Error(err), zap.String("event_type", eventType))
		return fmt.Errorf("failed to publish event: %w", err)
	}

	p.logger.Info("event published", zap.String("event_type", eventType), zap.String("routing_key", routingKey))
	return nil
}

func (p *Publisher) Close() error {
	return p.conn.Close()
}

func getRoutingKey(eventType string) string {
	return fmt.Sprintf("products.%s", eventType)
}

func getCurrentTimestamp() int64 {
	return int64(0) // Will be set by the actual implementation
}
