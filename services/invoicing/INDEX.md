# Invoicing Microservice - Complete File Index

## Project Structure

```
invoicing-microservice/
├── Root Configuration Files
│   ├── package.json                 - NPM dependencies and scripts
│   ├── tsconfig.json               - TypeScript compiler options
│   ├── vitest.config.ts            - Vitest testing framework config
│   ├── .eslintrc.json              - ESLint style guide
│   ├── .env.example                - Environment variables template
│   ├── .gitignore                  - Git ignore patterns
│   ├── Makefile                    - Build and command shortcuts
│   │
├── Documentation
│   ├── README.md                   - Main project documentation (87 KB)
│   ├── ARCHITECTURE.md             - System design and patterns (12 KB)
│   ├── openapi.yaml                - OpenAPI 3.0 specification
│   └── INDEX.md                    - This file
│
├── Docker & Infrastructure
│   ├── Dockerfile                  - Multi-stage container build
│   ├── docker-compose.yml          - Complete stack setup
│   ├── prometheus.yml              - Prometheus scrape config
│   │
├── Source Code (src/)
│   ├── app.ts                      - Express app entry point (main app initialization)
│   │
│   ├── config/
│   │   └── database.ts             - Sequelize PostgreSQL configuration
│   │
│   ├── middleware/
│   │   └── auth.ts                 - JWT and company isolation middleware
│   │
│   ├── models/
│   │   ├── Company.ts              - Company model (multi-tenancy)
│   │   ├── Invoice.ts              - Invoice model with company isolation
│   │   └── index.ts                - Model associations and exports
│   │
│   ├── routes/
│   │   ├── invoices.ts             - Invoice CRUD endpoints
│   │   ├── health.ts               - Health check endpoints
│   │   └── metrics.ts              - Prometheus metrics endpoint
│   │
│   ├── services/
│   │   ├── invoiceService.ts       - Invoice business logic (700+ LOC)
│   │   └── eventPublisher.ts       - RabbitMQ event publishing
│   │
│   └── utils/
│       ├── logger.ts               - Winston structured logging
│       └── jwt.ts                  - JWT generation and verification
│
├── Tests (tests/)
│   ├── setup.ts                    - Test environment setup
│   │
│   ├── unit/
│   │   ├── jwt.test.ts             - JWT utility tests
│   │   ├── auth.test.ts            - Middleware tests
│   │   └── invoiceService.test.ts  - Service logic tests
│   │
│   └── integration/
│       └── invoices.test.ts        - End-to-end API tests
│
└── Examples
    └── test-api.sh                 - Bash script for API testing
```

## File Breakdown

### Core Application Files

#### `src/app.ts` (200 lines)
- **Purpose**: Express application setup and initialization
- **Key Features**:
  - Helmet security headers
  - CORS configuration
  - Request/response middleware stack
  - Health check and readiness probes
  - Error handling and 404 fallback
  - Graceful shutdown handling
  - RabbitMQ and database initialization

#### `src/config/database.ts` (30 lines)
- **Purpose**: Sequelize ORM configuration
- **Exports**: Configured Sequelize instance
- **Features**:
  - PostgreSQL dialect
  - Connection pooling (min: 0, max: 5)
  - Query logging in development
  - Automatic reconnection

#### `src/middleware/auth.ts` (100 lines)
- **Purpose**: JWT authentication and company isolation
- **Exports**:
  - `authMiddleware` - JWT token verification
  - `companyMiddleware` - Company ID validation
  - `AuthRequest` - Extended Express Request type
- **Key Features**:
  - Bearer token extraction
  - JWT verification with error handling
  - Company ID header validation
  - Cross-company access prevention (403)

### Database Models

#### `src/models/Company.ts` (55 lines)
- **Table**: companies
- **Fields**: 13 columns + timestamps
- **Features**:
  - UUID primary key
  - Settings as JSON
  - Tax ID and address fields
  - Logo storage

#### `src/models/Invoice.ts` (90 lines)
- **Table**: invoices
- **Fields**: 15 columns + timestamps
- **Key Features**:
  - UUID with UUIDV4 default
  - Company isolation via companyId FK
  - Unique index on (companyId, invoiceNumber)
  - Status enum with 5 states
  - JSON array for line items
  - Indexed queries on status, dueDate
  - Total amount calculation

#### `src/models/index.ts` (15 lines)
- **Purpose**: Model association definitions
- **Relationships**:
  - Company.hasMany(Invoice)
  - Invoice.belongsTo(Company)

### Service Layer

#### `src/services/invoiceService.ts` (350 lines)
- **Purpose**: Invoice business logic
- **Key Methods**:
  - `createInvoice()` - Create with invoice number generation
  - `getInvoice()` - Fetch with company isolation
  - `getInvoiceByNumber()` - Query by invoice number
  - `listInvoices()` - Paginated list with filters
  - `updateInvoice()` - Update with status validation
  - `deleteInvoice()` - Delete with draft/cancelled restriction
  - `getInvoicesByDueDate()` - Due date queries
  - `generateInvoiceNumber()` - Auto-increment format
  - `getInvoiceStats()` - Aggregated statistics
- **Features**:
  - Invoice number format: INV-YYYYMM-XXXXX
  - Total amount auto-calculation
  - Status transition validation
  - Company isolation on all queries
  - Event publishing on state changes

#### `src/services/eventPublisher.ts` (130 lines)
- **Purpose**: RabbitMQ event publishing
- **Key Methods**:
  - `connect()` - Establish connection
  - `publishEvent()` - Send events to queues
  - `reconnect()` - Handle disconnections
  - `close()` - Graceful shutdown
- **Features**:
  - Automatic reconnection with exponential backoff
  - Queue assertion
  - Persistent message delivery
  - Error handling and logging
  - Support for 3 event types

### Routes & Endpoints

#### `src/routes/invoices.ts` (250 lines)
- **Protected Endpoints** (require JWT + company-id):
  - `POST /api/v1/invoices` - Create invoice
  - `GET /api/v1/invoices` - List with pagination
  - `GET /api/v1/invoices/:id` - Get by ID
  - `PATCH /api/v1/invoices/:id` - Update
  - `DELETE /api/v1/invoices/:id` - Delete
  - `GET /api/v1/invoices/status/:status` - Filter by status
  - `GET /api/v1/invoices/stats/summary` - Statistics

#### `src/routes/health.ts` (40 lines)
- **Public Endpoints**:
  - `GET /health` - Service health (includes uptime)
  - `GET /ready` - Readiness probe (K8s compatible)

#### `src/routes/metrics.ts` (60 lines)
- **Public Endpoint**:
  - `GET /metrics` - Prometheus metrics
- **Metrics Tracked**:
  - http_request_duration_seconds (Histogram)
  - http_requests_total (Counter)
  - invoices_created_total (Counter)
  - invoices_deleted_total (Counter)
  - db_connections_active (Gauge)
  - events_published_total (Counter)

### Utilities

#### `src/utils/jwt.ts` (40 lines)
- **Exports**:
  - `JwtPayload` - TypeScript interface
  - `generateToken()` - Create JWT
  - `verifyToken()` - Validate and extract
  - `decodeToken()` - Parse without verification
- **Algorithm**: HS256
- **Default Expiry**: 24h

#### `src/utils/logger.ts` (25 lines)
- **Framework**: Winston 3.11+
- **Outputs**:
  - Console (all levels)
  - File: logs/error.log (errors only)
  - File: logs/combined.log (all)
- **Formats**:
  - Development: Simple text
  - Production: JSON with metadata

### Docker & Infrastructure

#### `Dockerfile` (40 lines)
- **Base Image**: node:20-alpine (production)
- **Build Image**: node:20-alpine (builder)
- **Multi-stage**:
  - Builder: Install deps, compile TypeScript
  - Production: Lean runtime image
- **Features**:
  - Non-root user (nodejs:1001)
  - Health check endpoint
  - Exposed port: 8001
  - Minimal attack surface

#### `docker-compose.yml` (90 lines)
- **Services** (4):
  1. **postgres** - PostgreSQL 16 (data)
  2. **rabbitmq** - RabbitMQ 3.13 (events)
  3. **invoicing-service** - Node.js app
  4. **prometheus** - Metrics collection
- **Features**:
  - Health checks for all services
  - Volume persistence
  - Service networking
  - Environment variable injection
  - Dependency ordering

#### `prometheus.yml` (12 lines)
- **Job**: invoicing-service
- **Scrape Interval**: 10s
- **Metrics Path**: /metrics
- **Target**: invoicing-service:9090

### Configuration Files

#### `package.json` (55 lines)
- **Runtime**: Node.js 20+ (ES2020 modules)
- **Dependencies** (13):
  - amqplib 0.10.3 (RabbitMQ)
  - express 4.18.2
  - helmet 7.1.0 (security)
  - jsonwebtoken 9.1.2
  - pg 8.11.3 (PostgreSQL driver)
  - prom-client 15.0.0 (Prometheus)
  - sequelize 6.35.2 (ORM)
  - uuid 9.0.1
  - winston 3.11.0 (logging)
  - dotenv 16.3.1
  - cors 2.8.5
  - express-async-errors 3.1.1
- **Dev Dependencies** (12):
  - @typescript-eslint/* (linting)
  - vitest 1.0.4 (testing)
  - supertest 6.3.3 (API testing)
  - tsx 4.7.0 (dev runner)
  - typescript 5.3.3

#### `tsconfig.json` (20 lines)
- **Target**: ES2020
- **Module**: ES2020 (native ESM)
- **Strict Mode**: Enabled
- **Source Maps**: Enabled
- **Declaration Files**: Enabled

#### `vitest.config.ts` (25 lines)
- **Environment**: Node.js
- **Coverage**:
  - Provider: v8
  - Reporters: text, json, html, lcov
  - Threshold: 90% on all metrics
- **Test Files**: tests/**/*.test.ts

#### `.env.example` (25 lines)
- **Server**: PORT, SERVICE_NAME, LOG_LEVEL
- **Database**: HOST, PORT, NAME, USER, PASSWORD
- **JWT**: SECRET, EXPIRY
- **RabbitMQ**: URL, VHOST, QUEUE_NAMES
- **Monitoring**: METRICS_ENABLED, METRICS_PORT, LOG_FORMAT

### Test Files

#### `tests/unit/jwt.test.ts` (65 lines)
- **Test Suites**: 3
- **Test Cases**: 8
- **Coverage**:
  - Token generation
  - Verification success/failure
  - Decoding
  - Tampering detection
  - Expiry handling

#### `tests/unit/auth.test.ts` (120 lines)
- **Test Suites**: 2
- **Test Cases**: 8
- **Coverage**:
  - Missing authorization header
  - Bearer token extraction
  - Invalid token handling
  - Company ID validation
  - Company mismatch detection
  - User context setting

#### `tests/unit/invoiceService.test.ts` (180 lines)
- **Test Suites**: 6
- **Test Cases**: 12
- **Coverage**:
  - Company existence check
  - Invoice creation
  - Invoice retrieval
  - Deletion restrictions
  - Status filtering
  - Pagination

#### `tests/integration/invoices.test.ts` (420 lines)
- **Test Suites**: 6
- **Test Cases**: 16
- **Coverage**:
  - Complete CRUD operations
  - Company isolation enforcement
  - Status transitions
  - Invoice number generation
  - Pagination and filtering
  - Statistics aggregation
  - Error scenarios

#### `tests/setup.ts` (30 lines)
- **Purpose**: Test environment initialization
- **Features**:
  - Database connection setup
  - Pre-test sync
  - Post-test cleanup
  - Transaction rollback

### Documentation

#### `README.md` (800 lines)
- **Sections**:
  1. Features overview
  2. Tech stack details
  3. Project structure
  4. Getting started guide
  5. API documentation
  6. Database schema
  7. RabbitMQ events
  8. Logging configuration
  9. Metrics reference
  10. Testing guide
  11. Deployment options
  12. Company isolation details
  13. Error handling
  14. Troubleshooting
  15. License

#### `ARCHITECTURE.md` (400 lines)
- **Sections**:
  1. System overview with diagram
  2. Core components (4 layers)
  3. Company isolation strategy
  4. Request/response flows
  5. Error handling patterns
  6. Testing strategy
  7. Deployment options
  8. Security considerations
  9. Scalability features
  10. Monitoring setup
  11. Future enhancements

#### `openapi.yaml` (350 lines)
- **Version**: 3.0.0
- **Servers**: Development, Production
- **Paths**: 8 endpoint groups
- **Components**:
  - 3 schemas (Invoice, CreateRequest, UpdateRequest)
  - Security scheme: BearerAuth (JWT)

### Auxiliary Files

#### `Makefile` (50 lines)
- **Commands** (18 total):
  - help, install, dev, build, lint, lint-fix
  - test, test-coverage, test-watch
  - docker-up, docker-down, docker-logs, docker-rebuild, docker-shell
  - clean, seed

#### `.eslintrc.json` (40 lines)
- **Parser**: @typescript-eslint
- **Rules**: 10+ configured
- **Overrides**: Different rules for test files

#### `examples/test-api.sh` (80 lines)
- **Tests** (9):
  1. Health check
  2. Readiness
  3. Create invoice
  4. Get invoice
  5. List invoices
  6. Update invoice
  7. Get statistics
  8. View metrics
  9. Company isolation (should fail)

## Statistics

### Code Metrics
- **Total Files**: 33
- **Total Lines of Code**: ~5,000+
- **Source Code**: ~2,800 lines
- **Test Code**: ~800 lines
- **Configuration**: ~600 lines
- **Documentation**: ~1,200 lines

### File Breakdown by Type
- **TypeScript/JavaScript**: 20 files (~3,500 LOC)
- **Tests**: 5 files (~800 LOC)
- **Documentation**: 3 files (~1,200 LOC)
- **Configuration**: 5 files (~600 LOC)
- **Docker**: 2 files (~130 LOC)

### Coverage Targets
- **Unit Tests**: 12+ test cases
- **Integration Tests**: 16+ test cases
- **Target Coverage**: 90%+ (lines, branches, functions)

## Quick Reference

### Most Important Files
1. **`src/app.ts`** - App entry point
2. **`src/services/invoiceService.ts`** - Business logic
3. **`src/models/Invoice.ts`** - Data model
4. **`src/middleware/auth.ts`** - Security
5. **`src/routes/invoices.ts`** - API endpoints

### Configuration Priority
1. **`.env.example`** - Copy to `.env` first
2. **`docker-compose.yml`** - Start services
3. **`package.json`** - Install dependencies
4. **`tsconfig.json`** - Compile options

### Testing Strategy
1. **Unit Tests**: Isolated logic verification
2. **Integration Tests**: End-to-end flows
3. **Coverage Report**: 90%+ threshold

## Getting Started Workflow

```bash
# 1. Setup
cp .env.example .env
npm install

# 2. Development
docker-compose up -d
npm run dev

# 3. Testing
npm test
npm run test:coverage

# 4. Deployment
npm run build
docker build -t invoicing-service:latest .
docker run -p 8001:8001 invoicing-service:latest
```

## API Quick Reference

```bash
# Health
GET /health
GET /ready

# Invoices (all require Authorization header + company-id)
POST   /api/v1/invoices
GET    /api/v1/invoices
GET    /api/v1/invoices/:id
PATCH  /api/v1/invoices/:id
DELETE /api/v1/invoices/:id
GET    /api/v1/invoices/status/:status
GET    /api/v1/invoices/stats/summary

# Monitoring
GET /metrics
```
