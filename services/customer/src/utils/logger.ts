import pino from 'pino';

const logLevel = process.env.LOG_LEVEL || 'info';
const isDevelopment = process.env.NODE_ENV === 'development';

export const logger = pino({
  level: logLevel,
  transport: isDevelopment
    ? {
        target: 'pino-pretty',
        options: {
          colorize: true,
          translateTime: 'SYS:standard',
          ignore: 'pid,hostname',
        },
      }
    : undefined,
  serializers: {
    req: (req: any) => ({
      method: req.method,
      url: req.url,
      headers: req.headers,
      remoteAddress: req.remoteAddress,
      remotePort: req.remotePort,
    }),
    res: (res: any) => ({
      statusCode: res.statusCode,
      headers: res.getHeaders(),
    }),
  },
  timestamp: pino.stdTimeFunctions.isoTime,
  formatters: {
    level: (label) => {
      return { level: label.toUpperCase() };
    },
    bindings: (bindings) => {
      return {
        service: process.env.SERVICE_NAME || 'customer-service',
        ...bindings,
      };
    },
  },
});

export const childLogger = (metadata: Record<string, any>) =>
  logger.child(metadata);
