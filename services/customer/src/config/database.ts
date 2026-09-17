import { Pool, PoolClient } from 'pg';
import { logger } from '../utils/logger';

export interface Database {
  query(text: string, params?: any[]): Promise<any>;
  getClient(): Promise<PoolClient>;
  close(): Promise<void>;
}

class DatabaseConnection implements Database {
  private pool: Pool;

  constructor() {
    this.pool = new Pool({
      host: process.env.DB_HOST || 'localhost',
      port: parseInt(process.env.DB_PORT || '5432', 10),
      database: process.env.DB_DATABASE || 'customer_service',
      user: process.env.DB_USERNAME || 'customer_user',
      password: process.env.DB_PASSWORD || 'secure_password',
      max: parseInt(process.env.DB_POOL_MAX || '10', 10),
      min: parseInt(process.env.DB_POOL_MIN || '2', 10),
      ssl: process.env.DB_SSL === 'true' ? { rejectUnauthorized: false } : false,
      idleTimeoutMillis: 30000,
      connectionTimeoutMillis: 5000,
    });

    this.pool.on('error', (err) => {
      logger.error({ err }, 'Unexpected error on idle client');
    });

    this.pool.on('connect', () => {
      logger.debug('New client connected to database');
    });
  }

  async query(text: string, params?: any[]): Promise<any> {
    const start = Date.now();
    try {
      const result = await this.pool.query(text, params);
      const duration = Date.now() - start;
      logger.debug({ duration, query: text }, 'Database query executed');
      return result;
    } catch (error) {
      const duration = Date.now() - start;
      logger.error({ error, duration, query: text }, 'Database query failed');
      throw error;
    }
  }

  async getClient(): Promise<PoolClient> {
    return this.pool.connect();
  }

  async close(): Promise<void> {
    await this.pool.end();
    logger.info('Database connection closed');
  }
}

export const database = new DatabaseConnection();

// Initialize database schema on startup
export async function initializeDatabase(): Promise<void> {
  try {
    await database.query(`
      CREATE TABLE IF NOT EXISTS customers (
        id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
        company_id INTEGER NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        address TEXT,
        city VARCHAR(100),
        state VARCHAR(100),
        postal_code VARCHAR(20),
        country VARCHAR(100),
        tax_id VARCHAR(50),
        currency_code VARCHAR(3) DEFAULT 'USD',
        website VARCHAR(255),
        notes TEXT,
        status VARCHAR(50) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INTEGER,
        updated_by INTEGER
      );

      CREATE INDEX IF NOT EXISTS idx_customers_company_id ON customers(company_id);
      CREATE INDEX IF NOT EXISTS idx_customers_email ON customers(email);
      CREATE INDEX IF NOT EXISTS idx_customers_status ON customers(status);
      CREATE INDEX IF NOT EXISTS idx_customers_created_at ON customers(created_at);

      CREATE TABLE IF NOT EXISTS customer_contacts (
        id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
        customer_id UUID NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        phone VARCHAR(20),
        position VARCHAR(100),
        is_primary BOOLEAN DEFAULT false,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      );

      CREATE INDEX IF NOT EXISTS idx_customer_contacts_customer_id ON customer_contacts(customer_id);

      CREATE TABLE IF NOT EXISTS customer_events (
        id SERIAL PRIMARY KEY,
        customer_id UUID NOT NULL,
        company_id INTEGER NOT NULL,
        event_type VARCHAR(100) NOT NULL,
        event_data JSONB,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      );

      CREATE INDEX IF NOT EXISTS idx_customer_events_customer_id ON customer_events(customer_id);
      CREATE INDEX IF NOT EXISTS idx_customer_events_company_id ON customer_events(company_id);
      CREATE INDEX IF NOT EXISTS idx_customer_events_event_type ON customer_events(event_type);
    `);

    logger.info('Database schema initialized successfully');
  } catch (error) {
    logger.error({ error }, 'Failed to initialize database schema');
    throw error;
  }
}
