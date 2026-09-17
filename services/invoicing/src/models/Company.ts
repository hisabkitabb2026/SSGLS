import {
  DataTypes,
  Model,
  InferAttributes,
  InferCreationAttributes,
  CreationOptional,
} from 'sequelize';
import sequelize from '../config/database.js';

export interface CompanyAttributes
  extends Model<InferAttributes<Company>, InferCreationAttributes<Company>> {
  id: CreationOptional<string>;
  name: string;
  email?: string;
  phone?: string;
  address?: string;
  city?: string;
  state?: string;
  zipCode?: string;
  country?: string;
  taxId?: string;
  logo?: string;
  settings: Record<string, unknown>;
  createdAt: CreationOptional<Date>;
  updatedAt: CreationOptional<Date>;
}

const Company = sequelize.define<Company>(
  'Company',
  {
    id: {
      type: DataTypes.UUID,
      defaultValue: DataTypes.UUIDV4,
      primaryKey: true,
    },
    name: {
      type: DataTypes.STRING,
      allowNull: false,
    },
    email: {
      type: DataTypes.STRING,
      allowNull: true,
    },
    phone: {
      type: DataTypes.STRING,
      allowNull: true,
    },
    address: {
      type: DataTypes.STRING,
      allowNull: true,
    },
    city: {
      type: DataTypes.STRING,
      allowNull: true,
    },
    state: {
      type: DataTypes.STRING,
      allowNull: true,
    },
    zipCode: {
      type: DataTypes.STRING,
      allowNull: true,
    },
    country: {
      type: DataTypes.STRING,
      allowNull: true,
    },
    taxId: {
      type: DataTypes.STRING,
      allowNull: true,
    },
    logo: {
      type: DataTypes.STRING,
      allowNull: true,
    },
    settings: {
      type: DataTypes.JSON,
      allowNull: false,
      defaultValue: {},
    },
    createdAt: DataTypes.DATE,
    updatedAt: DataTypes.DATE,
  },
  {
    tableName: 'companies',
    timestamps: true,
  }
);

export default Company;
