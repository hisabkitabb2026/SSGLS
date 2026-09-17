export const ABILITIES = {
  // Dashboard
  DASHBOARD: 'dashboard',

  // Customers
  CREATE_CUSTOMER: 'create-customer',
  DELETE_CUSTOMER: 'delete-customer',
  EDIT_CUSTOMER: 'edit-customer',
  VIEW_CUSTOMER: 'view-customer',

  // Items
  CREATE_ITEM: 'create-item',
  DELETE_ITEM: 'delete-item',
  EDIT_ITEM: 'edit-item',
  VIEW_ITEM: 'view-item',

  // Tax Types
  CREATE_TAX_TYPE: 'create-tax-type',
  DELETE_TAX_TYPE: 'delete-tax-type',
  EDIT_TAX_TYPE: 'edit-tax-type',
  VIEW_TAX_TYPE: 'view-tax-type',

  // Estimates
  CREATE_ESTIMATE: 'create-estimate',
  DELETE_ESTIMATE: 'delete-estimate',
  EDIT_ESTIMATE: 'edit-estimate',
  VIEW_ESTIMATE: 'view-estimate',
  SEND_ESTIMATE: 'send-estimate',

  // Invoices
  CREATE_INVOICE: 'create-invoice',
  DELETE_INVOICE: 'delete-invoice',
  EDIT_INVOICE: 'edit-invoice',
  VIEW_INVOICE: 'view-invoice',
  SEND_INVOICE: 'send-invoice',

  // LR Receipt
  CREATE_LR_RECEIPT: 'create-lr-receipt',
  DELETE_LR_RECEIPT: 'delete-lr-receipt',
  EDIT_LR_RECEIPT: 'edit-lr-receipt',
  VIEW_LR_RECEIPT: 'view-lr-receipt',
  SEND_LR_RECEIPT: 'send-lr-receipt',

  // Invoice Receipt (Office Invoice)
  CREATE_INVOICE_RECEIPT: 'create-invoice-receipt',
  DELETE_INVOICE_RECEIPT: 'delete-invoice-receipt',
  EDIT_INVOICE_RECEIPT: 'edit-invoice-receipt',
  VIEW_INVOICE_RECEIPT: 'view-invoice-receipt',
  SEND_INVOICE_RECEIPT: 'send-invoice-receipt',

  // Lorry Receipt
  CREATE_LORRY_RECEIPT: 'create-lorry-receipt',
  DELETE_LORRY_RECEIPT: 'delete-lorry-receipt',
  EDIT_LORRY_RECEIPT: 'edit-lorry-receipt',
  VIEW_LORRY_RECEIPT: 'view-lorry-receipt',
  SEND_LORRY_RECEIPT: 'send-lorry-receipt',

  // Recurring Invoices

  CREATE_RECURRING_INVOICE: 'create-recurring-invoice',
  DELETE_RECURRING_INVOICE: 'delete-recurring-invoice',
  EDIT_RECURRING_INVOICE: 'edit-recurring-invoice',
  VIEW_RECURRING_INVOICE: 'view-recurring-invoice',

  // Payments
  CREATE_PAYMENT: 'create-payment',
  DELETE_PAYMENT: 'delete-payment',
  EDIT_PAYMENT: 'edit-payment',
  VIEW_PAYMENT: 'view-payment',
  SEND_PAYMENT: 'send-payment',

  // Expenses
  CREATE_EXPENSE: 'create-expense',
  DELETE_EXPENSE: 'delete-expense',
  EDIT_EXPENSE: 'edit-expense',
  VIEW_EXPENSE: 'view-expense',

  // Custom Fields
  CREATE_CUSTOM_FIELDS: 'create-custom-field',
  DELETE_CUSTOM_FIELDS: 'delete-custom-field',
  EDIT_CUSTOM_FIELDS: 'edit-custom-field',
  VIEW_CUSTOM_FIELDS: 'view-custom-field',

  // Roles
  CREATE_ROLE: 'create-role',
  DELETE_ROLE: 'delete-role',
  EDIT_ROLE: 'edit-role',
  VIEW_ROLE: 'view-role',

  // Exchange Rates
  VIEW_EXCHANGE_RATE: 'view-exchange-rate-provider',
  CREATE_EXCHANGE_RATE: 'create-exchange-rate-provider',
  EDIT_EXCHANGE_RATE: 'edit-exchange-rate-provider',
  DELETE_EXCHANGE_RATE: 'delete-exchange-rate-provider',

  // Reports
  VIEW_FINANCIAL_REPORT: 'view-financial-reports',

  // Notes
  MANAGE_NOTE: 'manage-all-notes',
  VIEW_NOTE: 'view-all-notes',

  // Settings Pages
  VIEW_SETTINGS_COMPANY_INFO: 'view-settings-company-info',
  VIEW_SETTINGS_PREFERENCES: 'view-settings-preferences',
  VIEW_SETTINGS_CUSTOMIZATION: 'view-settings-customization',
  VIEW_SETTINGS_NOTIFICATIONS: 'view-settings-notifications',
  VIEW_SETTINGS_ROLES: 'view-settings-roles',
  VIEW_SETTINGS_MAIL_CONFIG: 'view-settings-mail-config',
  VIEW_SETTINGS_AI_CONFIG: 'view-settings-ai-config',
  VIEW_SETTINGS_MODULES: 'view-settings-modules',

  // Settings — remaining settings pages
  VIEW_SETTINGS_EXCHANGE_RATE: 'view-settings-exchange-rate',
  VIEW_SETTINGS_TAX_TYPES: 'view-settings-tax-types',
  VIEW_SETTINGS_PAYMENT_MODES: 'view-settings-payment-modes',
  VIEW_SETTINGS_CUSTOM_FIELDS: 'view-settings-custom-fields',
  VIEW_SETTINGS_NOTES: 'view-settings-notes',
  VIEW_SETTINGS_EXPENSE_CATEGORIES: 'view-settings-expense-categories',

  // Truck / Fleet Management
  CREATE_TRUCK: 'create-truck',
  DELETE_TRUCK: 'delete-truck',
  EDIT_TRUCK: 'edit-truck',
  VIEW_TRUCK: 'view-truck',

  // Truck Maintenance
  CREATE_TRUCK_MAINTENANCE: 'create-truck-maintenance',
  DELETE_TRUCK_MAINTENANCE: 'delete-truck-maintenance',
  EDIT_TRUCK_MAINTENANCE: 'edit-truck-maintenance',
  VIEW_TRUCK_MAINTENANCE: 'view-truck-maintenance',

  // Truck Odometer Reading
  CREATE_ODOMETER_READING: 'create-odometer-reading',
  DELETE_ODOMETER_READING: 'delete-odometer-reading',
  EDIT_ODOMETER_READING: 'edit-odometer-reading',
  VIEW_ODOMETER_READING: 'view-odometer-reading',

  // Truck Service Schedule
  CREATE_TRUCK_SERVICE_SCHEDULE: 'create-truck-service-schedule',
  DELETE_TRUCK_SERVICE_SCHEDULE: 'delete-truck-service-schedule',
  EDIT_TRUCK_SERVICE_SCHEDULE: 'edit-truck-service-schedule',
  VIEW_TRUCK_SERVICE_SCHEDULE: 'view-truck-service-schedule',

  // Warehouse Item
  CREATE_WAREHOUSE_ITEM: 'create-warehouse-item',
  DELETE_WAREHOUSE_ITEM: 'delete-warehouse-item',
  EDIT_WAREHOUSE_ITEM: 'edit-warehouse-item',
  VIEW_WAREHOUSE_ITEM: 'view-warehouse-item',

  // Load Trip
  CREATE_LOAD_TRIP: 'create-load-trip',
  DELETE_LOAD_TRIP: 'delete-load-trip',
  EDIT_LOAD_TRIP: 'edit-load-trip',
  VIEW_LOAD_TRIP: 'view-load-trip',

  // Consolidation Group
  CREATE_CONSOLIDATION_GROUP: 'create-consolidation-group',
  DELETE_CONSOLIDATION_GROUP: 'delete-consolidation-group',
  EDIT_CONSOLIDATION_GROUP: 'edit-consolidation-group',
  VIEW_CONSOLIDATION_GROUP: 'view-consolidation-group',

  // Quotation
  CREATE_QUOTATION: 'create-quotation',
  DELETE_QUOTATION: 'delete-quotation',
  EDIT_QUOTATION: 'edit-quotation',
  VIEW_QUOTATION: 'view-quotation',
} as const



export type Ability = typeof ABILITIES[keyof typeof ABILITIES]
