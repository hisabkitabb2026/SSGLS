import { Router } from 'express';
import { register, Counter, Histogram, Gauge } from 'prom-client';
import logger from '../utils/logger.js';

const router = Router();

// Define metrics
export const httpRequestDuration = new Histogram({
  name: 'http_request_duration_seconds',
  help: 'Duration of HTTP requests in seconds',
  labelNames: ['method', 'route', 'status'],
  buckets: [0.1, 0.5, 1, 2, 5],
});

export const httpRequestTotal = new Counter({
  name: 'http_requests_total',
  help: 'Total number of HTTP requests',
  labelNames: ['method', 'route', 'status'],
});

export const invoiceCreated = new Counter({
  name: 'invoices_created_total',
  help: 'Total number of invoices created',
  labelNames: ['company_id'],
});

export const invoiceDeleted = new Counter({
  name: 'invoices_deleted_total',
  help: 'Total number of invoices deleted',
  labelNames: ['company_id'],
});

export const dbConnections = new Gauge({
  name: 'db_connections_active',
  help: 'Number of active database connections',
});

export const eventsPublished = new Counter({
  name: 'events_published_total',
  help: 'Total number of events published',
  labelNames: ['event_type', 'company_id'],
});

// Metrics endpoint
router.get('/metrics', async (req, res) => {
  try {
    const metrics = await register.metrics();
    res.set('Content-Type', register.contentType);
    res.end(metrics);
  } catch (error) {
    logger.error({ error }, 'Error generating metrics');
    res.status(500).json({
      status: 'error',
      message: 'Internal server error',
    });
  }
});

export default router;
