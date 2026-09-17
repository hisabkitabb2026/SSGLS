# InvoiceShelf Performance Optimization Guide

Strategies and best practices for optimizing InvoiceShelf performance for production deployments.

## Table of Contents

1. [Database Query Optimization](#database-query-optimization)
2. [Caching Strategy](#caching-strategy)
3. [Frontend Optimization](#frontend-optimization)
4. [Server Configuration](#server-configuration)
5. [Monitoring & Profiling](#monitoring--profiling)
6. [Common Performance Issues](#common-performance-issues)

## Database Query Optimization

### N+1 Query Prevention

**Problem:** Loading related models without eager loading causes query explosion.

```php
// Bad - N+1 query problem
$invoices = Invoice::all();
foreach ($invoices as $invoice) {
    echo $invoice->customer->name;  // Query per invoice!
}
// Executes: 1 invoice query + N customer queries
```

**Solution:** Use eager loading with `with()`.

```php
// Good - Single query with joins
$invoices = Invoice::with('customer')->get();
foreach ($invoices as $invoice) {
    echo $invoice->customer->name;  // No additional queries
}
// Executes: 1 invoice query + 1 customer query
```

**Best Practices:**

```php
// Load multiple relationships
Invoice::with('customer', 'items', 'payments')->get();

// Nested relationships
Invoice::with('customer.addresses', 'items.unit')->get();

// Conditional eager loading
Invoice::with(['customer' => function ($q) {
    $q->where('status', 'active');
}])->get();

// Lazy eager loading (if load was forgotten)
$invoices = Invoice::all();
$invoices->load('customer', 'items');
```

### Query Scoping & Filtering

Always scope queries to the active company:

```php
// Good - Automatic company scoping
$invoices = Invoice::all();  // Already filtered by company via scope

// Better - Be explicit
$invoices = Invoice::forCompany(auth()->user()->company_id)->get();

// Avoid - Loading all then filtering (wasteful)
$invoices = Invoice::all()->where('company_id', ...);
```

### Indexing Strategy

Ensure proper database indexes:

```php
// Critical indexes in migrations
$table->unsignedInteger('company_id')->index();
$table->unsignedInteger('customer_id')->index();
$table->unsignedInteger('invoice_id')->index();

// Composite indexes for common filters
$table->index(['company_id', 'status']);
$table->index(['company_id', 'created_at']);

// Check indexes
SHOW INDEX FROM invoices;
```

### Query Analysis

Use Laravel Debugbar to identify slow queries:

```php
// In development environment
// Generate N+1 warning
$invoices = Invoice::all();
foreach ($invoices as $invoice) {
    // Debugbar shows query count and execution time
}

// Profile specific query
DB::enableQueryLog();
$invoices = Invoice::with('customer')->get();
dd(DB::getQueryLog());
```

### Pagination for Large Datasets

Never load all records:

```php
// Good - Paginated
$invoices = Invoice::paginate(15);

// Bad - Loading thousands of records
$invoices = Invoice::all();

// Use cursor pagination for very large datasets
$invoices = Invoice::orderBy('id')->cursorPaginate(50);
```

## Caching Strategy

### Query Result Caching

Cache expensive calculations:

```php
// Cache for 1 hour
$dashboardStats = cache()->remember(
    "dashboard.stats.{$company_id}",
    3600,  // 1 hour
    function () {
        return [
            'total_invoices' => Invoice::count(),
            'total_revenue' => Invoice::sum('total'),
            'unpaid_amount' => Invoice::where('status', 'unpaid')->sum('total'),
        ];
    }
);

// Tag cache for selective invalidation
cache()->tags(["company.{$company_id}"])
    ->remember('invoices.total', 3600, function () {
        return Invoice::sum('total');
    });

// Invalidate when invoice changes
cache()->tags(["company.{$invoice->company_id}"])->flush();
```

### Model Query Caching

Cache frequently accessed models:

```php
// Cache list of currencies
$currencies = cache()->rememberForever('currencies:list', function () {
    return Currency::all();
});

// Cache settings
$taxRate = cache()->remember(
    "company.{$company_id}.settings.tax_rate",
    86400,
    function () {
        return CompanySetting::getValue('tax_rate');
    }
);
```

### Cache Drivers

**Development:** Use `file` driver
```env
CACHE_DRIVER=file
```

**Production:** Use `redis` for best performance
```env
CACHE_DRIVER=redis
REDIS_HOST=localhost
REDIS_PORT=6379
```

### Cache Invalidation

Clear cache on relevant changes:

```php
// In service classes
class UpdateInvoiceService {
    public function execute(Invoice $invoice): void {
        // Update logic...

        // Invalidate related caches
        cache()->tags(["invoice.{$invoice->id}"])->flush();
        cache()->tags(["company.{$invoice->company_id}"])->flush();
    }
}

// Or use events
Event::listen(InvoiceUpdated::class, function ($event) {
    cache()->tags(["invoice.{$event->invoice->id}"])->flush();
});
```

## Frontend Optimization

### Asset Bundling & Minification

```bash
# Production build with minification
pnpm build

# Analyze bundle size
npm install -g vite-plugin-visualizer
pnpm build --analyzeBundle
```

### Code Splitting

Vite automatically code-splits by route:

```javascript
// routes.ts - automatic code splitting
const routes = [
  {
    path: '/invoices',
    component: () => import('@/features/invoicing/pages/InvoiceList.vue'),
  },
  {
    path: '/customers',
    component: () => import('@/features/customer/pages/CustomerList.vue'),
  },
];
```

### Image Optimization

```bash
# Install image optimizer
pnpm add -D vite-plugin-image-optimizer

# Configure in vite.config.js
import ViteImageOptimizer from 'vite-plugin-image-optimizer';

export default {
  plugins: [
    ViteImageOptimizer({
      png: { quality: 80 },
      jpeg: { quality: 80 },
      jpg: { quality: 80 },
    }),
  ],
};
```

### Lazy Loading

```vue
<template>
  <Suspense>
    <template #default>
      <InvoiceForm />
    </template>
    <template #fallback>
      <LoadingSpinner />
    </template>
  </Suspense>
</template>

<script setup lang="ts">
import { defineAsyncComponent } from 'vue';

const InvoiceForm = defineAsyncComponent(
  () => import('@/components/InvoiceForm.vue')
);
</script>
```

### HTTP Compression

Enable gzip in web server:

**Nginx:**
```nginx
gzip on;
gzip_types text/plain text/css text/javascript application/json;
gzip_min_length 1000;
```

**Apache:**
```apache
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/plain text/xml
  AddOutputFilterByType DEFLATE text/css text/javascript application/json
</IfModule>
```

## Server Configuration

### PHP Configuration

Optimize PHP for production:

```ini
[PHP]
; Display errors in development, not production
display_errors = Off
error_reporting = E_ALL
log_errors = On
error_log = /var/log/php-errors.log

; Memory and time limits
memory_limit = 256M
max_execution_time = 30
max_input_time = 60

; OPCache - critical for performance
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0  # Production
opcache.revalidate_freq = 0      # Production

; Session handling
session.save_handler = redis
session.save_path = "tcp://127.0.0.1:6379"

; Resource limits
post_max_size = 100M
upload_max_filesize = 100M
```

### Laravel Configuration

```env
# Production settings
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=info

# Cache configuration
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Database connection pooling
DB_POOL_SIZE=10
DB_POOL_TIMEOUT=30

# File permissions
CACHE_PATH=/var/www/storage/cache
LOG_PATH=/var/www/storage/logs
```

### Database Optimization

**MySQL:**
```sql
-- Increase buffer pool (for sufficient RAM)
SET GLOBAL innodb_buffer_pool_size = 8G;

-- Enable query cache (if version < 5.7)
SET GLOBAL query_cache_type = 1;
SET GLOBAL query_cache_size = 256M;

-- Connection pooling
max_connections = 200
```

**PostgreSQL:**
```sql
-- Increase shared buffers
shared_buffers = 256MB

-- Effective cache size
effective_cache_size = 2GB

-- Work memory for operations
work_mem = 16MB

-- Connection pooling
max_connections = 200
```

### Redis Configuration

```conf
# /etc/redis/redis.conf
maxmemory 512mb
maxmemory-policy allkeys-lru
save ""  # Disable persistence (use separate instance for sessions)
appendonly no
```

### Nginx Configuration

```nginx
# Enable gzip
gzip on;
gzip_types text/plain text/css text/xml text/javascript
           application/json application/javascript application/xml+rss;
gzip_min_length 1000;

# Browser caching
location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}

# PHP-FPM connection pooling
upstream php_pool {
    server 127.0.0.1:9000;
    server 127.0.0.1:9001;
    server 127.0.0.1:9002;
    keepalive 32;
}

location ~ \.php$ {
    fastcgi_pass php_pool;
    fastcgi_keep_conn on;
}
```

## Monitoring & Profiling

### Laravel Telescope (Development)

```bash
composer require laravel/telescope --dev
php artisan telescope:install
```

View performance at `/telescope`:

- Query performance
- Request timeline
- Cache operations
- Log messages

### New Relic Monitoring (Production)

```bash
composer require newrelic/newrelic-php-agent
```

Monitor:
- Application response time
- Database query performance
- External API calls
- Error rates

### Query Logging

Enable query logging to identify slow queries:

```php
// In ServiceProvider
if ($this->app->isLocal()) {
    DB::listen(function ($query) {
        if ($query->time > 1000) {  // > 1 second
            \Log::warning('Slow Query', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time,
            ]);
        }
    });
}
```

### Performance Benchmarking

```bash
# Simple load testing with Apache Bench
ab -n 1000 -c 10 http://localhost/api/v1/invoices

# More advanced with Apache Bench
wrk -t4 -c100 -d30s http://localhost/api/v1/invoices

# With custom Lua script
wrk -t4 -c100 -d30s -s script.lua http://localhost
```

## Common Performance Issues

### Issue 1: Slow Invoice List Loading

**Symptom:** `GET /invoices` takes > 2 seconds

**Diagnosis:**

```php
// Check query count
DB::enableQueryLog();
$invoices = Invoice::paginate();
dd(count(DB::getQueryLog()));  // Should be 1-2, not 20+
```

**Solution:**

```php
// Add eager loading
$invoices = Invoice::with('customer', 'items', 'payments')
    ->paginate();

// Add indexes
$table->index(['company_id', 'created_at']);
```

### Issue 2: High Memory Usage

**Symptom:** PHP process uses > 256MB RAM

**Diagnosis:**

```bash
# Check memory usage
ps aux | grep php

# Profile with Xdebug
php -d xdebug.mode=profile artisan tinker
```

**Solution:**

```php
// Use chunking for large datasets
Invoice::chunk(100, function ($invoices) {
    foreach ($invoices as $invoice) {
        // Process and release from memory
    }
});

// Use generators
function getInvoices() {
    foreach (Invoice::lazyById() as $invoice) {
        yield $invoice;
    }
}
```

### Issue 3: Slow PDF Generation

**Symptom:** PDF generation takes > 5 seconds

**Solution:**

```php
// Queue PDF generation
dispatch(new GenerateInvoicePdf($invoice));

// Use Gotenberg (faster than dompdf)
// Set in settings: PDF_DRIVER=gotenberg
```

### Issue 4: High Database Connection Count

**Symptom:** Too many open connections to database

**Solution:**

```php
// Enable connection pooling
// Nginx/Apache should reuse connections

// Close connections explicitly if needed
DB::disconnect();

// Use persistent connections (with caution)
// 'options' => PDO::ATTR_PERSISTENT => true,
```

### Issue 5: Slow Frontend Load

**Symptom:** Initial page load > 3 seconds

**Solution:**

```bash
# Analyze bundle size
pnpm build --analyzeBundle

# Check what's large and consider:
# - Code splitting
# - Lazy loading
# - Tree shaking unused code
# - Using CDN for static assets
```

## Performance Checklist

Before production deployment:

- [ ] Database indexes on `company_id`, `customer_id`, foreign keys
- [ ] Eager loading configured in controllers (no N+1 queries)
- [ ] Caching strategy implemented (Redis for sessions/cache)
- [ ] Frontend assets minified and bundled
- [ ] Gzip compression enabled on web server
- [ ] OPCache enabled in PHP
- [ ] Query logging enabled for slow query detection
- [ ] Database connection pooling configured
- [ ] Static assets cached with far-future expires headers
- [ ] CDN configured for static assets
- [ ] Monitoring (New Relic, DataDog, etc.) set up
- [ ] Load testing performed
- [ ] Database backups automated
- [ ] Log rotation configured
- [ ] Security headers set (HTTPS, CSP, etc.)

## Performance Benchmarks

Target performance metrics:

| Metric | Target | Good | Excellent |
|--------|--------|------|-----------|
| API Response Time | < 500ms | < 300ms | < 100ms |
| Page Load Time | < 2s | < 1s | < 500ms |
| Database Query | < 50ms | < 20ms | < 10ms |
| PDF Generation | < 5s | < 3s | < 1s |
| Memory Usage | < 256MB | < 128MB | < 64MB |
| Cache Hit Rate | > 50% | > 70% | > 90% |
