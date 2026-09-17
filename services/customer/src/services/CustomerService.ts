import { v4 as uuidv4 } from 'uuid';
import {
  Customer,
  CustomerContact,
  CreateCustomerDTO,
  UpdateCustomerDTO,
  CreateCustomerContactDTO,
  UpdateCustomerContactDTO,
  customerRepository,
} from '../models/Customer';
import { messageBroker } from '../config/rabbitmq';
import { logger } from '../utils/logger';
import { AppError } from '../middleware/errorHandler';

export interface ICustomerService {
  createCustomer(dto: CreateCustomerDTO): Promise<Customer>;
  getCustomer(id: string, companyId: number): Promise<Customer>;
  listCustomers(companyId: number, filters?: any): Promise<any>;
  updateCustomer(id: string, companyId: number, dto: UpdateCustomerDTO): Promise<Customer>;
  deleteCustomer(id: string, companyId: number): Promise<void>;
  addContact(customerId: string, companyId: number, dto: CreateCustomerContactDTO): Promise<CustomerContact>;
  getContacts(customerId: string, companyId: number): Promise<CustomerContact[]>;
  deleteContact(contactId: string): Promise<void>;
}

export class CustomerService implements ICustomerService {
  async createCustomer(dto: CreateCustomerDTO): Promise<Customer> {
    try {
      // Check if email already exists in company
      const existingCustomer = await customerRepository.findByEmail(dto.email, dto.companyId);
      if (existingCustomer) {
        throw new AppError(409, `Customer with email ${dto.email} already exists`);
      }

      const customer = await customerRepository.create(dto);

      // Publish event
      await messageBroker.publishEvent('created', {
        customerId: customer.id,
        companyId: customer.companyId,
        email: customer.email,
        name: customer.name,
      });

      logger.info({ customerId: customer.id, companyId: dto.companyId }, 'Customer created successfully');
      return customer;
    } catch (error) {
      if (error instanceof AppError) throw error;
      logger.error({ error, dto }, 'Error creating customer');
      throw new AppError(500, 'Failed to create customer');
    }
  }

  async getCustomer(id: string, companyId: number): Promise<Customer> {
    try {
      const customer = await customerRepository.findById(id, companyId);

      if (!customer) {
        throw new AppError(404, 'Customer not found');
      }

      return customer;
    } catch (error) {
      if (error instanceof AppError) throw error;
      logger.error({ error, id, companyId }, 'Error retrieving customer');
      throw new AppError(500, 'Failed to retrieve customer');
    }
  }

  async listCustomers(companyId: number, filters?: { status?: string; limit?: number; offset?: number }): Promise<any> {
    try {
      const result = await customerRepository.findAll(companyId, filters);
      return result;
    } catch (error) {
      logger.error({ error, companyId }, 'Error listing customers');
      throw new AppError(500, 'Failed to list customers');
    }
  }

  async updateCustomer(id: string, companyId: number, dto: UpdateCustomerDTO): Promise<Customer> {
    try {
      // Verify customer exists
      const existing = await customerRepository.findById(id, companyId);
      if (!existing) {
        throw new AppError(404, 'Customer not found');
      }

      // Check email uniqueness if being updated
      if (dto.email && dto.email !== existing.email) {
        const emailExists = await customerRepository.findByEmail(dto.email, companyId);
        if (emailExists) {
          throw new AppError(409, `Email ${dto.email} is already in use`);
        }
      }

      const updated = await customerRepository.update(id, companyId, dto);

      if (!updated) {
        throw new AppError(404, 'Customer not found');
      }

      // Publish event
      await messageBroker.publishEvent('updated', {
        customerId: updated.id,
        companyId: updated.companyId,
        email: updated.email,
        name: updated.name,
        changes: dto,
      });

      logger.info({ customerId: id, companyId }, 'Customer updated successfully');
      return updated;
    } catch (error) {
      if (error instanceof AppError) throw error;
      logger.error({ error, id, companyId }, 'Error updating customer');
      throw new AppError(500, 'Failed to update customer');
    }
  }

  async deleteCustomer(id: string, companyId: number): Promise<void> {
    try {
      const existing = await customerRepository.findById(id, companyId);
      if (!existing) {
        throw new AppError(404, 'Customer not found');
      }

      const deleted = await customerRepository.delete(id, companyId);

      if (!deleted) {
        throw new AppError(404, 'Customer not found');
      }

      // Publish event
      await messageBroker.publishEvent('deleted', {
        customerId: id,
        companyId,
        email: existing.email,
      });

      logger.info({ customerId: id, companyId }, 'Customer deleted successfully');
    } catch (error) {
      if (error instanceof AppError) throw error;
      logger.error({ error, id, companyId }, 'Error deleting customer');
      throw new AppError(500, 'Failed to delete customer');
    }
  }

  async addContact(
    customerId: string,
    companyId: number,
    dto: CreateCustomerContactDTO
  ): Promise<CustomerContact> {
    try {
      // Verify customer exists and belongs to company
      const customer = await customerRepository.findById(customerId, companyId);
      if (!customer) {
        throw new AppError(404, 'Customer not found');
      }

      const contact = await customerRepository.createContact(customerId, dto);

      // Publish event
      await messageBroker.publishEvent('contact_added', {
        customerId,
        companyId,
        contactId: contact.id,
        contactName: contact.name,
      });

      logger.info({ customerId, contactId: contact.id }, 'Contact added successfully');
      return contact;
    } catch (error) {
      if (error instanceof AppError) throw error;
      logger.error({ error, customerId, companyId }, 'Error adding contact');
      throw new AppError(500, 'Failed to add contact');
    }
  }

  async getContacts(customerId: string, companyId: number): Promise<CustomerContact[]> {
    try {
      // Verify customer exists and belongs to company
      const customer = await customerRepository.findById(customerId, companyId);
      if (!customer) {
        throw new AppError(404, 'Customer not found');
      }

      return await customerRepository.findContactsByCustomerId(customerId);
    } catch (error) {
      if (error instanceof AppError) throw error;
      logger.error({ error, customerId, companyId }, 'Error retrieving contacts');
      throw new AppError(500, 'Failed to retrieve contacts');
    }
  }

  async deleteContact(contactId: string): Promise<void> {
    try {
      const deleted = await customerRepository.deleteContact(contactId);

      if (!deleted) {
        throw new AppError(404, 'Contact not found');
      }

      logger.info({ contactId }, 'Contact deleted successfully');
    } catch (error) {
      if (error instanceof AppError) throw error;
      logger.error({ error, contactId }, 'Error deleting contact');
      throw new AppError(500, 'Failed to delete contact');
    }
  }
}

export const customerService = new CustomerService();
