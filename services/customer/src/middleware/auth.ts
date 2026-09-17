import { Request, Response, NextFunction } from 'express';
import { jwtService, JWTPayload } from '../config/jwt';
import { logger } from '../utils/logger';

declare global {
  namespace Express {
    interface Request {
      user?: JWTPayload;
      companyId?: number;
    }
  }
}

export const authenticateJWT = (req: Request, res: Response, next: NextFunction): void => {
  try {
    const authHeader = req.headers.authorization;

    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      logger.warn({ path: req.path }, 'Missing or invalid authorization header');
      res.status(401).json({ error: 'Missing or invalid authorization header' });
      return;
    }

    const token = authHeader.substring(7);

    try {
      const payload = jwtService.verifyToken(token);
      req.user = payload;
      req.companyId = payload.companyId;

      logger.debug({ userId: payload.userId, companyId: payload.companyId }, 'JWT authenticated');
      next();
    } catch (error) {
      logger.warn({ error: error instanceof Error ? error.message : 'Unknown error' }, 'Token verification failed');
      res.status(401).json({ error: 'Invalid or expired token' });
    }
  } catch (error) {
    logger.error({ error }, 'Authentication middleware error');
    res.status(500).json({ error: 'Internal server error' });
  }
};

export const validateCompanyId = (req: Request, res: Response, next: NextFunction): void => {
  try {
    const headerCompanyId = req.headers['x-company-id'];

    if (!headerCompanyId) {
      logger.warn({ path: req.path }, 'Missing company ID header');
      res.status(400).json({ error: 'Missing X-Company-ID header' });
      return;
    }

    const companyId = parseInt(headerCompanyId as string, 10);

    if (isNaN(companyId)) {
      logger.warn({ companyId: headerCompanyId }, 'Invalid company ID format');
      res.status(400).json({ error: 'Invalid company ID format' });
      return;
    }

    if (req.user && req.user.companyId !== companyId) {
      logger.warn(
        { userCompanyId: req.user.companyId, requestedCompanyId: companyId },
        'Company ID mismatch'
      );
      res.status(403).json({ error: 'Forbidden: company ID mismatch' });
      return;
    }

    req.companyId = companyId;
    next();
  } catch (error) {
    logger.error({ error }, 'Company ID validation middleware error');
    res.status(500).json({ error: 'Internal server error' });
  }
};

export const authorize = (allowedRoles: string[]) => {
  return (req: Request, res: Response, next: NextFunction): void => {
    try {
      if (!req.user) {
        res.status(401).json({ error: 'Unauthorized' });
        return;
      }

      if (!allowedRoles.includes(req.user.role)) {
        logger.warn(
          { userRole: req.user.role, allowedRoles, userId: req.user.userId },
          'Role authorization failed'
        );
        res.status(403).json({ error: 'Insufficient permissions' });
        return;
      }

      next();
    } catch (error) {
      logger.error({ error }, 'Authorization middleware error');
      res.status(500).json({ error: 'Internal server error' });
    }
  };
};
