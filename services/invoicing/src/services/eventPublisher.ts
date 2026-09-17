import amqp from 'amqplib';
import logger from '../utils/logger.js';

interface EventPayload {
  eventType: string;
  data: Record<string, unknown>;
  timestamp: Date;
  companyId: string;
}

class EventPublisher {
  private connection: amqp.Connection | null = null;
  private channel: amqp.Channel | null = null;
  private reconnectAttempts = 0;
  private maxReconnectAttempts = 5;
  private reconnectDelay = 5000;

  async connect(): Promise<void> {
    try {
      const url = process.env.RABBITMQ_URL || 'amqp://guest:guest@localhost:5672';
      this.connection = await amqp.connect(url);
      this.channel = await this.connection.createChannel();

      // Assert queues
      await this.channel.assertQueue(
        process.env.RABBITMQ_QUEUE_INVOICE_CREATED || 'invoice.created',
        { durable: true }
      );
      await this.channel.assertQueue(
        process.env.RABBITMQ_QUEUE_INVOICE_UPDATED || 'invoice.updated',
        { durable: true }
      );
      await this.channel.assertQueue(
        process.env.RABBITMQ_QUEUE_INVOICE_DELETED || 'invoice.deleted',
        { durable: true }
      );

      logger.info('Connected to RabbitMQ');
      this.reconnectAttempts = 0;

      // Handle connection errors
      this.connection.on('error', (err) => {
        logger.error({ error: err }, 'RabbitMQ connection error');
        this.reconnect();
      });

      this.connection.on('close', () => {
        logger.warn('RabbitMQ connection closed');
        this.reconnect();
      });
    } catch (error) {
      logger.error({ error }, 'Failed to connect to RabbitMQ');
      this.reconnect();
    }
  }

  private async reconnect(): Promise<void> {
    if (this.reconnectAttempts >= this.maxReconnectAttempts) {
      logger.error('Max reconnection attempts reached, giving up');
      return;
    }

    this.reconnectAttempts++;
    logger.info(
      { attempt: this.reconnectAttempts },
      'Attempting to reconnect to RabbitMQ'
    );

    setTimeout(() => {
      this.connect();
    }, this.reconnectDelay);
  }

  async publishEvent(
    eventType: string,
    data: Record<string, unknown>,
    companyId: string
  ): Promise<void> {
    try {
      if (!this.channel) {
        logger.warn('RabbitMQ channel not available, skipping event publication');
        return;
      }

      const queueName = this.getQueueName(eventType);
      const payload: EventPayload = {
        eventType,
        data,
        timestamp: new Date(),
        companyId,
      };

      await this.channel.assertQueue(queueName, { durable: true });
      const published = this.channel.sendToQueue(
        queueName,
        Buffer.from(JSON.stringify(payload)),
        { persistent: true }
      );

      if (published) {
        logger.info(
          {
            eventType,
            queueName,
            companyId,
          },
          'Event published'
        );
      } else {
        logger.warn(
          { eventType, queueName },
          'Failed to publish event, queue might be full'
        );
      }
    } catch (error) {
      logger.error(
        { error, eventType, companyId },
        'Error publishing event'
      );
    }
  }

  private getQueueName(eventType: string): string {
    switch (eventType) {
      case 'invoice.created':
        return process.env.RABBITMQ_QUEUE_INVOICE_CREATED || 'invoice.created';
      case 'invoice.updated':
        return process.env.RABBITMQ_QUEUE_INVOICE_UPDATED || 'invoice.updated';
      case 'invoice.deleted':
        return process.env.RABBITMQ_QUEUE_INVOICE_DELETED || 'invoice.deleted';
      default:
        return eventType;
    }
  }

  async close(): Promise<void> {
    try {
      if (this.channel) {
        await this.channel.close();
      }
      if (this.connection) {
        await this.connection.close();
      }
      logger.info('Disconnected from RabbitMQ');
    } catch (error) {
      logger.error({ error }, 'Error closing RabbitMQ connection');
    }
  }
}

export default new EventPublisher();
