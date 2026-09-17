# InvoiceShelf Setup Guide

Complete guide for setting up InvoiceShelf for local development or production deployment.

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Local Development Setup](#local-development-setup)
3. [Docker Development Setup](#docker-development-setup)
4. [Database Configuration](#database-configuration)
5. [Environment Configuration](#environment-configuration)
6. [Running the Application](#running-the-application)
7. [Running Tests](#running-tests)
8. [Production Deployment](#production-deployment)
9. [Troubleshooting](#troubleshooting)

## System Requirements

### Minimum Requirements

- **PHP:** 8.4+
- **Composer:** 2.2+
- **Node.js:** 24+
- **pnpm:** 11.6+
- **Database:** MySQL 8.0+, PostgreSQL 12+, or SQLite 3.35+

### Recommended

- **PHP:** 8.4 with opcache enabled
- **MySQL:** 8.0+ or PostgreSQL 15+
- **Node.js:** 24 LTS
- **RAM:** 2GB minimum
- **Storage:** 5GB minimum
- **Web Server:** Nginx 1.24+ or Apache 2.4.41+

### PHP Extensions Required

```
bcmath
ctype
curl
dom
fileinfo
filter
ftp
gd
hash
iconv
intl
json
libxml
mbstring
openssl
pcre
pdo
pdo_sqlite (for testing)
pdo_mysql (for MySQL)
pdo_pgsql (for PostgreSQL)
posix
sockets
tokenizer
xml
xmlreader
xmlwriter
zip
```

Check installed extensions:
```bash
php -m
```

## Local Development Setup

### Step 1: Clone the Repository

```bash
git clone https://github.com/invoiceshelf/invoiceshelf.git
cd invoiceshelf
```

### Step 2: Install PHP Dependencies

```bash
composer install
```

If you encounter permission issues:
```bash
sudo composer install
```

### Step 3: Install Frontend Dependencies

Ensure Node.js 24+ and pnpm are installed:

```bash
node --version  # Should be v24.0.0 or higher
npm install -g pnpm
pnpm --version # Should be 11.6+
```

Install dependencies:
```bash
pnpm install
```

### Step 4: Configure Environment

Copy the example environment file:

```bash
cp .env.example .env
```

Generate application key:
```bash
php artisan key:generate
```

### Step 5: Configure Database

Edit `.env` with your database credentials:

**For SQLite (recommended for development):**
```env
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/database/database.sqlite
```

**For MySQL:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=invoiceshelf
DB_USERNAME=root
DB_PASSWORD=password
```

**For PostgreSQL:**
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=invoiceshelf
DB_USERNAME=postgres
DB_PASSWORD=password
```

Create database if needed:
```bash
# MySQL
mysql -u root -p -e "CREATE DATABASE invoiceshelf;"

# PostgreSQL
psql -U postgres -c "CREATE DATABASE invoiceshelf;"
```

### Step 6: Initialize Database

Run migrations to create tables:

```bash
php artisan migrate
```

Seed with demo data (optional):
```bash
php artisan db:seed
```

### Step 7: Build Frontend Assets

```bash
pnpm build
```

### Step 8: Start Development Server

Run the complete development environment:

```bash
composer run dev
```

This starts:
- PHP development server (port 8000)
- Queue listener
- Log tail
- Vite dev server (Vite configured for `invoiceshelf.test`)

Access the application:
- Web: http://invoiceshelf.test (add to `/etc/hosts`)
- Or: http://localhost:8000

### Accessing the Application

If using `invoiceshelf.test`, add to your hosts file:

**macOS/Linux:**
```bash
echo "127.0.0.1 invoiceshelf.test" | sudo tee -a /etc/hosts
```

**Windows (as Administrator):**
```
C:\Windows\System32\drivers\etc\hosts

Add line: 127.0.0.1 invoiceshelf.test
```

## Docker Development Setup

### Quick Start

The project includes `devenv` script for Docker Compose management:

```bash
# Interactive setup (choose database)
./devenv

# Start services
./devenv start

# Stop services
./devenv stop

# View logs
./devenv logs

# Open shell in container
./devenv shell

# Run tests
./devenv test

# Format code
./devenv format

# Rebuild containers
./devenv rebuild
```

### Manual Docker Setup

If you prefer manual Docker commands:

```bash
# Build images
docker compose -f docker/development/docker-compose.yml build

# Start services
docker compose -f docker/development/docker-compose.yml up -d

# Run migrations
docker compose -f docker/development/docker-compose.yml exec app \
  php artisan migrate

# Seed database
docker compose -f docker/development/docker-compose.yml exec app \
  php artisan db:seed
```

### Docker Services

- **Web:** Nginx (port 8080) or Your configured port
- **PHP:** 8.4 with all extensions
- **Database:** MySQL/PostgreSQL/SQLite
- **Redis:** For caching and queues
- **Adminer:** Database browser (port 8080)
- **Mailpit:** Email testing (port 8025)

### Database GUI Access

After starting with `./devenv start`:

- **Adminer:** http://localhost:8080
- **Mailpit:** http://localhost:8025

## Database Configuration

### Supported Databases

All three databases are fully supported and tested:

| Feature | MySQL | PostgreSQL | SQLite |
|---------|-------|-----------|--------|
| Development | Yes | Yes | Yes |
| Testing | Yes | Yes | Yes (preferred) |
| Production | Yes | Yes | No |
| Multi-tenancy | Yes | Yes | Yes |

### Database Initialization

All databases support the same schema via Eloquent migrations.

```bash
# Run all pending migrations
php artisan migrate

# Seed with demo data
php artisan db:seed

# Reset database (dev only!)
php artisan migrate:fresh --seed

# Rollback migrations
php artisan migrate:rollback
```

### Database Schema Overview

Key tables and relationships:

```sql
-- Multi-tenancy root
companies (id, name, slug, ...)
users (id, company_id, email, ...)

-- Core entities
invoices (id, company_id, customer_id, invoice_number, total, status)
invoice_items (id, invoice_id, item_id, quantity, amount)
customers (id, company_id, name, email)
items (id, company_id, name, price)
expenses (id, company_id, category_id, amount)
payments (id, invoice_id, amount, payment_date)

-- Configuration
company_settings (id, company_id, key, value)
currencies (id, code, name, symbol)
tax_types (id, company_id, name, percent)
```

All company-scoped tables include `company_id` foreign key for isolation.

## Environment Configuration

### Key Environment Variables

```env
# Application
APP_NAME=InvoiceShelf
APP_ENV=local|production
APP_DEBUG=true|false
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=sqlite|mysql|pgsql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=invoiceshelf
DB_USERNAME=root
DB_PASSWORD=

# Cache
CACHE_DRIVER=file
CACHE_TTL=3600

# Queue
QUEUE_CONNECTION=sync|database|redis

# Mail
MAIL_MAILER=log|smtp|mailgun
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=465
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@example.com

# AI Configuration (optional)
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...

# File Storage
FILESYSTEMS_DISK=local

# Redis (if using)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Development-Specific Settings

For local development, these settings are recommended:

```env
APP_ENV=local
APP_DEBUG=true
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
MAIL_MAILER=log
```

### Production-Specific Settings

For production deployment:

```env
APP_ENV=production
APP_DEBUG=false
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
MAIL_MAILER=smtp
```

## Running the Application

### Development Server

Start all services with single command:

```bash
composer run dev
```

This concurrently runs:
1. Laravel PHP server
2. Queue listener
3. Log tail (pail)
4. Vite dev server

### Frontend Development Only

If Laravel is running separately, start frontend dev server:

```bash
pnpm dev
```

The Vite dev server will run with hot module replacement (HMR).

### Production Build

Create optimized frontend bundle:

```bash
pnpm build
```

Output goes to `public/build/` directory.

### Accessing the Application

**Development (without host entry):**
```
http://localhost:8000
http://localhost (if Nginx configured)
```

**Development (with host entry):**
```
http://invoiceshelf.test
```

**Production:**
```
https://your-domain.com
```

### Initial Login

After setup, you'll have demo users:

```
Email: demo@invoiceshelf.com
Password: Demo@123456
```

Or create a super admin:

```bash
php artisan tinker
```

```php
User::factory()->create([
    'email' => 'admin@invoiceshelf.com',
    'password' => Hash::make('password'),
    'super_admin' => true,
]);
```

## Running Tests

### Test Framework

InvoiceShelf uses **Pest** for testing with Laravel plugin.

### Running Tests

```bash
# Run all tests
php artisan test --compact

# Run tests with coverage
php artisan test --coverage

# Run specific test file
php artisan test tests/Feature/Invoice/CreateInvoiceTest.php

# Run tests matching pattern
php artisan test --filter=testCanCreateInvoice

# Run tests without stopping on first failure
php artisan test --no-stop-on-failure

# Run via Pest directly
./vendor/bin/pest
```

### Test Structure

```
tests/
├── Feature/
│   ├── Invoicing/
│   ├── Customer/
│   ├── Expense/
│   └── ...
├── Unit/
│   ├── Services/
│   ├── Models/
│   └── ...
└── Pest.php (configuration)
```

### Writing Tests

Test example:

```php
<?php

use App\Models\Invoice;
use App\Models\Company;
use Laravel\Sanctum\Sanctum;

describe('Invoice Operations', function () {
    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->user = $this->company->users()->factory()->create();
        Sanctum::actingAs($this->user);
    });

    test('can create invoice', function () {
        $response = $this->postJson('/api/v1/invoices', [
            'customer_id' => 1,
            'invoice_date' => '2024-08-01',
            'items' => []
        ], [
            'Company' => $this->company->slug
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'data']);
    });
});
```

### Database for Tests

Tests use SQLite in-memory database (configured in `phpunit.xml`):

```php
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

This ensures:
- Fast test execution
- Isolation between tests
- No database cleanup needed

## Production Deployment

### Pre-Deployment Checklist

- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Generate unique `APP_KEY`
- [ ] Configure database credentials
- [ ] Configure mail driver
- [ ] Set up Redis (if using)
- [ ] Configure file storage (S3, local, etc.)
- [ ] Run migrations on production database
- [ ] Build frontend assets
- [ ] Configure web server (Nginx/Apache)
- [ ] Set up SSL certificate
- [ ] Configure backup strategy

### Deployment Steps

1. **Clone repository**
   ```bash
   git clone https://github.com/invoiceshelf/invoiceshelf.git
   cd invoiceshelf
   ```

2. **Install dependencies**
   ```bash
   composer install --no-dev
   pnpm install --production
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   # Edit .env with production settings
   ```

4. **Build frontend**
   ```bash
   pnpm build
   ```

5. **Database migration**
   ```bash
   php artisan migrate --force
   php artisan db:seed --force
   ```

6. **Cache configuration**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

7. **Set permissions**
   ```bash
   chmod -R 775 storage bootstrap/cache
   chmod -R 644 public
   ```

8. **Start queue worker**
   ```bash
   php artisan queue:work --daemon
   ```

### Nginx Configuration

```nginx
server {
    listen 80;
    server_name invoiceshelf.com;

    root /path/to/invoiceshelf/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### Apache Configuration

```apache
<VirtualHost *:80>
    ServerName invoiceshelf.com
    DocumentRoot /path/to/invoiceshelf/public

    <Directory /path/to/invoiceshelf/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted

        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule ^ index.php [QSA,L]
        </IfModule>
    </Directory>

    <FilesMatch \.php$>
        SetHandler application/x-httpd-php
    </FilesMatch>
</VirtualHost>
```

## Troubleshooting

### Common Issues

#### 1. "Class 'PDO' not found"
PHP PDO extension not installed.
```bash
php -m | grep -i pdo  # Check if installed
# Install for your OS
```

#### 2. "Permission denied" on storage/logs
```bash
chmod -R 775 storage bootstrap/cache
```

#### 3. "Target class Controller does not exist"
Clear cached routes:
```bash
php artisan route:clear
php artisan cache:clear
```

#### 4. Frontend not compiling
```bash
pnpm install
pnpm build
# Or delete node_modules and reinstall
rm -rf node_modules pnpm-lock.yaml
pnpm install
```

#### 5. Database connection error
Verify `.env` database configuration:
```bash
php artisan tinker
DB::connection()->getPdo();  # Should not error
```

#### 6. "SQLSTATE[HY000]: General error: 1030 Got an error"
MySQL table space issue. Check disk space:
```bash
df -h
```

#### 7. Queue not processing
Check queue connection in `.env`:
```bash
# For sync (immediate processing)
QUEUE_CONNECTION=sync

# For database queue
php artisan queue:work

# For Redis
QUEUE_CONNECTION=redis
php artisan queue:work
```

### Debug Mode

Enable debug mode for detailed error messages:

```env
APP_DEBUG=true
```

View logs:
```bash
tail -f storage/logs/laravel.log
```

### Health Check

Run health check command:
```bash
php artisan tinker
app()->make('App\Services\HealthCheckService')->check();
```

### Support

For issues and questions:
- GitHub Issues: https://github.com/invoiceshelf/invoiceshelf/issues
- Documentation: https://docs.invoiceshelf.com
- Community: https://community.invoiceshelf.com
