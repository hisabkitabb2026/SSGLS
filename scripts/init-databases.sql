-- =============================================================================
-- Microservices Database Initialization Script
-- Creates a dedicated database per microservice for data isolation.
-- This script runs automatically on first PostgreSQL container startup
-- (mounted at /docker-entrypoint-initdb.d/).
-- =============================================================================

-- Invoicing Service Database
CREATE DATABASE invoicing_db;
CREATE DATABASE expense_service;
CREATE DATABASE customer_service;
CREATE DATABASE product_service;
CREATE DATABASE settings_db;
CREATE DATABASE transport_db;

-- Grant privileges to the app user on all service databases
GRANT ALL PRIVILEGES ON DATABASE invoicing_db TO postgres;
GRANT ALL PRIVILEGES ON DATABASE expense_service TO postgres;
GRANT ALL PRIVILEGES ON DATABASE customer_service TO postgres;
GRANT ALL PRIVILEGES ON DATABASE product_service TO postgres;
GRANT ALL PRIVILEGES ON DATABASE settings_db TO postgres;
GRANT ALL PRIVILEGES ON DATABASE transport_db TO postgres;
