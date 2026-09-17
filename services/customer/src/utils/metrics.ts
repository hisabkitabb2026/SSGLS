export interface MetricsCollector {
  recordHttpRequest(method: string, path: string, statusCode: number, duration: number): void;
  recordDatabaseQuery(duration: number, success: boolean): void;
  recordEventPublished(eventType: string): void;
  getMetrics(): Metrics;
  reset(): void;
}

export interface Metrics {
  timestamp: string;
  uptime: number;
  memory: {
    heapUsed: number;
    heapTotal: number;
    external: number;
    rss: number;
  };
  http: {
    requests: number;
    errors: number;
    avgResponseTime: number;
  };
  database: {
    queries: number;
    errors: number;
    avgQueryTime: number;
  };
  events: {
    published: number;
    failed: number;
  };
}

class MetricsCollectorImpl implements MetricsCollector {
  private httpRequests = 0;
  private httpErrors = 0;
  private httpDuration = 0;
  private dbQueries = 0;
  private dbErrors = 0;
  private dbDuration = 0;
  private eventsPublished = 0;
  private eventsFailed = 0;
  private startTime = Date.now();

  recordHttpRequest(method: string, path: string, statusCode: number, duration: number): void {
    this.httpRequests++;
    this.httpDuration += duration;
    if (statusCode >= 400) {
      this.httpErrors++;
    }
  }

  recordDatabaseQuery(duration: number, success: boolean): void {
    this.dbQueries++;
    this.dbDuration += duration;
    if (!success) {
      this.dbErrors++;
    }
  }

  recordEventPublished(eventType: string): void {
    this.eventsPublished++;
  }

  recordEventFailed(eventType: string): void {
    this.eventsFailed++;
  }

  reset(): void {
    this.httpRequests = 0;
    this.httpErrors = 0;
    this.httpDuration = 0;
    this.dbQueries = 0;
    this.dbErrors = 0;
    this.dbDuration = 0;
    this.eventsPublished = 0;
    this.eventsFailed = 0;
    this.startTime = Date.now();
  }

  getMetrics(): Metrics {
    const memUsage = process.memoryUsage();
    const uptime = Math.floor((Date.now() - this.startTime) / 1000);

    return {
      timestamp: new Date().toISOString(),
      uptime,
      memory: {
        heapUsed: Math.round(memUsage.heapUsed / 1024 / 1024 * 100) / 100,
        heapTotal: Math.round(memUsage.heapTotal / 1024 / 1024 * 100) / 100,
        external: Math.round(memUsage.external / 1024 / 1024 * 100) / 100,
        rss: Math.round(memUsage.rss / 1024 / 1024 * 100) / 100,
      },
      http: {
        requests: this.httpRequests,
        errors: this.httpErrors,
        avgResponseTime: this.httpRequests > 0 ? Math.round(this.httpDuration / this.httpRequests) : 0,
      },
      database: {
        queries: this.dbQueries,
        errors: this.dbErrors,
        avgQueryTime: this.dbQueries > 0 ? Math.round(this.dbDuration / this.dbQueries) : 0,
      },
      events: {
        published: this.eventsPublished,
        failed: this.eventsFailed,
      },
    };
  }
}

export const metricsCollector = new MetricsCollectorImpl();
