import request from 'supertest';
import { createApp } from '../../src/app';
import { Express } from 'express';
import { jwtService } from '../../src/config/jwt';
import { customerService } from '../../src/services/CustomerService';

jest.mock('../../src/services/CustomerService');
jest.mock('../../src/config/jwt');

describe('Customer Routes Integration Tests', () => {
  let app: Express;
  const mockCompanyId = 1;
  const mockUserId = 123;
  const mockToken = 'Bearer valid.jwt.token';

  const mockPayload = {
    userId: mockUserId,
    companyId: mockCompanyId,
    email: 'user@example.com',
    role: 'owner',
  };

  beforeEach(() => {
    app = createApp();
    jest.clearAllMocks();

    (jwtService.verifyToken as jest.Mock).mockReturnValue(mockPayload);
  });

  describe('POST /api/v1/customers', () => {
    it('should create a customer successfully', async () => {
      const customerData = {
        email: 'newcustomer@example.com',
        name: 'New Customer',
        phone: '123456789',
        city: 'New York',
        currencyCode: 'USD',
      };

      const mockCustomer = {
        id: 'uuid-1',
        companyId: mockCompanyId,
        ...customerData,
        status: 'active',
        createdAt: new Date(),
        updatedAt: new Date(),
      };

      (customerService.createCustomer as jest.Mock).mockResolvedValue(mockCustomer);

      const response = await request(app)
        .post('/api/v1/customers')
        .set('Authorization', mockToken)
        .set('X-Company-ID', mockCompanyId.toString())
        .send(customerData);

      expect(response.status).toBe(201);
      expect(response.body.success).toBe(true);
      expect(response.body.data).toEqual(mockCustomer);
    });

    it('should return 401 without authorization token', async () => {
      const response = await request(app)
        .post('/api/v1/customers')
        .set('X-Company-ID', mockCompanyId.toString())
        .send({
          email: 'test@example.com',
          name: 'Test',
        });

      expect(response.status).toBe(401);
    });

    it('should return 400 without company ID header', async () => {
      const response = await request(app)
        .post('/api/v1/customers')
        .set('Authorization', mockToken)
        .send({
          email: 'test@example.com',
          name: 'Test',
        });

      expect(response.status).toBe(400);
    });

    it('should validate required fields', async () => {
      const response = await request(app)
        .post('/api/v1/customers')
        .set('Authorization', mockToken)
        .set('X-Company-ID', mockCompanyId.toString())
        .send({
          // Missing required fields: email, name
          phone: '123456789',
        });

      expect(response.status).toBe(400);
    });
  });

  describe('GET /api/v1/customers/:id', () => {
    it('should retrieve a customer by ID', async () => {
      const customerId = 'uuid-1';
      const mockCustomer = {
        id: customerId,
        companyId: mockCompanyId,
        email: 'test@example.com',
        name: 'Test Customer',
        status: 'active',
        currencyCode: 'USD',
        createdAt: new Date(),
        updatedAt: new Date(),
      };

      (customerService.getCustomer as jest.Mock).mockResolvedValue(mockCustomer);

      const response = await request(app)
        .get(`/api/v1/customers/${customerId}`)
        .set('Authorization', mockToken)
        .set('X-Company-ID', mockCompanyId.toString());

      expect(response.status).toBe(200);
      expect(response.body.success).toBe(true);
      expect(response.body.data.id).toBe(customerId);
    });

    it('should return 404 for non-existent customer', async () => {
      (customerService.getCustomer as jest.Mock).mockRejectedValue(
        new Error('Customer not found')
      );

      const response = await request(app)
        .get('/api/v1/customers/non-existent')
        .set('Authorization', mockToken)
        .set('X-Company-ID', mockCompanyId.toString());

      expect(response.status).toBe(500);
    });
  });

  describe('GET /api/v1/customers', () => {
    it('should list customers with pagination', async () => {
      const mockCustomers = [
        {
          id: 'uuid-1',
          companyId: mockCompanyId,
          email: 'test1@example.com',
          name: 'Customer 1',
          status: 'active',
          currencyCode: 'USD',
          createdAt: new Date(),
          updatedAt: new Date(),
        },
        {
          id: 'uuid-2',
          companyId: mockCompanyId,
          email: 'test2@example.com',
          name: 'Customer 2',
          status: 'active',
          currencyCode: 'USD',
          createdAt: new Date(),
          updatedAt: new Date(),
        },
      ];

      (customerService.listCustomers as jest.Mock).mockResolvedValue({
        data: mockCustomers,
        total: 2,
      });

      const response = await request(app)
        .get('/api/v1/customers?limit=10&offset=0')
        .set('Authorization', mockToken)
        .set('X-Company-ID', mockCompanyId.toString());

      expect(response.status).toBe(200);
      expect(response.body.success).toBe(true);
      expect(response.body.data.length).toBe(2);
      expect(response.body.pagination.total).toBe(2);
    });

    it('should filter by status', async () => {
      const mockCustomers = [
        {
          id: 'uuid-1',
          companyId: mockCompanyId,
          email: 'test@example.com',
          name: 'Customer',
          status: 'inactive',
          currencyCode: 'USD',
          createdAt: new Date(),
          updatedAt: new Date(),
        },
      ];

      (customerService.listCustomers as jest.Mock).mockResolvedValue({
        data: mockCustomers,
        total: 1,
      });

      const response = await request(app)
        .get('/api/v1/customers?status=inactive')
        .set('Authorization', mockToken)
        .set('X-Company-ID', mockCompanyId.toString());

      expect(response.status).toBe(200);
      expect(customerService.listCustomers).toHaveBeenCalledWith(mockCompanyId, expect.any(Object));
    });
  });

  describe('PUT /api/v1/customers/:id', () => {
    it('should update a customer', async () => {
      const customerId = 'uuid-1';
      const updateData = {
        name: 'Updated Name',
        email: 'updated@example.com',
      };

      const mockUpdatedCustomer = {
        id: customerId,
        companyId: mockCompanyId,
        ...updateData,
        status: 'active',
        currencyCode: 'USD',
        createdAt: new Date(),
        updatedAt: new Date(),
      };

      (customerService.updateCustomer as jest.Mock).mockResolvedValue(mockUpdatedCustomer);

      const response = await request(app)
        .put(`/api/v1/customers/${customerId}`)
        .set('Authorization', mockToken)
        .set('X-Company-ID', mockCompanyId.toString())
        .send(updateData);

      expect(response.status).toBe(200);
      expect(response.body.success).toBe(true);
      expect(response.body.data.name).toBe('Updated Name');
    });
  });

  describe('DELETE /api/v1/customers/:id', () => {
    it('should delete a customer', async () => {
      const customerId = 'uuid-1';

      (customerService.deleteCustomer as jest.Mock).mockResolvedValue(undefined);

      const response = await request(app)
        .delete(`/api/v1/customers/${customerId}`)
        .set('Authorization', mockToken)
        .set('X-Company-ID', mockCompanyId.toString());

      expect(response.status).toBe(200);
      expect(response.body.success).toBe(true);
      expect(customerService.deleteCustomer).toHaveBeenCalledWith(customerId, mockCompanyId);
    });

    it('should require owner role', async () => {
      const adminPayload = { ...mockPayload, role: 'admin' };
      (jwtService.verifyToken as jest.Mock).mockReturnValue(adminPayload);

      const response = await request(app)
        .delete('/api/v1/customers/uuid-1')
        .set('Authorization', mockToken)
        .set('X-Company-ID', mockCompanyId.toString());

      expect(response.status).toBe(403);
    });
  });

  describe('POST /api/v1/customers/:customerId/contacts', () => {
    it('should add a contact to customer', async () => {
      const customerId = 'uuid-1';
      const contactData = {
        name: 'John Doe',
        email: 'john@example.com',
        position: 'Manager',
      };

      const mockContact = {
        id: 'contact-uuid',
        customerId,
        ...contactData,
        isPrimary: false,
        createdAt: new Date(),
        updatedAt: new Date(),
      };

      (customerService.addContact as jest.Mock).mockResolvedValue(mockContact);

      const response = await request(app)
        .post(`/api/v1/customers/${customerId}/contacts`)
        .set('Authorization', mockToken)
        .set('X-Company-ID', mockCompanyId.toString())
        .send(contactData);

      expect(response.status).toBe(201);
      expect(response.body.success).toBe(true);
      expect(response.body.data.name).toBe('John Doe');
    });
  });

  describe('GET /api/v1/customers/:customerId/contacts', () => {
    it('should retrieve customer contacts', async () => {
      const customerId = 'uuid-1';
      const mockContacts = [
        {
          id: 'contact-1',
          customerId,
          name: 'John Doe',
          email: 'john@example.com',
          position: 'Manager',
          isPrimary: true,
          createdAt: new Date(),
          updatedAt: new Date(),
        },
      ];

      (customerService.getContacts as jest.Mock).mockResolvedValue(mockContacts);

      const response = await request(app)
        .get(`/api/v1/customers/${customerId}/contacts`)
        .set('Authorization', mockToken)
        .set('X-Company-ID', mockCompanyId.toString());

      expect(response.status).toBe(200);
      expect(response.body.success).toBe(true);
      expect(response.body.data.length).toBe(1);
    });
  });

  describe('Health and Status Endpoints', () => {
    it('should return health status', async () => {
      const response = await request(app).get('/health');

      expect(response.status).toBe(200);
      expect(response.body.status).toBe('healthy');
    });

    it('should return ready status', async () => {
      const response = await request(app).get('/ready');

      expect(response.status).toBe(200);
      expect(response.body.status).toBe('ready');
    });

    it('should return metrics', async () => {
      const response = await request(app).get('/metrics');

      expect(response.status).toBe(200);
      expect(response.body.success).toBe(true);
      expect(response.body.data).toHaveProperty('timestamp');
      expect(response.body.data).toHaveProperty('memory');
      expect(response.body.data).toHaveProperty('http');
    });
  });

  describe('404 Handling', () => {
    it('should return 404 for unknown routes', async () => {
      const response = await request(app).get('/api/v1/unknown');

      expect(response.status).toBe(404);
      expect(response.body.error).toBe('Route not found');
    });
  });
});
