/**
 * Jest Test Fixtures for Frontend E2E Tests
 *
 * Provides:
 * - Mock API responses
 * - Test data factories
 * - Auth fixtures
 * - Component test utilities
 */

import { vi } from 'vitest'

// ============================================================================
// Auth Fixtures
// ============================================================================

export const mockAuthToken = {
  valid: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjoxLCJjb21wYW55X2lkIjoxLCJ1c2VybmFtZSI6InRlc3RfdXNlciIsImVtYWlsIjoidGVzdEBleGFtcGxlLmNvbSIsInJvbGVzIjpbImFkbWluIl0sImV4cCI6OTk5OTk5OTk5OSwiaWF0IjoxNjAwMDAwMDAwfQ.mock',
  expired: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjoxLCJjb21wYW55X2lkIjoxLCJleHAiOjE2MDAwMDAwMDB9.mock',
  invalid: 'invalid.token.format',
}

export const mockAuthUser = {
  id: 1,
  company_id: 1,
  username: 'test_user',
  email: 'test@example.com',
  roles: ['admin'],
  permissions: ['create_invoice', 'read_invoice', 'update_invoice', 'delete_invoice'],
}

export const mockAuthHeaders = () => ({
  'Authorization': `Bearer ${mockAuthToken.valid}`,
  'X-Company-ID': '1',
  'Content-Type': 'application/json',
})

// ============================================================================
// Company Fixtures
// ============================================================================

export const mockCompanyData = {
  valid: {
    name: 'Test Company Inc',
    email: 'info@testcompany.com',
    phone: '+1-555-0123',
    currency_code: 'USD',
    timezone: 'UTC',
    website: 'https://testcompany.example.com',
    industry: 'Technology',
    registration_number: 'TC123456',
  },
  invalid: {
    name: '', // Invalid: empty name
    email: 'invalid-email',
    phone: 'invalid-phone',
  },
}

export const mockCompanyResponse = {
  id: 1,
  ...mockCompanyData.valid,
  created_at: '2024-01-01T00:00:00Z',
  updated_at: '2024-01-01T00:00:00Z',
}

// ============================================================================
// Customer Fixtures
// ============================================================================

export const mockCustomerData = {
  valid: {
    name: 'John Doe Enterprises',
    email: 'john@example.com',
    phone: '+1-555-0124',
    address: '123 Main St',
    city: 'New York',
    state: 'NY',
    postal_code: '10001',
    country: 'US',
    tax_id: '12-3456789',
  },
  batch: [
    { name: 'Customer 1', email: 'customer1@example.com' },
    { name: 'Customer 2', email: 'customer2@example.com' },
    { name: 'Customer 3', email: 'customer3@example.com' },
  ],
}

export const mockCustomerResponse = {
  id: 1,
  company_id: 1,
  ...mockCustomerData.valid,
  created_at: '2024-01-01T00:00:00Z',
  updated_at: '2024-01-01T00:00:00Z',
}

export const mockCustomersListResponse = {
  data: [mockCustomerResponse],
  pagination: {
    page: 1,
    per_page: 50,
    total: 1,
    pages: 1,
  },
}

// ============================================================================
// Product Fixtures
// ============================================================================

export const mockProductData = {
  valid: {
    name: 'Premium Web Development Service',
    description: 'Full-stack web application development',
    sku: 'PWD-2024-001',
    unit_price: 5000.0,
    tax_rate: 0.1,
    category: 'Services',
    unit_type: 'hours',
    is_active: true,
  },
  batch: [
    { name: 'Product 1', sku: 'PROD-001', unit_price: 100 },
    { name: 'Product 2', sku: 'PROD-002', unit_price: 200 },
    { name: 'Product 3', sku: 'PROD-003', unit_price: 300 },
  ],
}

export const mockProductResponse = {
  id: 1,
  company_id: 1,
  ...mockProductData.valid,
  created_at: '2024-01-01T00:00:00Z',
  updated_at: '2024-01-01T00:00:00Z',
}

// ============================================================================
// Invoice Fixtures
// ============================================================================

export const mockInvoiceData = {
  valid: {
    customer_id: 1,
    invoice_number: 'INV-2024-001',
    invoice_date: '2024-01-01',
    due_date: '2024-01-31',
    status: 'draft',
    currency_code: 'USD',
    notes: 'Thank you for your business!',
    items: [
      {
        product_id: 1,
        description: 'Web Development Service',
        quantity: 40,
        unit_price: 100.0,
        tax_rate: 0.1,
        line_total: 4400.0,
      },
    ],
    subtotal: 4000.0,
    tax_total: 400.0,
    total_amount: 4400.0,
  },
  draft: {
    customer_id: 1,
    invoice_number: 'INV-DRAFT-001',
    status: 'draft',
    total_amount: 1000.0,
  },
  published: {
    customer_id: 1,
    invoice_number: 'INV-PUB-001',
    status: 'published',
    total_amount: 2000.0,
  },
}

export const mockInvoiceResponse = {
  id: 1,
  company_id: 1,
  ...mockInvoiceData.valid,
  created_at: '2024-01-01T00:00:00Z',
  updated_at: '2024-01-01T00:00:00Z',
}

export const mockInvoiceListResponse = {
  data: [mockInvoiceResponse],
  pagination: {
    page: 1,
    per_page: 50,
    total: 1,
    pages: 1,
  },
}

// ============================================================================
// Payment Fixtures
// ============================================================================

export const mockPaymentData = {
  valid: {
    amount: 500.0,
    payment_method: 'credit_card',
    transaction_id: 'TXN-12345',
    payment_date: '2024-01-15',
    notes: 'Payment received',
  },
  partial: {
    amount: 500.0,
    payment_method: 'bank_transfer',
  },
  full: {
    amount: 4400.0,
    payment_method: 'check',
  },
}

export const mockPaymentResponse = {
  id: 1,
  invoice_id: 1,
  ...mockPaymentData.valid,
  created_at: '2024-01-15T00:00:00Z',
}

// ============================================================================
// Expense Fixtures
// ============================================================================

export const mockExpenseData = {
  valid: {
    vendor_name: 'Office Supplies Co',
    vendor_email: 'sales@suppliesco.com',
    amount: 250.5,
    currency_code: 'USD',
    category: 'Office Supplies',
    description: 'Monthly office supplies',
    expense_date: '2024-01-15',
    status: 'pending',
    payment_method: 'credit_card',
    reference_number: 'EXP-2024-001',
  },
  batch: [
    { vendor_name: 'Vendor 1', amount: 100, category: 'Office Supplies' },
    { vendor_name: 'Vendor 2', amount: 200, category: 'Travel' },
    { vendor_name: 'Vendor 3', amount: 300, category: 'Utilities' },
  ],
}

export const mockExpenseResponse = {
  id: 1,
  company_id: 1,
  ...mockExpenseData.valid,
  created_at: '2024-01-15T00:00:00Z',
  updated_at: '2024-01-15T00:00:00Z',
}

// ============================================================================
// Transport Fixtures
// ============================================================================

export const mockTransportData = {
  valid: {
    tracking_number: 'TRK-2024-001',
    origin: 'New York, NY',
    destination: 'Los Angeles, CA',
    distance_km: 2800,
    estimated_cost: 500.0,
    carrier_name: 'Premium Logistics',
    vehicle_number: 'VH-2024-001',
    driver_name: 'John Smith',
    driver_phone: '+1-555-0150',
    status: 'pending',
    departure_date: '2024-01-20',
    expected_arrival_date: '2024-01-23',
    notes: 'Handle with care',
    insurance_required: true,
  },
  batch: [
    {
      tracking_number: 'TRK-001',
      origin: 'City 0',
      destination: 'City 10',
      distance_km: 1000,
      estimated_cost: 300,
    },
    {
      tracking_number: 'TRK-002',
      origin: 'City 1',
      destination: 'City 11',
      distance_km: 1100,
      estimated_cost: 350,
    },
    {
      tracking_number: 'TRK-003',
      origin: 'City 2',
      destination: 'City 12',
      distance_km: 1200,
      estimated_cost: 400,
    },
  ],
}

export const mockTransportResponse = {
  id: 1,
  company_id: 1,
  ...mockTransportData.valid,
  created_at: '2024-01-20T00:00:00Z',
  updated_at: '2024-01-20T00:00:00Z',
}

// ============================================================================
// API Response Fixtures
// ============================================================================

export const mockApiResponses = {
  success: {
    status: 200,
    data: { message: 'Success' },
  },
  created: {
    status: 201,
    data: { id: 1, message: 'Created' },
  },
  badRequest: {
    status: 400,
    data: { error: 'Invalid request', details: {} },
  },
  unauthorized: {
    status: 401,
    data: { error: 'Unauthorized' },
  },
  forbidden: {
    status: 403,
    data: { error: 'Forbidden' },
  },
  notFound: {
    status: 404,
    data: { error: 'Not found' },
  },
  conflict: {
    status: 409,
    data: { error: 'Conflict' },
  },
  serverError: {
    status: 500,
    data: { error: 'Internal server error' },
  },
}

// ============================================================================
// Event Fixtures
// ============================================================================

export const mockEventData = {
  customerCreated: {
    event_type: 'customer.created',
    source_service: 'customer',
    customer_id: 1,
    timestamp: '2024-01-01T00:00:00Z',
  },
  invoiceCreated: {
    event_type: 'invoice.created',
    source_service: 'invoice',
    invoice_id: 1,
    customer_id: 1,
    timestamp: '2024-01-01T00:00:00Z',
  },
  invoicePublished: {
    event_type: 'invoice.published',
    source_service: 'invoice',
    invoice_id: 1,
    timestamp: '2024-01-01T00:00:00Z',
  },
  paymentRecorded: {
    event_type: 'payment.recorded',
    source_service: 'invoice',
    invoice_id: 1,
    amount: 500.0,
    timestamp: '2024-01-01T00:00:00Z',
  },
}

// ============================================================================
// Mock API Client
// ============================================================================

export class MockApiClient {
  private baseUrl: string
  private token: string = ''
  private companyId: string = '1'

  constructor(baseUrl: string = 'http://localhost:8000') {
    this.baseUrl = baseUrl
  }

  setToken(token: string) {
    this.token = token
  }

  setCompanyId(companyId: string) {
    this.companyId = companyId
  }

  private getHeaders() {
    return {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${this.token}`,
      'X-Company-ID': this.companyId,
    }
  }

  async get(endpoint: string) {
    return vi.fn().mockResolvedValue({
      status: 200,
      data: {},
    })
  }

  async post(endpoint: string, data: any) {
    return vi.fn().mockResolvedValue({
      status: 201,
      data: { id: 1, ...data },
    })
  }

  async put(endpoint: string, data: any) {
    return vi.fn().mockResolvedValue({
      status: 200,
      data: { ...data },
    })
  }

  async patch(endpoint: string, data: any) {
    return vi.fn().mockResolvedValue({
      status: 200,
      data: { ...data },
    })
  }

  async delete(endpoint: string) {
    return vi.fn().mockResolvedValue({
      status: 204,
    })
  }
}

// ============================================================================
// Factory Functions
// ============================================================================

export function createMockCustomer(overrides = {}) {
  return {
    ...mockCustomerResponse,
    id: Math.random(),
    ...overrides,
  }
}

export function createMockProduct(overrides = {}) {
  return {
    ...mockProductResponse,
    id: Math.random(),
    ...overrides,
  }
}

export function createMockInvoice(overrides = {}) {
  return {
    ...mockInvoiceResponse,
    id: Math.random(),
    ...overrides,
  }
}

export function createMockPayment(overrides = {}) {
  return {
    ...mockPaymentResponse,
    id: Math.random(),
    ...overrides,
  }
}

export function createMockExpense(overrides = {}) {
  return {
    ...mockExpenseResponse,
    id: Math.random(),
    ...overrides,
  }
}

export function createMockTransport(overrides = {}) {
  return {
    ...mockTransportResponse,
    id: Math.random(),
    ...overrides,
  }
}

// ============================================================================
// Fetch/HTTP Mocking
// ============================================================================

export function mockFetch(responses: Record<string, any>) {
  return vi.fn((url: string, options: any) => {
    for (const [pattern, response] of Object.entries(responses)) {
      if (url.includes(pattern)) {
        return Promise.resolve({
          ok: response.status < 400,
          status: response.status,
          json: () => Promise.resolve(response.data || response),
          text: () => Promise.resolve(JSON.stringify(response.data || response)),
          headers: new Map(Object.entries(response.headers || {})),
        })
      }
    }

    return Promise.resolve({
      ok: false,
      status: 404,
      json: () => Promise.resolve({ error: 'Not found' }),
    })
  })
}

// ============================================================================
// Store/State Fixtures
// ============================================================================

export const mockStoreState = {
  auth: {
    token: mockAuthToken.valid,
    user: mockAuthUser,
    isAuthenticated: true,
  },
  company: {
    current: mockCompanyResponse,
    settings: {},
  },
  customers: {
    list: [mockCustomerResponse],
    current: mockCustomerResponse,
    loading: false,
    error: null,
  },
  products: {
    list: [mockProductResponse],
    current: mockProductResponse,
    loading: false,
    error: null,
  },
  invoices: {
    list: [mockInvoiceResponse],
    current: mockInvoiceResponse,
    loading: false,
    error: null,
  },
  expenses: {
    list: [mockExpenseResponse],
    current: mockExpenseResponse,
    loading: false,
    error: null,
  },
  transports: {
    list: [mockTransportResponse],
    current: mockTransportResponse,
    loading: false,
    error: null,
  },
}

// ============================================================================
// Utility Fixtures
// ============================================================================

export function createMockRef(value: any) {
  return {
    value,
    __isRef: true,
  }
}

export function createMockPinia() {
  return {
    state: mockStoreState,
    getters: {},
    mutations: {},
    actions: {},
  }
}
