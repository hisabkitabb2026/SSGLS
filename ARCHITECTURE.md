# InvoiceShelf Architecture Guide

## Table of Contents

1. [High-Level Architecture](#high-level-architecture)
2. [Domain-Driven Design](#domain-driven-design)
3. [Domain Structure](#domain-structure)
4. [Key Design Patterns](#key-design-patterns)
5. [Data Flow](#data-flow)
6. [Database Schema](#database-schema)
7. [Authentication & Authorization](#authentication--authorization)
8. [Multi-Tenancy Architecture](#multi-tenancy-architecture)

## High-Level Architecture

InvoiceShelf follows a modern layered architecture with Domain-Driven Design principles:

```
┌─────────────────────────────────────────────────────────┐
│                      Frontend (Vue 3)                    │
│              Typescript + Pinia + Tailwind              │
└──────────────────────┬──────────────────────────────────┘
                       │ HTTP/REST
                       ▼
┌─────────────────────────────────────────────────────────┐
│                    API Gateway                           │
│    Middleware: Auth, Company, Bouncer, Throttle        │
└──────────────────────┬──────────────────────────────────┘
                       │
        ┌──────────────┼──────────────┐
        ▼              ▼              ▼
  ┌──────────┐  ┌──────────┐  ┌──────────┐
  │ Domain   │  │ Domain   │  │ Domain   │
  │ Layer    │  │ Services │  │ Policies │
  └──────────┘  └──────────┘  └──────────┘
        │              │              │
        └──────────────┼──────────────┘
                       ▼
┌─────────────────────────────────────────────────────────┐
│                  Persistence Layer                      │
│         Eloquent ORM + Repository Pattern              │
└──────────────────────┬──────────────────────────────────┘
                       ▼
┌─────────────────────────────────────────────────────────┐
│            Database (MySQL/PostgreSQL/SQLite)          │
└─────────────────────────────────────────────────────────┘
```

## Domain-Driven Design

InvoiceShelf is organized into six independent domains, each representing a distinct business capability:

### Domain Characteristics

Each domain includes:

```
Domain/
├── Models/                  # Eloquent entities
├── Application/             # Application services (use cases)
├── Adapters/               # Repository implementations
├── Events/                 # Domain events
├── Listeners/              # Event handlers
├── Policies/               # Authorization policies
├── Http/
│   ├── Controllers/        # API controllers
│   ├── Requests/           # Form requests & validation
│   ├── Resources/          # API resources (serialization)
│   └── Routes/             # Domain-specific routes
├── Services/               # Domain services (if needed)
├── Exceptions/             # Domain exceptions
├── {DomainName}ServiceProvider.php
└── routes/api.php          # Domain routes
```

### Domain Benefits

- **Isolation** - Each domain is independently testable
- **Scalability** - Domains can be refactored independently
- **Reusability** - Clear contracts via repositories and services
- **Maintainability** - Cohesive, focused code
- **Team Scaling** - Teams can own specific domains

## Domain Structure

### 1. Invoicing Domain

**Purpose:** Core invoice and payment functionality

**Key Entities:**
- `Invoice` - Main invoice document
- `InvoiceItem` - Line items on invoices
- `Payment` - Payment records
- `RecurringInvoice` - Recurring billing templates

**Key Services:**
- `CreateInvoiceService` - Create new invoices
- `PublishInvoiceService` - Publish invoices to customers
- `RecordPaymentService` - Process payments
- `DuplicateInvoiceService` - Clone existing invoices

**Key Events:**
- `InvoiceCreated` - When invoice is created
- `InvoicePublished` - When invoice is published
- `InvoicePaid` - When invoice is fully paid
- `InvoiceDuplicated` - When invoice is cloned

**API Endpoints:**
- `GET/POST /invoices` - List and create invoices
- `GET/PUT/DELETE /invoices/{invoice}` - Manage single invoice
- `POST /invoices/{invoice}/send` - Send invoice to customer
- `POST /invoices/{invoice}/status` - Change invoice status
- `POST /recurring-invoices` - Manage recurring invoices

### 2. Customer Domain

**Purpose:** Customer and address management

**Key Entities:**
- `Customer` - Customer entity
- `Address` - Customer addresses

**Key Services:**
- Customer creation and updates
- Address management

**API Endpoints:**
- `GET/POST /customers` - List and create customers
- `GET/PUT/DELETE /customers/{customer}` - Manage single customer
- `GET /customers/{customer}/stats` - Customer statistics
- `GET/POST /domain/addresses` - Manage addresses

### 3. Product Domain

**Purpose:** Items and inventory management

**Key Entities:**
- `Item` - Product/service items
- `Unit` - Measurement units (hours, boxes, etc.)

**Key Services:**
- Item CRUD operations
- Unit management

**API Endpoints:**
- `GET/POST /items` - List and create items
- `GET/PUT/DELETE /items/{item}` - Manage single item
- `GET/POST /units` - Manage measurement units

### 4. Expense Domain

**Purpose:** Expense tracking and categorization

**Key Entities:**
- `Expense` - Expense records
- `ExpenseCategory` - Expense categories

**Key Services:**
- Create and track expenses
- Category management
- Receipt file handling

**API Endpoints:**
- `GET/POST /expenses` - List and create expenses
- `GET/PUT/DELETE /expenses/{expense}` - Manage single expense
- `POST /expenses/{expense}/upload/receipts` - Upload receipt
- `GET/POST /categories` - Manage expense categories

### 5. Settings Domain

**Purpose:** System and company configuration

**Key Entities:**
- `CompanySetting` - Key-value company settings
- `User` - User profiles and preferences

**Configuration Areas:**
- Tax types and rates
- Invoice templates
- Email settings
- PDF generation settings
- AI configuration
- File disk assignments

**API Endpoints:**
- `GET/POST /settings` - Global settings
- `GET/POST /company/settings` - Company-specific settings
- `PUT /me/settings` - User preferences

### 6. Transport Domain

**Purpose:** Logistics and transport operations

**Key Entities:**
- `LorryReceipt` - Transport documents
- `LorryPartyProfile` - Party profiles (owner/driver/broker)
- `WarehouseItem` - Goods in storage
- `ConsolidationGroup` - Part-load consolidation
- `LoadTrip` - Truck dispatch records

**Key Features:**
- Lorry receipt management
- Warehouse goods tracking
- Load consolidation
- Trip dispatch and delivery

**API Endpoints:**
- `GET/POST /lorry-receipts` - Manage receipts
- `GET/POST /lorry-party-profiles` - Manage parties
- `GET/POST /warehouse-items` - Track goods
- `GET/POST /consolidation-groups` - Manage consolidation
- `GET/POST /load-trips` - Track deliveries

## Key Design Patterns

### 1. Repository Pattern

**Purpose:** Abstract data persistence

```php
// Interface in Domain
interface InvoiceRepository {
    public function store(Invoice $invoice): void;
    public function find(int $id): ?Invoice;
    public function update(Invoice $invoice): void;
    public function delete(Invoice $invoice): void;
}

// Implementation in Adapters
class EloquentInvoiceRepository implements InvoiceRepository {
    public function store(Invoice $invoice): void {
        InvoiceModel::create($invoice->toArray());
    }
}
```

**Benefits:**
- Decouples business logic from persistence
- Easy to swap implementations (e.g., for testing)
- Clear contracts for data access

### 2. Service Layer Pattern

**Purpose:** Encapsulate complex business logic

```php
class CreateInvoiceService {
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private ItemRepository $itemRepository,
    ) {}

    public function execute(CreateInvoiceDTO $dto): Invoice {
        // Complex business logic here
        $invoice = Invoice::create($dto->toArray());

        foreach ($dto->items as $item) {
            $invoice->addItem($item);
        }

        $this->invoiceRepository->store($invoice);

        event(new InvoiceCreated($invoice));

        return $invoice;
    }
}
```

**Benefits:**
- Separation of concerns
- Reusable across controllers
- Easy to unit test
- Clear business intent

### 3. Domain Events

**Purpose:** Decouple domain operations from side effects

```php
// In domain service
event(new InvoiceCreated($invoice));

// Listener handles side effects
class SendEmailOnInvoicePublished {
    public function handle(InvoicePublished $event): void {
        Mail::to($event->invoice->customer)
            ->send(new InvoicePublishedMail($event->invoice));
    }
}
```

**Benefits:**
- Decouples core logic from side effects
- Enable easy addition of new side effects
- Make business events explicit
- Support async processing via queues

### 4. API Resources (Serialization)

**Purpose:** Consistent API response formatting

```php
class InvoiceResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'total' => $this->total,
            'customer' => new CustomerResource($this->customer),
            'items' => InvoiceItemResource::collection($this->items),
        ];
    }
}
```

**Benefits:**
- Consistent response format
- Hide internal structure from API
- Easy to version API responses
- Automatic JSON serialization

### 5. Authorization via Policies

**Purpose:** Centralized authorization logic

```php
class InvoicePolicy {
    public function update(User $user, Invoice $invoice): bool {
        return $user->company_id === $invoice->company_id
            && $user->can('update-invoice');
    }
}

// In controller
$this->authorize('update', $invoice);
```

**Benefits:**
- Centralized authorization rules
- Easy to audit and test
- Separate from business logic
- Company-level scoping

## Data Flow

### Creating an Invoice - Example Flow

```
1. Frontend (Vue)
   └─> POST /api/v1/invoices
       └─> CreateInvoiceRequest (validation)

2. HTTP Layer (InvoicesController)
   └─> $this->authorize('create', Invoice::class)
   └─> CreateInvoiceService->execute($request->dto())

3. Domain Layer (CreateInvoiceService)
   └─> Create Invoice entity
   └─> Add line items
   └─> InvoiceRepository->store($invoice)
   └─> event(InvoiceCreated::class)

4. Persistence Layer (Repository)
   └─> InvoiceModel::create()
   └─> InvoiceItemModel::create() (batch)

5. Event Handlers (Listeners)
   └─> SendEmailOnInvoiceCreated
   └─> UpdateDashboardMetrics

6. Response
   └─> Return InvoiceResource
   └─> 201 Created status
```

## Database Schema

### Key Tables

```sql
-- Companies (Multi-tenancy root)
companies
  ├── id (INT UNSIGNED PRIMARY KEY)
  ├── name (VARCHAR)
  ├── slug (VARCHAR UNIQUE)
  └── ...

-- Users
users
  ├── id (INT UNSIGNED PRIMARY KEY)
  ├── company_id (INT UNSIGNED FK companies.id)
  ├── email (VARCHAR UNIQUE)
  ├── ...

-- Invoicing Domain
invoices
  ├── id (INT UNSIGNED PRIMARY KEY)
  ├── company_id (INT UNSIGNED FK companies.id)
  ├── customer_id (INT UNSIGNED FK customers.id)
  ├── invoice_number (VARCHAR)
  ├── total (DECIMAL)
  ├── status (ENUM)
  └── ...

invoice_items
  ├── id (INT UNSIGNED PRIMARY KEY)
  ├── invoice_id (INT UNSIGNED FK invoices.id)
  ├── item_id (INT UNSIGNED FK items.id)
  ├── quantity (DECIMAL)
  ├── amount (DECIMAL)
  └── ...

-- Customer Domain
customers
  ├── id (INT UNSIGNED PRIMARY KEY)
  ├── company_id (INT UNSIGNED FK companies.id)
  ├── name (VARCHAR)
  ├── email (VARCHAR)
  └── ...

-- Product Domain
items
  ├── id (INT UNSIGNED PRIMARY KEY)
  ├── company_id (INT UNSIGNED FK companies.id)
  ├── name (VARCHAR)
  ├── description (TEXT)
  └── ...

-- Expense Domain
expenses
  ├── id (INT UNSIGNED PRIMARY KEY)
  ├── company_id (INT UNSIGNED FK companies.id)
  ├── category_id (INT UNSIGNED FK expense_categories.id)
  ├── amount (DECIMAL)
  └── ...

-- Foreign Keys
-- All foreign keys are INT UNSIGNED to match parent PKs
-- No cascading deletes at database level (handled in app)
-- Every company-scoped table has unsignedInteger('company_id') index
```

### Key Constraints

1. **All foreign keys are `unsignedInteger`** - Never use `foreignId()` (which is BIGINT)
2. **No database-level cascading deletes** - Handled in application code
3. **Company scoping** - Every table has `company_id` for multi-tenancy
4. **Soft deletes** - Use `softDeletes()` for non-critical data

## Authentication & Authorization

### Authentication Layers

```
┌──────────────────────────────────────────┐
│        API Routes (/api/v1/)             │
│  Middleware: auth:sanctum, company       │
└────────────────────┬─────────────────────┘
                     │
        ┌────────────┼────────────┐
        ▼            ▼            ▼
    ┌──────────┬──────────┬──────────┐
    │  Web     │   API    │ Customer │
    │ Routes   │ Routes   │ Portal   │
    └──────────┴──────────┴──────────┘
        │          │          │
        ▼          ▼          ▼
    auth:web  auth:sanctum auth:customer
      (JWT)    (Tokens)     (Session)
```

### Authorization Flow

1. **Authentication** - Verify user identity
   - `auth:sanctum` for API
   - Session for web
   - `auth:customer` for customer portal

2. **Company Scoping** - Set active company
   - `CompanyMiddleware` reads `company` header
   - Scopes all queries to company

3. **Authorization** - Check permissions
   - `bouncer` middleware applies Bouncer authorization
   - `InvoicePolicy->update()` called on resource operations
   - Custom middleware for role checks

### Authorization Middleware Stack

```php
Route::middleware([
    'auth:sanctum',      // Require Sanctum token
    'company',           // Load company context
    'bouncer'            // Apply Bouncer authorization
])->group(function () {
    Route::resource('invoices', InvoicesController::class);
});
```

### Roles & Permissions

```
super admin (global)
  └─ Manage all companies
  └─ Manage users
  └─ System settings

owner (company-scoped)
  └─ Full access to company
  └─ Invite users
  └─ Configure settings

custom roles (company-scoped)
  └─ Fine-grained abilities
  └─ Examples: create-invoice, view-payments, manage-customers
```

## Multi-Tenancy Architecture

### Company Isolation

Every request is scoped to a single company:

```php
// middleware/CompanyMiddleware.php
public function handle(Request $request) {
    $company = Company::where('slug', $request->header('company'))->first();

    // All subsequent queries filtered by this company
    app()->instance('company', $company);

    // Via Bouncer scope
    Auth::user()->setCompany($company);
}
```

### Data Isolation Guarantees

1. **Foreign Key** - Every major table has `company_id` FK
2. **Query Scoping** - All models use `company` scope by default
3. **Authorization** - Policies check `company_id` match
4. **API Resources** - Never expose data from other companies

### Example: Customer Retrieval

```php
// Frontend request with company header
GET /api/v1/customers
Header: company: my-company

// In controller
$customers = Customer::where('company_id', auth()->user()->company_id)->get();

// Result: Only customers from 'my-company' returned
```

## Performance Considerations

### Query Optimization

1. **Eager Loading** - Use `with()` to prevent N+1
   ```php
   Invoice::with('customer', 'items', 'payments')->get();
   ```

2. **Indexing** - Index `company_id`, foreign keys, and frequently filtered columns
   ```php
   $table->unsignedInteger('company_id')->index();
   $table->unsignedInteger('customer_id')->index();
   ```

3. **Pagination** - Use cursor or offset pagination for large datasets
   ```php
   Invoice::paginate(15);
   ```

### Caching Strategy

1. **Query Caching** - Cache expensive calculations
   ```php
   cache()->remember("invoices.total.{$company_id}", 3600, fn() => ...);
   ```

2. **Model Caching** - Cache frequently accessed models
3. **View Caching** - Cache compiled Blade templates

### Response Optimization

1. **API Resources** - Serialize only needed fields
2. **Pagination** - Limit default page size
3. **Compression** - gzip enabled for API responses

## Summary

InvoiceShelf combines proven architectural patterns:

- **Domain-Driven Design** for business logic organization
- **Repository Pattern** for data persistence abstraction
- **Service Layer** for complex operations
- **Domain Events** for decoupled side effects
- **Multi-Tenancy** for company data isolation
- **Policy Authorization** for fine-grained access control
- **API Resources** for consistent API responses

This architecture enables the system to be:
- **Scalable** - Add new features without affecting existing code
- **Maintainable** - Clear separation of concerns
- **Testable** - Each layer can be tested in isolation
- **Secure** - Authorization at multiple levels
- **Performant** - Optimized queries and caching
