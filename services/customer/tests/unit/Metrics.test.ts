import { metricsCollector } from '../../src/utils/metrics';

describe('Metrics Collector', () => {
  beforeEach(() => {
    metricsCollector.reset();
    jest.clearAllMocks();
  });

  describe('recordHttpRequest', () => {
    it('should record HTTP requests', () => {
      metricsCollector.recordHttpRequest('GET', '/customers', 200, 50);
      metricsCollector.recordHttpRequest('POST', '/customers', 201, 100);

      const metrics = metricsCollector.getMetrics();

      expect(metrics.http.requests).toBe(2);
      expect(metrics.http.errors).toBe(0);
    });

    it('should count error responses', () => {
      metricsCollector.recordHttpRequest('GET', '/customers', 200, 50);
      metricsCollector.recordHttpRequest('GET', '/customers/invalid', 404, 20);
      metricsCollector.recordHttpRequest('POST', '/customers', 500, 100);

      const metrics = metricsCollector.getMetrics();

      expect(metrics.http.requests).toBe(3);
      expect(metrics.http.errors).toBe(2);
    });

    it('should calculate average response time', () => {
      metricsCollector.recordHttpRequest('GET', '/customers', 200, 100);
      metricsCollector.recordHttpRequest('GET', '/customers', 200, 200);
      metricsCollector.recordHttpRequest('GET', '/customers', 200, 300);

      const metrics = metricsCollector.getMetrics();

      expect(metrics.http.avgResponseTime).toBe(200);
    });
  });

  describe('recordDatabaseQuery', () => {
    it('should record database queries', () => {
      metricsCollector.recordDatabaseQuery(50, true);
      metricsCollector.recordDatabaseQuery(100, true);

      const metrics = metricsCollector.getMetrics();

      expect(metrics.database.queries).toBe(2);
      expect(metrics.database.errors).toBe(0);
    });

    it('should count query errors', () => {
      metricsCollector.recordDatabaseQuery(50, true);
      metricsCollector.recordDatabaseQuery(100, false);
      metricsCollector.recordDatabaseQuery(75, true);

      const metrics = metricsCollector.getMetrics();

      expect(metrics.database.queries).toBe(3);
      expect(metrics.database.errors).toBe(1);
    });

    it('should calculate average query time', () => {
      metricsCollector.recordDatabaseQuery(50, true);
      metricsCollector.recordDatabaseQuery(100, true);
      metricsCollector.recordDatabaseQuery(150, true);

      const metrics = metricsCollector.getMetrics();

      expect(metrics.database.avgQueryTime).toBe(100);
    });
  });

  describe('recordEventPublished', () => {
    it('should record published events', () => {
      metricsCollector.recordEventPublished('customer.created');
      metricsCollector.recordEventPublished('customer.updated');

      const metrics = metricsCollector.getMetrics();

      expect(metrics.events.published).toBe(2);
    });
  });

  describe('getMetrics', () => {
    it('should return metrics object with all fields', () => {
      const metrics = metricsCollector.getMetrics();

      expect(metrics).toHaveProperty('timestamp');
      expect(metrics).toHaveProperty('uptime');
      expect(metrics).toHaveProperty('memory');
      expect(metrics).toHaveProperty('http');
      expect(metrics).toHaveProperty('database');
      expect(metrics).toHaveProperty('events');
    });

    it('should include memory usage', () => {
      const metrics = metricsCollector.getMetrics();

      expect(metrics.memory).toHaveProperty('heapUsed');
      expect(metrics.memory).toHaveProperty('heapTotal');
      expect(metrics.memory).toHaveProperty('external');
      expect(metrics.memory).toHaveProperty('rss');

      expect(metrics.memory.heapUsed).toBeGreaterThan(0);
      expect(metrics.memory.heapTotal).toBeGreaterThan(0);
    });

    it('should have valid timestamp', () => {
      const metrics = metricsCollector.getMetrics();

      expect(() => new Date(metrics.timestamp)).not.toThrow();
    });

    it('should calculate uptime correctly', () => {
      const metrics = metricsCollector.getMetrics();

      expect(metrics.uptime).toBeGreaterThanOrEqual(0);
      expect(typeof metrics.uptime).toBe('number');
    });

    it('should handle zero requests gracefully', () => {
      const metrics = metricsCollector.getMetrics();

      if (metrics.http.requests === 0) {
        expect(metrics.http.avgResponseTime).toBe(0);
      }

      if (metrics.database.queries === 0) {
        expect(metrics.database.avgQueryTime).toBe(0);
      }
    });
  });

  describe('concurrent metrics', () => {
    it('should accumulate metrics from multiple operations', () => {
      metricsCollector.recordHttpRequest('GET', '/customers', 200, 50);
      metricsCollector.recordDatabaseQuery(100, true);
      metricsCollector.recordEventPublished('customer.created');

      metricsCollector.recordHttpRequest('POST', '/customers', 201, 200);
      metricsCollector.recordDatabaseQuery(150, true);
      metricsCollector.recordEventPublished('customer.updated');

      const metrics = metricsCollector.getMetrics();

      expect(metrics.http.requests).toBe(2);
      expect(metrics.database.queries).toBe(2);
      expect(metrics.events.published).toBe(2);
    });
  });
});
