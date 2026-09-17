import express, { Express, Request, Response } from 'express';
import cors from 'cors';
import helmet from 'helmet';
import pinoHttp from 'pino-http';
import { logger } from './utils/logger';
import { metricsCollector } from './utils/metrics';
import { errorHandler, asyncHandler } from './middleware/errorHandler';
import customerRoutes from './routes/customerRoutes';

export function createApp(): Express {
  const app = express();

  // Trust proxy
  app.set('trust proxy', 1);

  // Security middleware
  app.use(helmet());
  app.use(
    cors({
      origin: process.env.CORS_ORIGINS?.split(',') || ['*'],
      credentials: true,
    })
  );

  // Logging middleware
  app.use(
    pinoHttp({
      logger,
      customLogLevel: (req, res) => {
        if (res.statusCode >= 500) return 'error';
        if (res.statusCode >= 400) return 'warn';
        return 'info';
      },
    })
  );

  // Metrics middleware
  app.use((req: Request, res: Response, next: Function) => {
    const start = Date.now();

    res.on('finish', () => {
      const duration = Date.now() - start;
      metricsCollector.recordHttpRequest(req.method, req.path, res.statusCode, duration);
    });

    next();
  });

  // Body parsing
  app.use(express.json({ limit: '10mb' }));
  app.use(express.urlencoded({ limit: '10mb', extended: true }));

  // Health check endpoint
  app.get(
    '/health',
    asyncHandler(async (req: Request, res: Response) => {
      res.status(200).json({
        status: 'healthy',
        service: process.env.SERVICE_NAME || 'customer-service',
        timestamp: new Date().toISOString(),
      });
    })
  );

  // Ready check endpoint
  app.get(
    '/ready',
    asyncHandler(async (req: Request, res: Response) => {
      res.status(200).json({
        status: 'ready',
        service: process.env.SERVICE_NAME || 'customer-service',
      });
    })
  );

  // Metrics endpoint
  app.get(
    '/metrics',
    asyncHandler(async (req: Request, res: Response) => {
      const metrics = metricsCollector.getMetrics();
      res.status(200).json({
        success: true,
        data: metrics,
      });
    })
  );

  // API routes
  app.use('/api/v1', customerRoutes);

  // 404 handler
  app.use((req: Request, res: Response) => {
    logger.warn({ path: req.path, method: req.method }, 'Route not found');
    res.status(404).json({
      error: 'Route not found',
      path: req.path,
      method: req.method,
    });
  });

  // Error handler (must be last)
  app.use(errorHandler);

  return app;
}
