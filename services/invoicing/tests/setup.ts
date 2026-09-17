import { beforeAll, afterAll, beforeEach, afterEach } from 'vitest';
import sequelize from '../src/config/database';

beforeAll(async () => {
  // Setup test database connection
  try {
    await sequelize.authenticate();
    console.log('Test database connected');
  } catch (error) {
    console.error('Failed to connect to test database:', error);
    process.exit(1);
  }
});

afterAll(async () => {
  // Close database connection after tests
  await sequelize.close();
});

beforeEach(async () => {
  // Optional: Sync database before each test
  if (process.env.NODE_ENV === 'test') {
    await sequelize.sync({ alter: true });
  }
});

afterEach(async () => {
  // Optional: Clean up database after each test
  if (process.env.NODE_ENV === 'test') {
    // Clean tables
    const models = Object.values(sequelize.models);
    for (const model of models) {
      await model.destroy({ where: {}, truncate: true });
    }
  }
});
