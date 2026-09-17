import { CustomerService } from '../../src/services/CustomerService';
import { customerRepository } from '../../src/models/Customer';
import { messageBroker } from '../../src/config/rabbitmq';
import { AppError } from '../../src/middleware/errorHandler';

jest.mock('../../src/models/Customer');
jest.mock('../../src/config/rabbitmq');

describe('CustomerService', () => {
  let service: CustomerService;
  const mockCompanyId = 1;
  const mockUserId = 123;

  beforeEach(() => {
    service = new CustomerService();
    jest.clearAllMocks();
  });

  describe('createCustomer', () => {
    it('should create a customer successfully', async () => {
      const dto = {
        companyId: mockCompanyId,
        email: 'test@example.com',
        name: 'Test Customer',
        createdBy: mockUserId,
      };

      const mockCustomer = {
        id: 'uuid-1',
        companyId: mockCompanyId,
        email: 'test@example.com',
        name: 'Test Customer',
        status: 'active',
        currencyCode: 'USD',
        createdAt: new Date(),
        updatedAt: new Date(),
      };

      (customerRepository.findByEmail as jest.Mock).mockResolvedValue(null);
      (customerRepository.create as jest.Mock).mockResolvedValue(mockCustomer);
      (messageBroker.publishEvent as jest.Mock).mockResolvedValue(undefined);

      const result = await service.createCustomer(dto);

      expect(result).toEqual(mockCustomer);
      expect(customerRepository.create).toHaveBeenCalledWith(dto);
      expect(messageBroker.publishEvent).toHaveBeenCalledWith('created', expect.any(Object));
    });

    it('should throw error if email already exists', async () => {
      const dto = {
        companyId: mockCompanyId,
        email: 'existing@example.com',
        name: 'Test Customer',
        createdBy: mockUserId,
      };

      (customerRepository.findByEmail as jest.Mock).mockResolvedValue({
        id: 'uuid-1',
        email: 'existing@example.com',
      });

      await expect(service.createCustomer(dto)).rejects.toThrow(AppError);
    });
  });

  describe('getCustomer', () => {
    it('should return customer by id', async () => {
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

      (customerRepository.findById as jest.Mock).mockResolvedValue(mockCustomer);

      const result = await service.getCustomer(customerId, mockCompanyId);

      expect(result).toEqual(mockCustomer);
      expect(customerRepository.findById).toHaveBeenCalledWith(customerId, mockCompanyId);
    });

    it('should throw error if customer not found', async () => {
      const customerId = 'non-existent';

      (customerRepository.findById as jest.Mock).mockResolvedValue(null);

      await expect(service.getCustomer(customerId, mockCompanyId)).rejects.toThrow('Customer not found');
    });
  });

  describe('updateCustomer', () => {
    it('should update customer successfully', async () => {
      const customerId = 'uuid-1';
      const existingCustomer = {
        id: customerId,
        companyId: mockCompanyId,
        email: 'test@example.com',
        name: 'Test Customer',
        status: 'active',
        currencyCode: 'USD',
        createdAt: new Date(),
        updatedAt: new Date(),
      };

      const updateDto = {
        name: 'Updated Name',
        updatedBy: mockUserId,
      };

      const updatedCustomer = { ...existingCustomer, ...updateDto };

      (customerRepository.findById as jest.Mock).mockResolvedValue(existingCustomer);
      (customerRepository.update as jest.Mock).mockResolvedValue(updatedCustomer);
      (messageBroker.publishEvent as jest.Mock).mockResolvedValue(undefined);

      const result = await service.updateCustomer(customerId, mockCompanyId, updateDto);

      expect(result).toEqual(updatedCustomer);
      expect(customerRepository.update).toHaveBeenCalledWith(customerId, mockCompanyId, updateDto);
      expect(messageBroker.publishEvent).toHaveBeenCalledWith('updated', expect.any(Object));
    });

    it('should throw error if customer not found', async () => {
      const customerId = 'non-existent';

      (customerRepository.findById as jest.Mock).mockResolvedValue(null);

      await expect(
        service.updateCustomer(customerId, mockCompanyId, { name: 'New', updatedBy: mockUserId })
      ).rejects.toThrow('Customer not found');
    });
  });

  describe('deleteCustomer', () => {
    it('should delete customer successfully', async () => {
      const customerId = 'uuid-1';
      const existingCustomer = {
        id: customerId,
        companyId: mockCompanyId,
        email: 'test@example.com',
        name: 'Test Customer',
        status: 'active',
        currencyCode: 'USD',
        createdAt: new Date(),
        updatedAt: new Date(),
      };

      (customerRepository.findById as jest.Mock).mockResolvedValue(existingCustomer);
      (customerRepository.delete as jest.Mock).mockResolvedValue(true);
      (messageBroker.publishEvent as jest.Mock).mockResolvedValue(undefined);

      await service.deleteCustomer(customerId, mockCompanyId);

      expect(customerRepository.delete).toHaveBeenCalledWith(customerId, mockCompanyId);
      expect(messageBroker.publishEvent).toHaveBeenCalledWith('deleted', expect.any(Object));
    });

    it('should throw error if customer not found', async () => {
      const customerId = 'non-existent';

      (customerRepository.findById as jest.Mock).mockResolvedValue(null);

      await expect(service.deleteCustomer(customerId, mockCompanyId)).rejects.toThrow('Customer not found');
    });
  });

  describe('addContact', () => {
    it('should add contact successfully', async () => {
      const customerId = 'uuid-1';
      const existingCustomer = {
        id: customerId,
        companyId: mockCompanyId,
        email: 'test@example.com',
        name: 'Test Customer',
      };

      const contactDto = {
        name: 'John Doe',
        email: 'john@example.com',
        position: 'Manager',
      };

      const mockContact = {
        id: 'contact-uuid',
        customerId,
        ...contactDto,
        isPrimary: false,
        createdAt: new Date(),
        updatedAt: new Date(),
      };

      (customerRepository.findById as jest.Mock).mockResolvedValue(existingCustomer);
      (customerRepository.createContact as jest.Mock).mockResolvedValue(mockContact);
      (messageBroker.publishEvent as jest.Mock).mockResolvedValue(undefined);

      const result = await service.addContact(customerId, mockCompanyId, contactDto);

      expect(result).toEqual(mockContact);
      expect(messageBroker.publishEvent).toHaveBeenCalledWith('contact_added', expect.any(Object));
    });

    it('should throw error if customer not found', async () => {
      const customerId = 'non-existent';

      (customerRepository.findById as jest.Mock).mockResolvedValue(null);

      await expect(service.addContact(customerId, mockCompanyId, { name: 'John' })).rejects.toThrow(
        'Customer not found'
      );
    });
  });

  describe('listCustomers', () => {
    it('should list customers with pagination', async () => {
      const mockCustomers = {
        data: [
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
        ],
        total: 2,
      };

      (customerRepository.findAll as jest.Mock).mockResolvedValue(mockCustomers);

      const result = await service.listCustomers(mockCompanyId, { limit: 10, offset: 0 });

      expect(result).toEqual(mockCustomers);
      expect(customerRepository.findAll).toHaveBeenCalledWith(mockCompanyId, { limit: 10, offset: 0 });
    });
  });
});
