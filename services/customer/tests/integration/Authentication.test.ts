import request from 'supertest';
import { createApp } from '../../src/app';
import { Express } from 'express';
import { jwtService } from '../../src/config/jwt';
import { customerService } from '../../src/services/CustomerService';

jest.mock('../../src/services/CustomerService');
jest.mock('../../src/config/jwt');

describe('Authentication Integration Tests', () => {
  let app: Express;
  const mockCompanyId = 1;
  const mockUserId = 123;

  const mockPayload = {
    userId: mockUserId,
    companyId: mockCompanyId,
    email: 'user@example.com',
    role: 'owner',
  };

  beforeEach(() => {
    app = createApp();
    jest.clearAllMocks();
  });

  describe('JWT Authentication', () => {
    it('should reject requests without authorization header', async () => {
      const response = await request(app)
        .get('/api/v1/customers')
        .set('X-Company-ID', mockCompanyId.toString());

      expect(response.status).toBe(401);
      expect(response.body.error).toMatch(/authorization/i);
    });

    it('should reject requests with invalid token format', async () => {
      const response = await request(app)
        .get('/api/v1/customers')
        .set('Authorization', 'InvalidFormat token')
        .set('X-Company-ID', mockCompanyId.toString());

      expect(response.status).toBe(401);
    });

    it('should reject requests with invalid JWT token', async () => {
      (jwtService.verifyToken as jest.Mock).mockImplementation(() => {
        throw new Error('Invalid token');
      });

      const response = await request(app)
        .get('/api/v1/customers')
        .set('Authorization', 'Bearer invalid.jwt.token')
        .set('X-Company-ID', mockCompanyId.toString());

      expect(response.status).toBe(401);
    });

    it('should accept valid JWT token', async () => {
      (jwtService.verifyToken as jest.Mock).mockReturnValue(mockPayload);
      (customerService.listCustomers as jest.Mock).mockResolvedValue({
        data: [],
        total: 0,
      });

      const response = await request(app)
        .get('/api/v1/customers')
        .set('Authorization', 'Bearer valid.jwt.token')
        .set('X-Company-ID', mockCompanyId.toString());

      expect(response.status).toBe(200);
      expect(jwtService.verifyToken).toHaveBeenCalled();
    });

    it('should extract Bearer token correctly', async () => {
      (jwtService.verifyToken as jest.Mock).mockReturnValue(mockPayload);
      (customerService.listCustomers as jest.Mock).mockResolvedValue({
        data: [],
        total: 0,
      });

      await request(app)
        .get('/api/v1/customers')
        .set('Authorization', 'Bearer my.awesome.token')
        .set('X-Company-ID', mockCompanyId.toString());

      expect(jwtService.verifyToken).toHaveBeenCalledWith('my.awesome.token');
    });
  });

  describe('Company ID Validation', () => {
    it('should require X-Company-ID header', async () => {
      (jwtService.verifyToken as jest.Mock).mockReturnValue(mockPayload);

      const response = await request(app)
        .get('/api/v1/customers')
        .set('Authorization', 'Bearer valid.token');

      expect(response.status).toBe(400);
      expect(response.body.error).toMatch(/Company-ID/i);
    });

    it('should validate company ID format', async () => {
      (jwtService.verifyToken as jest.Mock).mockReturnValue(mockPayload);

      const response = await request(app)
        .get('/api/v1/customers')
        .set('Authorization', 'Bearer valid.token')
        .set('X-Company-ID', 'not-a-number');

      expect(response.status).toBe(400);
      expect(response.body.error).toMatch(/Invalid company ID/i);
    });

    it('should enforce company ID isolation', async () => {
      const otherCompanyId = 999;
      const payloadWithDifferentCompany = {
        ...mockPayload,
        companyId: otherCompanyId,
      };

      (jwtService.verifyToken as jest.Mock).mockReturnValue(payloadWithDifferentCompany);

      const response = await request(app)
        .get('/api/v1/customers')
        .set('Authorization', 'Bearer valid.token')
        .set('X-Company-ID', mockCompanyId.toString());

      expect(response.status).toBe(403);
      expect(response.body.error).toMatch(/forbidden|mismatch/i);
    });

    it('should allow matching company IDs', async () => {
      (jwtService.verifyToken as jest.Mock).mockReturnValue(mockPayload);
      (customerService.listCustomers as jest.Mock).mockResolvedValue({
        data: [],
        total: 0,
      });

      const response = await request(app)
        .get('/api/v1/customers')
        .set('Authorization', 'Bearer valid.token')
        .set('X-Company-ID', mockCompanyId.toString());

      expect(response.status).toBe(200);
    });
  });

  describe('Role-Based Access Control', () => {
    it('should allow owner to delete customer', async () => {
      (jwtService.verifyToken as jest.Mock).mockReturnValue({
        ...mockPayload,
        role: 'owner',
      });
      (customerService.deleteCustomer as jest.Mock).mockResolvedValue(undefined);

      const response = await request(app)
        .delete('/api/v1/customers/uuid-1')
        .set('Authorization', 'Bearer valid.token')
        .set('X-Company-ID', mockCompanyId.toString());

      expect(response.status).toBe(200);
    });

    it('should deny admin from deleting customer', async () => {
      (jwtService.verifyToken as jest.Mock).mockReturnValue({
        ...mockPayload,
        role: 'admin',
      });

      const response = await request(app)
        .delete('/api/v1/customers/uuid-1')
        .set('Authorization', 'Bearer valid.token')
        .set('X-Company-ID', mockCompanyId.toString());

      expect(response.status).toBe(403);
      expect(response.body.error).toMatch(/permission/i);
    });

    it('should allow admin to create customer', async () => {
      (jwtService.verifyToken as jest.Mock).mockReturnValue({
        ...mockPayload,
        role: 'admin',
      });
      (customerService.createCustomer as jest.Mock).mockResolvedValue({
        id: 'uuid-1',
        companyId: mockCompanyId,
        email: 'test@example.com',
        name: 'Test',
        status: 'active',
        currencyCode: 'USD',
        createdAt: new Date(),
        updatedAt: new Date(),
      });

      const response = await request(app)
        .post('/api/v1/customers')
        .set('Authorization', 'Bearer valid.token')
        .set('X-Company-ID', mockCompanyId.toString())
        .send({
          email: 'test@example.com',
          name: 'Test',
        });

      expect(response.status).toBe(201);
    });

    it('should deny viewer from creating customer', async () => {
      (jwtService.verifyToken as jest.Mock).mockReturnValue({
        ...mockPayload,
        role: 'viewer',
      });

      const response = await request(app)
        .post('/api/v1/customers')
        .set('Authorization', 'Bearer valid.token')
        .set('X-Company-ID', mockCompanyId.toString())
        .send({
          email: 'test@example.com',
          name: 'Test',
        });

      expect(response.status).toBe(403);
    });
  });

  describe('Request Context Population', () => {
    it('should populate user in request context', async () => {
      (jwtService.verifyToken as jest.Mock).mockReturnValue(mockPayload);
      (customerService.listCustomers as jest.Mock).mockImplementation((companyId) => {
        expect(companyId).toBe(mockCompanyId);
        return Promise.resolve({ data: [], total: 0 });
      });

      await request(app)
        .get('/api/v1/customers')
        .set('Authorization', 'Bearer valid.token')
        .set('X-Company-ID', mockCompanyId.toString());

      expect(customerService.listCustomers).toHaveBeenCalled();
    });

    it('should populate company ID in request context', async () => {
      (jwtService.verifyToken as jest.Mock).mockReturnValue(mockPayload);
      (customerService.listCustomers as jest.Mock).mockImplementation((companyId) => {
        expect(companyId).toBe(2);
        return Promise.resolve({ data: [], total: 0 });
      });

      await request(app)
        .get('/api/v1/customers')
        .set('Authorization', 'Bearer valid.token')
        .set('X-Company-ID', '2');

      expect(customerService.listCustomers).toHaveBeenCalledWith(2, expect.any(Object));
    });
  });
});
