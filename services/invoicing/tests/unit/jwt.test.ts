import { describe, it, expect, beforeEach } from 'vitest';
import { generateToken, verifyToken, decodeToken } from '../../src/utils/jwt';
import { JwtPayload } from '../../src/utils/jwt';

describe('JWT Utilities', () => {
  let testPayload: JwtPayload;

  beforeEach(() => {
    testPayload = {
      userId: 'test-user-123',
      companyId: 'test-company-123',
      email: 'test@example.com',
    };
  });

  describe('generateToken', () => {
    it('should generate a valid JWT token', () => {
      const token = generateToken(testPayload);
      expect(typeof token).toBe('string');
      expect(token.split('.').length).toBe(3); // JWT has 3 parts
    });

    it('should include payload in token', () => {
      const token = generateToken(testPayload);
      const decoded = decodeToken(token);
      // Use toMatchObject because JWT adds iat/exp claims automatically
      expect(decoded).toMatchObject(testPayload);
    });
  });

  describe('verifyToken', () => {
    it('should verify a valid token', () => {
      const token = generateToken(testPayload);
      const verified = verifyToken(token);
      expect(verified.userId).toBe(testPayload.userId);
      expect(verified.companyId).toBe(testPayload.companyId);
    });

    it('should throw error for invalid token', () => {
      expect(() => verifyToken('invalid-token')).toThrow();
    });

    it('should throw error for tampered token', () => {
      const token = generateToken(testPayload);
      const tampered = token.slice(0, -5) + 'wrong';
      expect(() => verifyToken(tampered)).toThrow();
    });
  });

  describe('decodeToken', () => {
    it('should decode a valid token without verification', () => {
      const token = generateToken(testPayload);
      const decoded = decodeToken(token);
      expect(decoded?.userId).toBe(testPayload.userId);
    });

    it('should return null for invalid token', () => {
      const decoded = decodeToken('invalid-token');
      expect(decoded).toBeNull();
    });
  });
});
