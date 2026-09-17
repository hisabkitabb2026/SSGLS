import { Sequelize } from 'sequelize';
import logger from '../utils/logger.js';

const sequelize = new Sequelize({
  dialect: 'postgres',
  host: process.env.DB_HOST || 'localhost',
  port: parseInt(process.env.DB_PORT || '5432', 10),
  database: process.env.DB_NAME || 'invoicing_db',
  username: process.env.DB_USER || 'postgres',
  password: process.env.DB_PASSWORD || 'postgres',
  logging: (sql) => {
    if (process.env.NODE_ENV !== 'test') {
      logger.debug({ sql }, 'SQL query executed');
    }
  },
  pool: {
    max: 5,
    min: 0,
    acquire: 30000,
    idle: 10000,
  },
});

export default sequelize;
