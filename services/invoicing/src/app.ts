import 'express-async-errors';
import express, { Request, Response, NextFunction } from 'express';
import helmet from 'helmet';
import cors from 'cors';
import sequelize from './config/database.js';
import logger from './utils/logger.js';
import { Company, Invoice } from './models/index.js';
import eventPublisher from './services/eventPublisher.js';
import invoicesRouter from './routes/invoices.js';
import healthRouter from './routes/health.js';
import metricsRouter, {
  httpRequestDuration,
  httpRequestTotal,
} from './routes/metrics.js';

const app = express();
const PORT = parseInt(process.env.PORT || '8001', 10);

// Middleware
app.use(helmet());
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Request logging and metrics middleware
app.use((req: Request, res: Response, next: NextFunction) => {
  const start = Date.now();

  res.on('finish', () => {
    const duration = (Date.now() - start) / 1000;
    httpRequestDuration
      .labels(req.method, req.route?.path || req.path, res.statusCode.toString())
      .observe(duration);
    httpRequestTotal
      .labels(req.method, req.route?.path || req.path, res.statusCode.toString())
      .inc();

    logger.info(
      {
        method: req.method,
        path: req.path,
        status: res.statusCode,
        duration,
      },
      'HTTP request'
    );
  });

  next();
});

// Routes
app.use('/api/v1/invoices', invoicesRouter);
app.use('/', healthRouter);
app.use('/', metricsRouter);

// 404 handler
app.use((req: Request, res: Response) => {
  res.status(404).json({
    status: 'error',
    message: 'Not found',
  });
});

// Error handler
app.use((err: Error, req: Request, res: Response, next: NextFunction) => {
  logger.error({ error: err }, 'Unhandled error');
  res.status(500).json({
    status: 'error',
    message: 'Internal server error',
    error: process.env.NODE_ENV === 'development' ? err.message : undefined,
  });
});

// Initialize database and start server
async function start() {
  try {
    // Connect to database
    await sequelize.authenticate();
    logger.info('Database connection established');

    // Sync database (use migrations in production)
    if (process.env.NODE_ENV !== 'production') {
      await sequelize.sync({ alter: true });
      logger.info('Database models synced');

      // Seed with demo company if needed
      const companyCount = await Company.count();
      if (companyCount === 0) {
        const company = await Company.create({
          name: 'Demo Company',
          email: 'demo@example.com',
          phone: '+1234567890',
          address: '123 Main St',
          city: 'San Francisco',
          state: 'CA',
          zipCode: '94105',
          country: 'US',
          taxId: 'TAX123456',
        });
        logger.info({ companyId: company.id }, 'Demo company created');
      }
    }

    // Connect to RabbitMQ
    await eventPublisher.connect();

    // Start server
    app.listen(PORT, () => {
      logger.info(
        {
          port: PORT,
          env: process.env.NODE_ENV || 'development',
        },
        'Server started'
      );
    });
  } catch (error) {
    logger.error({ error }, 'Failed to start server');
    process.exit(1);
  }
}

// Graceful shutdown
process.on('SIGTERM', async () => {
  logger.info('SIGTERM signal received, closing server');
  await eventPublisher.close();
  await sequelize.close();
  process.exit(0);
});

process.on('SIGINT', async () => {
  logger.info('SIGINT signal received, closing server');
  await eventPublisher.close();
  await sequelize.close();
  process.exit(0);
});

start();

export default app;
