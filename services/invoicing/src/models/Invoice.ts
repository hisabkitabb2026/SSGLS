import {
  DataTypes,
  Model,
  InferAttributes,
  InferCreationAttributes,
  CreationOptional,
  ForeignKey,
} from 'sequelize';
import sequelize from '../config/database.js';
import Company from './Company.js';

export interface InvoiceAttributes
  extends Model<InferAttributes<Invoice>, InferCreationAttributes<Invoice>> {
  id: CreationOptional<string>;
  companyId: ForeignKey<Company['id']>;
  invoiceNumber: string;
  customerId: string;
  customerName: string;
  customerEmail?: string;
  amount: number;
  taxAmount: number;
  totalAmount: number;
  currency: string;
  status: 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled';
  invoiceDate: Date;
  dueDate: Date;
  items: Array<{
    description: string;
    quantity: number;
    unitPrice: number;
    totalPrice: number;
  }>;
  notes?: string;
  createdAt: CreationOptional<Date>;
  updatedAt: CreationOptional<Date>;
}

const Invoice = sequelize.define<Invoice>(
  'Invoice',
  {
    id: {
      type: DataTypes.UUID,
      defaultValue: DataTypes.UUIDV4,
      primaryKey: true,
    },
    companyId: {
      type: DataTypes.UUID,
      allowNull: false,
      references: {
        model: 'Companies',
        key: 'id',
      },
      index: true,
    },
    invoiceNumber: {
      type: DataTypes.STRING,
      allowNull: false,
      unique: 'unique_invoice_per_company',
    },
    customerId: {
      type: DataTypes.UUID,
      allowNull: false,
    },
    customerName: {
      type: DataTypes.STRING,
      allowNull: false,
    },
    customerEmail: {
      type: DataTypes.STRING,
      allowNull: true,
    },
    amount: {
      type: DataTypes.DECIMAL(12, 2),
      allowNull: false,
    },
    taxAmount: {
      type: DataTypes.DECIMAL(12, 2),
      allowNull: false,
    },
    totalAmount: {
      type: DataTypes.DECIMAL(12, 2),
      allowNull: false,
    },
    currency: {
      type: DataTypes.STRING,
      allowNull: false,
      defaultValue: 'USD',
    },
    status: {
      type: DataTypes.ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled'),
      allowNull: false,
      defaultValue: 'draft',
    },
    invoiceDate: {
      type: DataTypes.DATE,
      allowNull: false,
    },
    dueDate: {
      type: DataTypes.DATE,
      allowNull: false,
    },
    items: {
      type: DataTypes.JSON,
      allowNull: false,
      defaultValue: [],
    },
    notes: {
      type: DataTypes.TEXT,
      allowNull: true,
    },
    createdAt: DataTypes.DATE,
    updatedAt: DataTypes.DATE,
  },
  {
    tableName: 'invoices',
    timestamps: true,
    indexes: [
      {
        fields: ['companyId', 'invoiceNumber'],
        unique: true,
        name: 'unique_invoice_per_company',
      },
      {
        fields: ['companyId', 'status'],
      },
      {
        fields: ['companyId', 'dueDate'],
      },
    ],
  }
);

export default Invoice;
