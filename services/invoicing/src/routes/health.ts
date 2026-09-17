import { Router } from 'express';
import sequelize from '../config/database.js';
import logger from '../utils/logger.js';

const router = Router();

router.get('/health', async (req, res) => {
  try {
    // Check database connection
    await sequelize.authenticate();

    res.status(200).json({
      status: 'ok',
      service: process.env.SERVICE_NAME || 'invoicing-microservice',
      timestamp: new Date().toISOString(),
      uptime: process.uptime(),
      database: 'connected',
    });
  } catch (error) {
    logger.error({ error }, 'Health check failed');
    res.status(503).json({
      status: 'error',
      service: process.env.SERVICE_NAME || 'invoicing-microservice',
      timestamp: new Date().toISOString(),
      database: 'disconnected',
    });
  }
});

router.get('/ready', async (req, res) => {
  try {
    await sequelize.authenticate();
    res.status(200).json({
      status: 'ready',
      timestamp: new Date().toISOString(),
    });
  } catch (error) {
    logger.error({ error }, 'Readiness check failed');
    res.status(503).json({
      status: 'not_ready',
      reason: 'database_connection_failed',
    });
  }
});

export default router;
