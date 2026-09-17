import { Request, Response, NextFunction } from 'express';
import { verifyToken, JwtPayload } from '../utils/jwt.js';
import logger from '../utils/logger.js';

export interface AuthRequest extends Request {
  user?: JwtPayload;
  companyId?: string;
}

export const authMiddleware = (
  req: AuthRequest,
  res: Response,
  next: NextFunction
): void => {
  try {
    const authHeader = req.headers.authorization;

    if (!authHeader) {
      res.status(401).json({
        status: 'error',
        message: 'Authorization header missing',
      });
      return;
    }

    const token = authHeader.startsWith('Bearer ')
      ? authHeader.slice(7)
      : authHeader;

    const decoded = verifyToken(token);
    req.user = decoded;
    req.companyId = decoded.companyId;

    logger.info(
      {
        userId: decoded.userId,
        companyId: decoded.companyId,
        path: req.path,
      },
      'User authenticated'
    );

    next();
  } catch (error) {
    logger.warn({ error }, 'Authentication failed');
    res.status(401).json({
      status: 'error',
      message: 'Invalid or expired token',
    });
  }
};

export const companyMiddleware = (
  req: AuthRequest,
  res: Response,
  next: NextFunction
): void => {
  try {
    const companyId = req.headers['company-id'] as string;

    if (!companyId) {
      res.status(400).json({
        status: 'error',
        message: 'Company ID header missing',
      });
      return;
    }

    if (req.user && req.user.companyId !== companyId) {
      logger.warn(
        {
          userId: req.user.userId,
          requestedCompanyId: companyId,
          userCompanyId: req.user.companyId,
        },
        'Company ID mismatch'
      );
      res.status(403).json({
        status: 'error',
        message: 'Access denied: company ID mismatch',
      });
      return;
    }

    req.companyId = companyId;
    next();
  } catch (error) {
    logger.error({ error }, 'Company middleware error');
    res.status(500).json({
      status: 'error',
      message: 'Internal server error',
    });
  }
};
