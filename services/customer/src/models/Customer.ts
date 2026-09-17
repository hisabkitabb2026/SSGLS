export interface Customer {
  id: string;
  companyId: number;
  email: string;
  name: string;
  phone?: string;
  address?: string;
  city?: string;
  state?: string;
  postalCode?: string;
  country?: string;
  taxId?: string;
  currencyCode: string;
  website?: string;
  notes?: string;
  status: 'active' | 'inactive' | 'archived';
  createdAt: Date;
  updatedAt: Date;
  createdBy?: number;
  updatedBy?: number;
}

export interface CustomerContact {
  id: string;
  customerId: string;
  name: string;
  email?: string;
  phone?: string;
  position?: string;
  isPrimary: boolean;
  createdAt: Date;
  updatedAt: Date;
}

export interface CreateCustomerDTO {
  companyId: number;
  email: string;
  name: string;
  phone?: string;
  address?: string;
  city?: string;
  state?: string;
  postalCode?: string;
  country?: string;
  taxId?: string;
  currencyCode?: string;
  website?: string;
  notes?: string;
  createdBy: number;
}

export interface UpdateCustomerDTO {
  email?: string;
  name?: string;
  phone?: string;
  address?: string;
  city?: string;
  state?: string;
  postalCode?: string;
  country?: string;
  taxId?: string;
  currencyCode?: string;
  website?: string;
  notes?: string;
  status?: 'active' | 'inactive' | 'archived';
  updatedBy: number;
}

export interface CreateCustomerContactDTO {
  name: string;
  email?: string;
  phone?: string;
  position?: string;
  isPrimary?: boolean;
}

export interface UpdateCustomerContactDTO {
  name?: string;
  email?: string;
  phone?: string;
  position?: string;
  isPrimary?: boolean;
}

import { database } from '../config/database';
import { logger } from '../utils/logger';

export class CustomerRepository {
  async create(dto: CreateCustomerDTO): Promise<Customer> {
    const query = `
      INSERT INTO customers (
        company_id, email, name, phone, address, city, state, postal_code,
        country, tax_id, currency_code, website, notes, created_by
      ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14)
      RETURNING id, company_id as "companyId", email, name, phone, address,
                city, state, postal_code as "postalCode", country, tax_id as "taxId",
                currency_code as "currencyCode", website, notes, status,
                created_at as "createdAt", updated_at as "updatedAt", created_by as "createdBy"
    `;

    const result = await database.query(query, [
      dto.companyId,
      dto.email,
      dto.name,
      dto.phone || null,
      dto.address || null,
      dto.city || null,
      dto.state || null,
      dto.postalCode || null,
      dto.country || null,
      dto.taxId || null,
      dto.currencyCode || 'USD',
      dto.website || null,
      dto.notes || null,
      dto.createdBy || null,
    ]);

    logger.info({ customerId: result.rows[0].id, companyId: dto.companyId }, 'Customer created');
    return result.rows[0];
  }

  async findById(id: string, companyId: number): Promise<Customer | null> {
    const query = `
      SELECT id, company_id as "companyId", email, name, phone, address,
             city, state, postal_code as "postalCode", country, tax_id as "taxId",
             currency_code as "currencyCode", website, notes, status,
             created_at as "createdAt", updated_at as "updatedAt", created_by as "createdBy",
             updated_by as "updatedBy"
      FROM customers
      WHERE id = $1 AND company_id = $2
    `;

    const result = await database.query(query, [id, companyId]);
    return result.rows[0] || null;
  }

  async findByEmail(email: string, companyId: number): Promise<Customer | null> {
    const query = `
      SELECT id, company_id as "companyId", email, name, phone, address,
             city, state, postal_code as "postalCode", country, tax_id as "taxId",
             currency_code as "currencyCode", website, notes, status,
             created_at as "createdAt", updated_at as "updatedAt", created_by as "createdBy",
             updated_by as "updatedBy"
      FROM customers
      WHERE email = $1 AND company_id = $2
    `;

    const result = await database.query(query, [email, companyId]);
    return result.rows[0] || null;
  }

  async findAll(
    companyId: number,
    filters?: { status?: string; limit?: number; offset?: number }
  ): Promise<{ data: Customer[]; total: number }> {
    let query = `
      SELECT id, company_id as "companyId", email, name, phone, address,
             city, state, postal_code as "postalCode", country, tax_id as "taxId",
             currency_code as "currencyCode", website, notes, status,
             created_at as "createdAt", updated_at as "updatedAt", created_by as "createdBy",
             updated_by as "updatedBy"
      FROM customers
      WHERE company_id = $1
    `;

    const params: any[] = [companyId];
    let paramIndex = 2;

    if (filters?.status) {
      query += ` AND status = $${paramIndex}`;
      params.push(filters.status);
      paramIndex++;
    }

    const countResult = await database.query(
      `SELECT COUNT(*) as count FROM customers WHERE company_id = $1${filters?.status ? ` AND status = $${paramIndex - 1}` : ''}`,
      filters?.status ? [companyId, filters.status] : [companyId]
    );

    query += ` ORDER BY created_at DESC LIMIT $${paramIndex} OFFSET $${paramIndex + 1}`;
    params.push(filters?.limit || 50);
    params.push(filters?.offset || 0);

    const result = await database.query(query, params);
    return {
      data: result.rows,
      total: parseInt(countResult.rows[0].count, 10),
    };
  }

  async update(id: string, companyId: number, dto: UpdateCustomerDTO): Promise<Customer | null> {
    const updates: string[] = [];
    const params: any[] = [];
    let paramIndex = 1;

    if (dto.email !== undefined) {
      updates.push(`email = $${paramIndex}`);
      params.push(dto.email);
      paramIndex++;
    }
    if (dto.name !== undefined) {
      updates.push(`name = $${paramIndex}`);
      params.push(dto.name);
      paramIndex++;
    }
    if (dto.phone !== undefined) {
      updates.push(`phone = $${paramIndex}`);
      params.push(dto.phone);
      paramIndex++;
    }
    if (dto.address !== undefined) {
      updates.push(`address = $${paramIndex}`);
      params.push(dto.address);
      paramIndex++;
    }
    if (dto.city !== undefined) {
      updates.push(`city = $${paramIndex}`);
      params.push(dto.city);
      paramIndex++;
    }
    if (dto.state !== undefined) {
      updates.push(`state = $${paramIndex}`);
      params.push(dto.state);
      paramIndex++;
    }
    if (dto.postalCode !== undefined) {
      updates.push(`postal_code = $${paramIndex}`);
      params.push(dto.postalCode);
      paramIndex++;
    }
    if (dto.country !== undefined) {
      updates.push(`country = $${paramIndex}`);
      params.push(dto.country);
      paramIndex++;
    }
    if (dto.taxId !== undefined) {
      updates.push(`tax_id = $${paramIndex}`);
      params.push(dto.taxId);
      paramIndex++;
    }
    if (dto.currencyCode !== undefined) {
      updates.push(`currency_code = $${paramIndex}`);
      params.push(dto.currencyCode);
      paramIndex++;
    }
    if (dto.website !== undefined) {
      updates.push(`website = $${paramIndex}`);
      params.push(dto.website);
      paramIndex++;
    }
    if (dto.notes !== undefined) {
      updates.push(`notes = $${paramIndex}`);
      params.push(dto.notes);
      paramIndex++;
    }
    if (dto.status !== undefined) {
      updates.push(`status = $${paramIndex}`);
      params.push(dto.status);
      paramIndex++;
    }

    updates.push(`updated_by = $${paramIndex}`);
    params.push(dto.updatedBy);
    paramIndex++;

    updates.push('updated_at = CURRENT_TIMESTAMP');

    params.push(id);
    params.push(companyId);

    const query = `
      UPDATE customers
      SET ${updates.join(', ')}
      WHERE id = $${paramIndex} AND company_id = $${paramIndex + 1}
      RETURNING id, company_id as "companyId", email, name, phone, address,
                city, state, postal_code as "postalCode", country, tax_id as "taxId",
                currency_code as "currencyCode", website, notes, status,
                created_at as "createdAt", updated_at as "updatedAt", created_by as "createdBy",
                updated_by as "updatedBy"
    `;

    const result = await database.query(query, params);
    if (result.rows.length > 0) {
      logger.info({ customerId: id, companyId }, 'Customer updated');
      return result.rows[0];
    }
    return null;
  }

  async delete(id: string, companyId: number): Promise<boolean> {
    const query = 'DELETE FROM customers WHERE id = $1 AND company_id = $2';
    const result = await database.query(query, [id, companyId]);
    if (result.rowCount && result.rowCount > 0) {
      logger.info({ customerId: id, companyId }, 'Customer deleted');
      return true;
    }
    return false;
  }

  async createContact(customerId: string, dto: CreateCustomerContactDTO): Promise<CustomerContact> {
    const query = `
      INSERT INTO customer_contacts (customer_id, name, email, phone, position, is_primary)
      VALUES ($1, $2, $3, $4, $5, $6)
      RETURNING id, customer_id as "customerId", name, email, phone, position,
                is_primary as "isPrimary", created_at as "createdAt", updated_at as "updatedAt"
    `;

    const result = await database.query(query, [
      customerId,
      dto.name,
      dto.email || null,
      dto.phone || null,
      dto.position || null,
      dto.isPrimary || false,
    ]);

    return result.rows[0];
  }

  async findContactsByCustomerId(customerId: string): Promise<CustomerContact[]> {
    const query = `
      SELECT id, customer_id as "customerId", name, email, phone, position,
             is_primary as "isPrimary", created_at as "createdAt", updated_at as "updatedAt"
      FROM customer_contacts
      WHERE customer_id = $1
      ORDER BY is_primary DESC, created_at ASC
    `;

    const result = await database.query(query, [customerId]);
    return result.rows;
  }

  async deleteContact(contactId: string): Promise<boolean> {
    const query = 'DELETE FROM customer_contacts WHERE id = $1';
    const result = await database.query(query, [contactId]);
    return result.rowCount ? result.rowCount > 0 : false;
  }
}

export const customerRepository = new CustomerRepository();
