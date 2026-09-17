import { describe, it, expect, beforeEach, vi } from 'vitest';
import { Request, Response, NextFunction } from 'express';
import { authMiddleware, companyMiddleware, AuthRequest } from '../../src/middleware/auth';
import * as jwt from '../../src/utils/jwt';

vi.mock('../../src/utils/logger');

describe('Auth Middleware', () => {
  let req: AuthRequest;
  let res: Response;
  let next: NextFunction;

  beforeEach(() => {
    req = {
      headers: {},
    } as AuthRequest;

    res = {
      status: vi.fn().mockReturnThis(),
      json: vi.fn(),
    } as unknown as Response;

    next = vi.fn();
  });

  describe('authMiddleware', () => {
    it('should return 401 if authorization header missing', () => {
      authMiddleware(req, res, next);

      expect(res.status).toHaveBeenCalledWith(401);
      expect(res.json).toHaveBeenCalledWith({
        status: 'error',
        message: 'Authorization header missing',
      });
      expect(next).not.toHaveBeenCalled();
    });

    it('should extract Bearer token correctly', () => {
      const token = 'test-token';
      req.headers.authorization = `Bearer ${token}`;

      vi.spyOn(jwt, 'verifyToken').mockReturnValue({
        userId: 'user-123',
        companyId: 'company-123',
      });

      authMiddleware(req, res, next);

      expect(jwt.verifyToken).toHaveBeenCalledWith(token);
      expect(req.user).toBeDefined();
      expect(next).toHaveBeenCalled();
    });

    it('should handle token without Bearer prefix', () => {
      const token = 'test-token';
      req.headers.authorization = token;

      vi.spyOn(jwt, 'verifyToken').mockReturnValue({
        userId: 'user-123',
        companyId: 'company-123',
      });

      authMiddleware(req, res, next);

      expect(jwt.verifyToken).toHaveBeenCalledWith(token);
    });

    it('should return 401 for invalid token', () => {
      req.headers.authorization = 'Bearer invalid-token';

      vi.spyOn(jwt, 'verifyToken').mockImplementation(() => {
        throw new Error('Invalid token');
      });

      authMiddleware(req, res, next);

      expect(res.status).toHaveBeenCalledWith(401);
      expect(res.json).toHaveBeenCalledWith({
        status: 'error',
        message: 'Invalid or expired token',
      });
    });

    it('should set user and companyId on request', () => {
      const payload = {
        userId: 'user-123',
        companyId: 'company-123',
        email: 'user@example.com',
      };

      req.headers.authorization = 'Bearer valid-token';
      req.path = '/api/invoices';

      vi.spyOn(jwt, 'verifyToken').mockReturnValue(payload);

      authMiddleware(req, res, next);

      expect(req.user).toEqual(payload);
      expect(req.companyId).toBe('company-123');
      expect(next).toHaveBeenCalled();
    });
  });

  describe('companyMiddleware', () => {
    beforeEach(() => {
      req.user = {
        userId: 'user-123',
        companyId: 'company-123',
      };
    });

    it('should return 400 if company-id header missing', () => {
      companyMiddleware(req, res, next);

      expect(res.status).toHaveBeenCalledWith(400);
      expect(res.json).toHaveBeenCalledWith({
        status: 'error',
        message: 'Company ID header missing',
      });
      expect(next).not.toHaveBeenCalled();
    });

    it('should return 403 if company-id does not match user company', () => {
      req.headers['company-id'] = 'other-company-456';

      companyMiddleware(req, res, next);

      expect(res.status).toHaveBeenCalledWith(403);
      expect(res.json).toHaveBeenCalledWith({
        status: 'error',
        message: 'Access denied: company ID mismatch',
      });
      expect(next).not.toHaveBeenCalled();
    });

    it('should allow matching company-id', () => {
      req.headers['company-id'] = 'company-123';

      companyMiddleware(req, res, next);

      expect(req.companyId).toBe('company-123');
      expect(next).toHaveBeenCalled();
    });

    it('should set companyId if no user context', () => {
      req.user = undefined;
      req.headers['company-id'] = 'company-123';

      companyMiddleware(req, res, next);

      expect(req.companyId).toBe('company-123');
      expect(next).toHaveBeenCalled();
    });
  });
});
