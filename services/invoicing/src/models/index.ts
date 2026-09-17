import Company from './Company.js';
import Invoice from './Invoice.js';

// Define associations
Company.hasMany(Invoice, {
  foreignKey: 'companyId',
  as: 'invoices',
});

Invoice.belongsTo(Company, {
  foreignKey: 'companyId',
  as: 'company',
});

export { Company, Invoice };
