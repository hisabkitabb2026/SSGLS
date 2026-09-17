-- Initialize database with extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE transport_db TO transport_user;

-- Create schema if needed
CREATE SCHEMA IF NOT EXISTS public;
GRANT ALL PRIVILEGES ON SCHEMA public TO transport_user;

-- Set default privileges
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO transport_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO transport_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON FUNCTIONS TO transport_user;
