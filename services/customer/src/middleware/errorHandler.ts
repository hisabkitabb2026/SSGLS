import { Request, Response, NextFunction } from 'express';
import { logger } from '../utils/logger';

export class AppError extends Error {
  constructor(
    public statusCode: number,
    message: string,
    public isOperational = true
  ) {
    super(message);
    Object.setPrototypeOf(this, new.target.prototype);
  }
}

export const errorHandler = (
  error: Error | AppError,
  req: Request,
  res: Response,
  next: NextFunction
): void => {
  if (error instanceof AppError) {
    logger.warn(
      {
        statusCode: error.statusCode,
        message: error.message,
        path: req.path,
        method: req.method,
      },
      'Application error'
    );

    res.status(error.statusCode).json({
      error: error.message,
      statusCode: error.statusCode,
    });
    return;
  }

  logger.error(
    {
      error: error.message,
      stack: error.stack,
      path: req.path,
      method: req.method,
    },
    'Unhandled error'
  );

  res.status(500).json({
    error: 'Internal server error',
    statusCode: 500,
  });
};

export const asyncHandler = (fn: Function) => {
  return (req: Request, res: Response, next: NextFunction) => {
    Promise.resolve(fn(req, res, next)).catch(next);
  };
};
