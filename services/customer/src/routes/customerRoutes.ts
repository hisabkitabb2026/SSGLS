import { Router, Request, Response } from 'express';
import Joi from 'joi';
import { customerService } from '../services/CustomerService';
import { authenticateJWT, validateCompanyId, authorize } from '../middleware/auth';
import { asyncHandler, AppError } from '../middleware/errorHandler';
import { logger } from '../utils/logger';

const router = Router();

// Validation schemas
const createCustomerSchema = Joi.object({
  email: Joi.string().email().required(),
  name: Joi.string().required(),
  phone: Joi.string().optional(),
  address: Joi.string().optional(),
  city: Joi.string().optional(),
  state: Joi.string().optional(),
  postalCode: Joi.string().optional(),
  country: Joi.string().optional(),
  taxId: Joi.string().optional(),
  currencyCode: Joi.string().optional(),
  website: Joi.string().optional(),
  notes: Joi.string().optional(),
});

const updateCustomerSchema = Joi.object({
  email: Joi.string().email().optional(),
  name: Joi.string().optional(),
  phone: Joi.string().optional(),
  address: Joi.string().optional(),
  city: Joi.string().optional(),
  state: Joi.string().optional(),
  postalCode: Joi.string().optional(),
  country: Joi.string().optional(),
  taxId: Joi.string().optional(),
  currencyCode: Joi.string().optional(),
  website: Joi.string().optional(),
  notes: Joi.string().optional(),
  status: Joi.string().valid('active', 'inactive', 'archived').optional(),
});

const createContactSchema = Joi.object({
  name: Joi.string().required(),
  email: Joi.string().email().optional(),
  phone: Joi.string().optional(),
  position: Joi.string().optional(),
  isPrimary: Joi.boolean().optional(),
});

const validateRequest = (schema: Joi.Schema) => {
  return (req: Request, res: Response, next: Function) => {
    const { error, value } = schema.validate(req.body, { stripUnknown: true });
    if (error) {
      logger.warn({ error: error.message, body: req.body }, 'Validation error');
      throw new AppError(400, `Validation error: ${error.message}`);
    }
    req.body = value;
    next();
  };
};

// Routes
// Create Customer
router.post(
  '/customers',
  authenticateJWT,
  validateCompanyId,
  authorize(['owner', 'admin']),
  validateRequest(createCustomerSchema),
  asyncHandler(async (req: Request, res: Response) => {
    const customer = await customerService.createCustomer({
      ...req.body,
      companyId: req.companyId!,
      createdBy: req.user!.userId,
    });

    res.status(201).json({
      success: true,
      data: customer,
    });
  })
);

// Get Customer by ID
router.get(
  '/customers/:id',
  authenticateJWT,
  validateCompanyId,
  asyncHandler(async (req: Request, res: Response) => {
    const customer = await customerService.getCustomer(req.params.id, req.companyId!);

    res.status(200).json({
      success: true,
      data: customer,
    });
  })
);

// List Customers
router.get(
  '/customers',
  authenticateJWT,
  validateCompanyId,
  asyncHandler(async (req: Request, res: Response) => {
    const filters = {
      status: req.query.status as string | undefined,
      limit: req.query.limit ? parseInt(req.query.limit as string, 10) : 50,
      offset: req.query.offset ? parseInt(req.query.offset as string, 10) : 0,
    };

    const result = await customerService.listCustomers(req.companyId!, filters);

    res.status(200).json({
      success: true,
      data: result.data,
      pagination: {
        total: result.total,
        limit: filters.limit,
        offset: filters.offset,
      },
    });
  })
);

// Update Customer
router.put(
  '/customers/:id',
  authenticateJWT,
  validateCompanyId,
  authorize(['owner', 'admin']),
  validateRequest(updateCustomerSchema),
  asyncHandler(async (req: Request, res: Response) => {
    const customer = await customerService.updateCustomer(req.params.id, req.companyId!, {
      ...req.body,
      updatedBy: req.user!.userId,
    });

    res.status(200).json({
      success: true,
      data: customer,
    });
  })
);

// Delete Customer
router.delete(
  '/customers/:id',
  authenticateJWT,
  validateCompanyId,
  authorize(['owner']),
  asyncHandler(async (req: Request, res: Response) => {
    await customerService.deleteCustomer(req.params.id, req.companyId!);

    res.status(200).json({
      success: true,
      message: 'Customer deleted successfully',
    });
  })
);

// Add Customer Contact
router.post(
  '/customers/:customerId/contacts',
  authenticateJWT,
  validateCompanyId,
  authorize(['owner', 'admin']),
  validateRequest(createContactSchema),
  asyncHandler(async (req: Request, res: Response) => {
    const contact = await customerService.addContact(req.params.customerId, req.companyId!, req.body);

    res.status(201).json({
      success: true,
      data: contact,
    });
  })
);

// Get Customer Contacts
router.get(
  '/customers/:customerId/contacts',
  authenticateJWT,
  validateCompanyId,
  asyncHandler(async (req: Request, res: Response) => {
    const contacts = await customerService.getContacts(req.params.customerId, req.companyId!);

    res.status(200).json({
      success: true,
      data: contacts,
    });
  })
);

// Delete Contact
router.delete(
  '/contacts/:contactId',
  authenticateJWT,
  validateCompanyId,
  authorize(['owner', 'admin']),
  asyncHandler(async (req: Request, res: Response) => {
    await customerService.deleteContact(req.params.contactId);

    res.status(200).json({
      success: true,
      message: 'Contact deleted successfully',
    });
  })
);

export default router;
