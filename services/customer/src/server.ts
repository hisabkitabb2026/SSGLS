import dotenv from 'dotenv';
import { createApp } from './app';
import { database, initializeDatabase } from './config/database';
import { messageBroker } from './config/rabbitmq';
import { logger } from './utils/logger';

// Load environment variables
dotenv.config();

const PORT = parseInt(process.env.PORT || '8004', 10);
const NODE_ENV = process.env.NODE_ENV || 'development';

async function bootstrap(): Promise<void> {
  try {
    logger.info(`Starting ${process.env.SERVICE_NAME || 'customer-service'} in ${NODE_ENV} mode`);

    // Initialize database
    logger.info('Initializing database...');
    await initializeDatabase();

    // Connect to RabbitMQ
    logger.info('Connecting to RabbitMQ...');
    await messageBroker.connect();

    // Create Express app
    const app = createApp();

    // Start server
    const server = app.listen(PORT, () => {
      logger.info({ port: PORT }, 'Server listening');
    });

    // Graceful shutdown
    const gracefulShutdown = async (signal: string) => {
      logger.info(`Received ${signal}, starting graceful shutdown...`);

      server.close(async () => {
        logger.info('HTTP server closed');

        try {
          await messageBroker.disconnect();
          logger.info('RabbitMQ disconnected');
        } catch (error) {
          logger.error({ error }, 'Error disconnecting from RabbitMQ');
        }

        try {
          await database.close();
          logger.info('Database closed');
        } catch (error) {
          logger.error({ error }, 'Error closing database');
        }

        process.exit(0);
      });

      // Force shutdown after 10 seconds
      setTimeout(() => {
        logger.error('Forced shutdown due to timeout');
        process.exit(1);
      }, 10000);
    };

    process.on('SIGTERM', () => gracefulShutdown('SIGTERM'));
    process.on('SIGINT', () => gracefulShutdown('SIGINT'));

    process.on('unhandledRejection', (reason: any) => {
      logger.error({ reason }, 'Unhandled rejection');
      process.exit(1);
    });

    process.on('uncaughtException', (error: Error) => {
      logger.error({ error }, 'Uncaught exception');
      process.exit(1);
    });
  } catch (error) {
    logger.error({ error }, 'Failed to bootstrap application');
    process.exit(1);
  }
}

bootstrap();
