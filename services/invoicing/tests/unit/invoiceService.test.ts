import { describe, it, expect, beforeEach, vi } from 'vitest';
import invoiceService, { CreateInvoiceDTO } from '../../src/services/invoiceService';
import Invoice from '../../src/models/Invoice';
import Company from '../../src/models/Company';

// Mock the models
vi.mock('../../src/models/Invoice');
vi.mock('../../src/models/Company');
vi.mock('../../src/services/eventPublisher');

describe('InvoiceService', () => {
  let companyId: string;
  let createDTO: CreateInvoiceDTO;

  beforeEach(() => {
    companyId = 'company-123';
    createDTO = {
      customerId: 'customer-123',
      customerName: 'John Doe',
      customerEmail: 'john@example.com',
      amount: 1000,
      taxAmount: 100,
      currency: 'USD',
      invoiceDate: new Date(),
      dueDate: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000),
      items: [
        {
          description: 'Service',
          quantity: 1,
          unitPrice: 1000,
          totalPrice: 1000,
        },
      ],
      notes: 'Test invoice',
    };

    vi.clearAllMocks();
  });

  describe('createInvoice', () => {
    it('should throw error if company not found', async () => {
      vi.mocked(Company.findByPk).mockResolvedValue(null);

      await expect(
        invoiceService.createInvoice(companyId, createDTO)
      ).rejects.toThrow('Company not found');
    });

    it('should create invoice with correct data', async () => {
      const mockCompany = { id: companyId };
      const mockInvoice = {
        id: 'invoice-123',
        companyId,
        invoiceNumber: 'INV-202401-00001',
        customerId: createDTO.customerId,
        customerName: createDTO.customerName,
        amount: createDTO.amount,
        taxAmount: createDTO.taxAmount,
        totalAmount: createDTO.amount + createDTO.taxAmount,
        ...createDTO,
      };

      vi.mocked(Company.findByPk).mockResolvedValue(mockCompany as any);
      vi.mocked(Invoice.create).mockResolvedValue(mockInvoice as any);

      const result = await invoiceService.createInvoice(companyId, createDTO);

      expect(result).toEqual(mockInvoice);
      expect(Invoice.create).toHaveBeenCalled();
    });
  });

  describe('getInvoice', () => {
    it('should return invoice if found', async () => {
      const mockInvoice = {
        id: 'invoice-123',
        companyId,
      };

      vi.mocked(Invoice.findOne).mockResolvedValue(mockInvoice as any);

      const result = await invoiceService.getInvoice(companyId, 'invoice-123');

      expect(result).toEqual(mockInvoice);
      expect(Invoice.findOne).toHaveBeenCalledWith({
        where: {
          id: 'invoice-123',
          companyId,
        },
      });
    });

    it('should return null if invoice not found', async () => {
      vi.mocked(Invoice.findOne).mockResolvedValue(null);

      const result = await invoiceService.getInvoice(companyId, 'invoice-123');

      expect(result).toBeNull();
    });
  });

  describe('deleteInvoice', () => {
    it('should throw error if invoice not found', async () => {
      vi.mocked(Invoice.findOne).mockResolvedValue(null);

      await expect(
        invoiceService.deleteInvoice(companyId, 'invoice-123')
      ).rejects.toThrow('Invoice not found');
    });

    it('should throw error if invoice status is not draft or cancelled', async () => {
      const mockInvoice = {
        id: 'invoice-123',
        status: 'sent',
        destroy: vi.fn(),
      };

      vi.mocked(Invoice.findOne).mockResolvedValue(mockInvoice as any);

      await expect(
        invoiceService.deleteInvoice(companyId, 'invoice-123')
      ).rejects.toThrow('Can only delete draft or cancelled invoices');
    });

    it('should delete invoice if status is draft', async () => {
      const mockInvoice = {
        id: 'invoice-123',
        invoiceNumber: 'INV-202401-00001',
        status: 'draft',
        destroy: vi.fn(),
      };

      vi.mocked(Invoice.findOne).mockResolvedValue(mockInvoice as any);

      await invoiceService.deleteInvoice(companyId, 'invoice-123');

      expect(mockInvoice.destroy).toHaveBeenCalled();
    });
  });

  describe('listInvoices', () => {
    it('should return invoices with pagination', async () => {
      const mockInvoices = [
        { id: 'invoice-1', status: 'sent' },
        { id: 'invoice-2', status: 'sent' },
      ];

      vi.mocked(Invoice.findAndCountAll).mockResolvedValue({
        rows: mockInvoices as any,
        count: 2,
      });

      const result = await invoiceService.listInvoices(companyId, {
        limit: 20,
        offset: 0,
      });

      expect(result.invoices).toEqual(mockInvoices);
      expect(result.total).toBe(2);
    });

    it('should filter invoices by status', async () => {
      vi.mocked(Invoice.findAndCountAll).mockResolvedValue({
        rows: [],
        count: 0,
      });

      await invoiceService.listInvoices(companyId, { status: 'sent' });

      expect(Invoice.findAndCountAll).toHaveBeenCalledWith(
        expect.objectContaining({
          where: expect.objectContaining({ status: 'sent' }),
        })
      );
    });
  });
});
