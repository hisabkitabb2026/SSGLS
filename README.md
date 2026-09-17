# InvoiceShelf - Open Source Invoice & Expense Management

A modern, open-source invoicing and expense tracking application built with Laravel 13 (PHP 8.4) and Vue 3. InvoiceShelf provides multi-company tenancy, customer portals, recurring invoices, and comprehensive financial management features.

## Key Features

### Core Functionality
- **Professional Invoicing** - Create, send, and track invoices with customizable templates
- **Expense Management** - Track expenses with categorization and receipt uploads
- **Customer Management** - Maintain customer profiles, addresses, and transaction history
- **Payment Tracking** - Record and monitor payments across multiple payment methods
- **Recurring Invoices** - Automate billing with configurable recurring invoice schedules
- **Estimates & Quotes** - Create professional estimates and convert them to invoices
- **Multi-Currency Support** - Handle international transactions with automatic exchange rates

### Advanced Features
- **Customer Portal** - Secure portal for customers to view invoices, estimates, and payments
- **Multi-Company Tenancy** - Manage multiple companies within a single installation
- **PDF Generation** - Generate professional PDFs with support for multiple rendering engines
- **Custom Fields** - Add custom fields to invoices, customers, and expenses
- **AI-Powered Features** - Integrate AI for document analysis and content generation
- **Role-Based Access Control** - Fine-grained permissions with customizable roles
- **Transport Module** - Specialized features for logistics and transport businesses
- **Warehouse Management** - Track goods storage and consolidation operations

### Administration
- **User Management** - Create and manage team members with role-based access
- **File Disk Management** - Configure local, S3, Dropbox, and DigitalOcean Spaces storage
- **Email Configuration** - Global and per-company email settings with custom templates
- **PDF Configuration** - Choose between dompdf and Gotenberg for rendering
- **Font Management** - Install language-specific font packages for multilingual PDFs
- **Database Backups** - Automated and manual backup functionality
- **Module System** - Extensible modular architecture for additional functionality

## Tech Stack

### Backend
- **Framework:** Laravel 13
- **Language:** PHP 8.4
- **Database:** MySQL, PostgreSQL, or SQLite (fully compatible)
- **Authentication:** Laravel Sanctum (API), Session (Web)
- **Authorization:** Silber/Bouncer (roles and permissions)
- **File Storage:** Spatie MediaLibrary with pluggable drivers
- **PDF Generation:** dompdf (default) or Gotenberg (headless Chromium)
- **Email:** Symfony Mailer with multiple provider support

### Frontend
- **Framework:** Vue 3 with TypeScript
- **State Management:** Pinia
- **Routing:** vue-router
- **Styling:** Tailwind CSS v4
- **Build Tool:** Vite
- **HTTP Client:** Axios
- **UI Components:** Headless UI, Heroicons

## Quick Start

### Requirements
- PHP 8.4+
- Composer
- Node.js 24+
- pnpm 11.6+
- SQLite, MySQL, or PostgreSQL

### Installation

1. **Clone the Repository**
   ```bash
   git clone https://github.com/invoiceshelf/invoiceshelf.git
   cd invoiceshelf
   ```

2. **Install PHP Dependencies**
   ```bash
   composer install
   ```

3. **Install Frontend Dependencies**
   ```bash
   pnpm install
   ```

4. **Configure Environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Database Setup**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

6. **Build Frontend**
   ```bash
   pnpm build
   ```

7. **Run Development Server**
   ```bash
   composer run dev
   ```

Access the application at `http://invoiceshelf.test` (requires host entry) or `http://localhost`.

### Using Docker

The project includes Docker Compose configuration for a complete local development stack:

```bash
# Interactive setup
./devenv

# Start services
./devenv start

# Stop services
./devenv stop

# Access application
open http://invoiceshelf.test
```

For more details, see [SETUP.md](./SETUP.md)

## Documentation

- **[ARCHITECTURE.md](./ARCHITECTURE.md)** - System design, domain structure, and design patterns
- **[API.md](./API.md)** - Complete API reference with endpoints and examples
- **[SETUP.md](./SETUP.md)** - Installation and configuration guide
- **[EXTENDING.md](./EXTENDING.md)** - Guide for extending the application
- **[PERFORMANCE.md](./PERFORMANCE.md)** - Performance optimization strategies
- **[TESTING.md](./TESTING.md)** - Testing guide and best practices
- **[CLAUDE.md](./CLAUDE.md)** - AI agent instructions for development

## Project Structure

```
InvoiceShelf/
├── app/
│   ├── Domains/              # Domain-Driven Design domains
│   │   ├── Customer/         # Customer management
│   │   ├── Product/          # Product and items
│   │   ├── Invoicing/        # Invoice operations
│   │   ├── Expense/          # Expense management
│   │   ├── Settings/         # System settings
│   │   └── Transport/        # Logistics features
│   ├── Http/                 # HTTP Controllers and Requests
│   ├── Services/             # Business logic services
│   ├── Models/               # Eloquent models
│   └── Support/              # Helpers and traits
├── config/                   # Configuration files
├── database/                 # Migrations and seeders
├── resources/
│   ├── scripts/              # Vue 3 frontend
│   ├── css/                  # Tailwind and theme styles
│   └── lang/                 # Internationalization
├── routes/                   # API and web routes
├── tests/                    # Test suite
└── docker/                   # Docker configuration
```

## Development Commands

```bash
# Development server with hot reload
composer run dev

# Run tests
php artisan test --compact

# Format code
composer lint:fix

# Frontend linting
pnpm lint:fix

# Build frontend
pnpm build
```

## Key Concepts

### Multi-Tenancy
Every major model includes a `company_id` foreign key. The `CompanyMiddleware` sets the active company from the request header, isolating data by company.

### Domain-Driven Design
The application is organized into six domains, each with its own:
- Entities and Value Objects
- Application Services
- Repository Interfaces and Adapters
- Domain Events
- Policies and Authorization

### API Architecture
- RESTful API under `/api/v1/`
- Laravel Sanctum for token-based authentication
- Eloquent API Resources for consistent responses
- Comprehensive validation via Form Requests

### Authorization
- Role-based access control via Silber/Bouncer
- Company-level authorization scopes
- Fine-grained ability-based permissions
- Super Admin for global platform administration

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](./CONTRIBUTING.md) for guidelines.

## License

InvoiceShelf is open source software licensed under the [MIT license](./LICENSE).

## Support

For issues, feature requests, and discussions:
- GitHub Issues: [InvoiceShelf/invoiceshelf](https://github.com/invoiceshelf/invoiceshelf)
- Documentation: [docs.invoiceshelf.com](https://docs.invoiceshelf.com)
- Community: [InvoiceShelf Community](https://community.invoiceshelf.com)

## Project Status

InvoiceShelf is in active development. All domains have been refactored to Domain-Driven Design with comprehensive test coverage. The application is production-ready for small to medium-sized businesses.

## Version

Current Version: 3.0.0+
Last Updated: August 2026

---

**Built with care by the InvoiceShelf Community**
