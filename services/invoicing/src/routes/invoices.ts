import { Router } from 'express';
import { AuthRequest, authMiddleware, companyMiddleware } from '../middleware/auth.js';
import invoiceService from '../services/invoiceService.js';
import logger from '../utils/logger.js';

const router = Router();

// Apply middleware
router.use(authMiddleware);
router.use(companyMiddleware);

// Create invoice
router.post('/', async (req: AuthRequest, res) => {
  try {
    const { customerId, customerName, customerEmail, amount, taxAmount, currency, invoiceDate, dueDate, items, notes } = req.body;

    if (!customerId || !customerName || amount === undefined || taxAmount === undefined) {
      res.status(400).json({
        status: 'error',
        message: 'Missing required fields',
      });
      return;
    }

    const invoice = await invoiceService.createInvoice(req.companyId!, {
      customerId,
      customerName,
      customerEmail,
      amount,
      taxAmount,
      currency: currency || 'USD',
      invoiceDate: invoiceDate ? new Date(invoiceDate) : new Date(),
      dueDate: dueDate ? new Date(dueDate) : new Date(Date.now() + 30 * 24 * 60 * 60 * 1000),
      items: items || [],
      notes,
    });

    res.status(201).json({
      status: 'success',
      data: invoice,
    });
  } catch (error) {
    logger.error({ error }, 'Error creating invoice');
    res.status(500).json({
      status: 'error',
      message: 'Internal server error',
    });
  }
});

// Get all invoices
router.get('/', async (req: AuthRequest, res) => {
  try {
    const status = req.query.status as string | undefined;
    const customerId = req.query.customerId as string | undefined;
    const limit = req.query.limit ? parseInt(req.query.limit as string, 10) : 20;
    const offset = req.query.offset ? parseInt(req.query.offset as string, 10) : 0;

    const { invoices, total } = await invoiceService.listInvoices(req.companyId!, {
      status,
      customerId,
      limit,
      offset,
    });

    res.status(200).json({
      status: 'success',
      data: invoices,
      pagination: {
        limit,
        offset,
        total,
      },
    });
  } catch (error) {
    logger.error({ error }, 'Error fetching invoices');
    res.status(500).json({
      status: 'error',
      message: 'Internal server error',
    });
  }
});

// Get invoice by ID
router.get('/:id', async (req: AuthRequest, res) => {
  try {
    const invoice = await invoiceService.getInvoice(req.companyId!, req.params.id);

    if (!invoice) {
      res.status(404).json({
        status: 'error',
        message: 'Invoice not found',
      });
      return;
    }

    res.status(200).json({
      status: 'success',
      data: invoice,
    });
  } catch (error) {
    logger.error({ error }, 'Error fetching invoice');
    res.status(500).json({
      status: 'error',
      message: 'Internal server error',
    });
  }
});

// Update invoice
router.patch('/:id', async (req: AuthRequest, res) => {
  try {
    const invoice = await invoiceService.updateInvoice(req.companyId!, req.params.id, req.body);

    res.status(200).json({
      status: 'success',
      data: invoice,
    });
  } catch (error: unknown) {
    const errMsg = error instanceof Error ? error.message : 'Unknown error';
    logger.error({ error }, 'Error updating invoice');

    if (errMsg.includes('not found')) {
      res.status(404).json({
        status: 'error',
        message: errMsg,
      });
      return;
    }

    res.status(400).json({
      status: 'error',
      message: errMsg,
    });
  }
});

// Delete invoice
router.delete('/:id', async (req: AuthRequest, res) => {
  try {
    await invoiceService.deleteInvoice(req.companyId!, req.params.id);

    res.status(204).send();
  } catch (error: unknown) {
    const errMsg = error instanceof Error ? error.message : 'Unknown error';
    logger.error({ error }, 'Error deleting invoice');

    if (errMsg.includes('not found')) {
      res.status(404).json({
        status: 'error',
        message: errMsg,
      });
      return;
    }

    res.status(400).json({
      status: 'error',
      message: errMsg,
    });
  }
});

// Get invoices by status
router.get('/status/:status', async (req: AuthRequest, res) => {
  try {
    const { invoices, total } = await invoiceService.listInvoices(req.companyId!, {
      status: req.params.status,
    });

    res.status(200).json({
      status: 'success',
      data: invoices,
      pagination: { total },
    });
  } catch (error) {
    logger.error({ error }, 'Error fetching invoices by status');
    res.status(500).json({
      status: 'error',
      message: 'Internal server error',
    });
  }
});

// Get invoice statistics
router.get('/stats/summary', async (req: AuthRequest, res) => {
  try {
    const stats = await invoiceService.getInvoiceStats(req.companyId!);

    res.status(200).json({
      status: 'success',
      data: stats,
    });
  } catch (error) {
    logger.error({ error }, 'Error fetching invoice stats');
    res.status(500).json({
      status: 'error',
      message: 'Internal server error',
    });
  }
});

export default router;
