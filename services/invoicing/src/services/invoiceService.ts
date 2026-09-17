import { v4 as uuidv4 } from 'uuid';
import Invoice from '../models/Invoice.js';
import Company from '../models/Company.js';
import eventPublisher from './eventPublisher.js';
import logger from '../utils/logger.js';

export interface CreateInvoiceDTO {
  customerId: string;
  customerName: string;
  customerEmail?: string;
  amount: number;
  taxAmount: number;
  currency: string;
  invoiceDate: Date;
  dueDate: Date;
  items: Array<{
    description: string;
    quantity: number;
    unitPrice: number;
    totalPrice: number;
  }>;
  notes?: string;
}

export interface UpdateInvoiceDTO {
  customerName?: string;
  customerEmail?: string;
  amount?: number;
  taxAmount?: number;
  currency?: string;
  invoiceDate?: Date;
  dueDate?: Date;
  items?: Array<{
    description: string;
    quantity: number;
    unitPrice: number;
    totalPrice: number;
  }>;
  notes?: string;
  status?: 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled';
}

class InvoiceService {
  async createInvoice(
    companyId: string,
    dto: CreateInvoiceDTO
  ): Promise<Invoice> {
    const company = await Company.findByPk(companyId);
    if (!company) {
      throw new Error('Company not found');
    }

    const invoiceNumber = await this.generateInvoiceNumber(companyId);
    const totalAmount = dto.amount + dto.taxAmount;

    const invoice = await Invoice.create({
      id: uuidv4(),
      companyId,
      invoiceNumber,
      customerId: dto.customerId,
      customerName: dto.customerName,
      customerEmail: dto.customerEmail,
      amount: dto.amount,
      taxAmount: dto.taxAmount,
      totalAmount,
      currency: dto.currency || 'USD',
      status: 'draft',
      invoiceDate: dto.invoiceDate,
      dueDate: dto.dueDate,
      items: dto.items || [],
      notes: dto.notes,
    });

    logger.info(
      {
        invoiceId: invoice.id,
        companyId,
        invoiceNumber,
        customerId: dto.customerId,
      },
      'Invoice created'
    );

    // Publish event
    await eventPublisher.publishEvent('invoice.created', {
      invoiceId: invoice.id,
      invoiceNumber: invoice.invoiceNumber,
      companyId,
      customerId: dto.customerId,
      customerName: dto.customerName,
      amount: totalAmount,
    }, companyId);

    return invoice;
  }

  async getInvoice(companyId: string, invoiceId: string): Promise<Invoice | null> {
    return Invoice.findOne({
      where: {
        id: invoiceId,
        companyId,
      },
    });
  }

  async getInvoiceByNumber(
    companyId: string,
    invoiceNumber: string
  ): Promise<Invoice | null> {
    return Invoice.findOne({
      where: {
        companyId,
        invoiceNumber,
      },
    });
  }

  async listInvoices(
    companyId: string,
    filters?: {
      status?: string;
      customerId?: string;
      limit?: number;
      offset?: number;
    }
  ): Promise<{ invoices: Invoice[]; total: number }> {
    const where: Record<string, unknown> = { companyId };

    if (filters?.status) {
      where.status = filters.status;
    }

    if (filters?.customerId) {
      where.customerId = filters.customerId;
    }

    const { rows: invoices, count: total } = await Invoice.findAndCountAll({
      where,
      limit: filters?.limit || 20,
      offset: filters?.offset || 0,
      order: [['createdAt', 'DESC']],
    });

    return { invoices, total };
  }

  async updateInvoice(
    companyId: string,
    invoiceId: string,
    dto: UpdateInvoiceDTO
  ): Promise<Invoice> {
    const invoice = await this.getInvoice(companyId, invoiceId);
    if (!invoice) {
      throw new Error('Invoice not found');
    }

    // Prevent updating draft invoices only
    if (invoice.status !== 'draft' && dto.status !== invoice.status) {
      throw new Error('Can only change status of draft invoices');
    }

    const updateData: Partial<Invoice> = {};

    if (dto.customerName !== undefined) updateData.customerName = dto.customerName;
    if (dto.customerEmail !== undefined) updateData.customerEmail = dto.customerEmail;
    if (dto.amount !== undefined) updateData.amount = dto.amount;
    if (dto.taxAmount !== undefined) updateData.taxAmount = dto.taxAmount;
    if (dto.currency !== undefined) updateData.currency = dto.currency;
    if (dto.invoiceDate !== undefined) updateData.invoiceDate = dto.invoiceDate;
    if (dto.dueDate !== undefined) updateData.dueDate = dto.dueDate;
    if (dto.items !== undefined) updateData.items = dto.items;
    if (dto.notes !== undefined) updateData.notes = dto.notes;
    if (dto.status !== undefined) updateData.status = dto.status;

    // Recalculate total amount if amount or tax changed
    if (updateData.amount !== undefined || updateData.taxAmount !== undefined) {
      const amount = updateData.amount ?? invoice.amount;
      const taxAmount = updateData.taxAmount ?? invoice.taxAmount;
      updateData.totalAmount = amount + taxAmount;
    }

    await invoice.update(updateData);

    logger.info(
      {
        invoiceId,
        companyId,
        changes: Object.keys(updateData),
      },
      'Invoice updated'
    );

    // Publish event
    await eventPublisher.publishEvent('invoice.updated', {
      invoiceId,
      invoiceNumber: invoice.invoiceNumber,
      companyId,
      changes: updateData,
    }, companyId);

    return invoice;
  }

  async deleteInvoice(companyId: string, invoiceId: string): Promise<void> {
    const invoice = await this.getInvoice(companyId, invoiceId);
    if (!invoice) {
      throw new Error('Invoice not found');
    }

    if (invoice.status !== 'draft' && invoice.status !== 'cancelled') {
      throw new Error('Can only delete draft or cancelled invoices');
    }

    const invoiceNumber = invoice.invoiceNumber;
    await invoice.destroy();

    logger.info(
      {
        invoiceId,
        companyId,
        invoiceNumber,
      },
      'Invoice deleted'
    );

    // Publish event
    await eventPublisher.publishEvent('invoice.deleted', {
      invoiceId,
      invoiceNumber,
      companyId,
    }, companyId);
  }

  async getInvoicesByDueDate(
    companyId: string,
    beforeDate: Date
  ): Promise<Invoice[]> {
    return Invoice.findAll({
      where: {
        companyId,
        dueDate: {
          [require('sequelize').Op.lte]: beforeDate,
        },
        status: ['sent', 'overdue'],
      },
      order: [['dueDate', 'ASC']],
    });
  }

  private async generateInvoiceNumber(companyId: string): Promise<string> {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');

    const lastInvoice = await Invoice.findOne({
      where: {
        companyId,
      },
      order: [['createdAt', 'DESC']],
    });

    let sequence = 1;
    if (lastInvoice) {
      const lastNumber = lastInvoice.invoiceNumber;
      const match = lastNumber.match(/(\d+)$/);
      if (match) {
        sequence = parseInt(match[1], 10) + 1;
      }
    }

    return `INV-${year}${month}-${String(sequence).padStart(5, '0')}`;
  }

  async getInvoiceStats(companyId: string): Promise<Record<string, unknown>> {
    const stats = await Invoice.findAll({
      attributes: [
        'status',
        [require('sequelize').fn('COUNT', require('sequelize').col('id')), 'count'],
        [require('sequelize').fn('SUM', require('sequelize').col('totalAmount')), 'totalAmount'],
      ],
      where: { companyId },
      group: ['status'],
      raw: true,
    });

    return stats;
  }
}

export default new InvoiceService();
