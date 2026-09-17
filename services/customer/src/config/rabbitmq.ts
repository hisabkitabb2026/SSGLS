import amqp from 'amqplib';
import { logger } from '../utils/logger';

export interface MessageBroker {
  connect(): Promise<void>;
  publishEvent(eventType: string, data: any): Promise<void>;
  disconnect(): Promise<void>;
  getChannel(): any | null;
}

class RabbitMQBroker implements MessageBroker {
  private connection: any = null;
  private channel: any = null;
  private isConnected = false;

  async connect(): Promise<void> {
    try {
      const url = `amqp://${process.env.RABBITMQ_USERNAME || 'guest'}:${process.env.RABBITMQ_PASSWORD || 'guest'}@${process.env.RABBITMQ_HOST || 'localhost'}:${process.env.RABBITMQ_PORT || 5672}${process.env.RABBITMQ_VHOST || '/'}`;

      this.connection = await amqp.connect(url);
      this.channel = await this.connection.createChannel();

      const exchange = process.env.RABBITMQ_EXCHANGE_CUSTOMERS || 'customers';
      const queue = process.env.RABBITMQ_QUEUE_CUSTOMER_EVENTS || 'customer.events';

      await this.channel.assertExchange(exchange, 'topic', { durable: true });
      await this.channel.assertQueue(queue, { durable: true });
      await this.channel.bindQueue(queue, exchange, 'customer.*');

      this.isConnected = true;
      logger.info('Connected to RabbitMQ');

      this.connection.on('close', () => {
        this.isConnected = false;
        logger.warn('RabbitMQ connection closed');
      });

      this.connection.on('error', (err: any) => {
        logger.error({ err }, 'RabbitMQ connection error');
        this.isConnected = false;
      });
    } catch (error) {
      logger.error({ error }, 'Failed to connect to RabbitMQ');
      throw error;
    }
  }

  async publishEvent(eventType: string, data: any): Promise<void> {
    if (!this.channel || !this.isConnected) {
      logger.warn('RabbitMQ channel not ready, skipping publish');
      return;
    }

    try {
      const exchange = process.env.RABBITMQ_EXCHANGE_CUSTOMERS || 'customers';
      const message = {
        eventType,
        timestamp: new Date().toISOString(),
        data,
      };

      const published = this.channel.publish(
        exchange,
        `customer.${eventType}`,
        Buffer.from(JSON.stringify(message)),
        { persistent: true }
      );

      if (published) {
        logger.debug({ eventType, data }, 'Event published to RabbitMQ');
      } else {
        logger.warn({ eventType }, 'Failed to publish event to RabbitMQ buffer');
      }
    } catch (error) {
      logger.error({ error, eventType }, 'Error publishing event to RabbitMQ');
      throw error;
    }
  }

  async disconnect(): Promise<void> {
    try {
      if (this.channel) {
        await this.channel.close();
      }
      if (this.connection) {
        await this.connection.close();
      }
      this.isConnected = false;
      logger.info('Disconnected from RabbitMQ');
    } catch (error) {
      logger.error({ error }, 'Error disconnecting from RabbitMQ');
      throw error;
    }
  }

  getChannel(): any | null {
    return this.channel;
  }
}

export const messageBroker = new RabbitMQBroker();
