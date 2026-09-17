# InvoiceShelf Testing Guide

Comprehensive guide for writing, running, and maintaining tests in InvoiceShelf.

## Table of Contents

1. [Testing Framework](#testing-framework)
2. [Test Structure](#test-structure)
3. [Running Tests](#running-tests)
4. [Writing Feature Tests](#writing-feature-tests)
5. [Writing Unit Tests](#writing-unit-tests)
6. [Mocking & Fixtures](#mocking--fixtures)
7. [Best Practices](#best-practices)
8. [Coverage Goals](#coverage-goals)

## Testing Framework

InvoiceShelf uses **Pest** - a elegant testing framework for PHP built on top of PHPUnit.

### Why Pest?

- **Expressive syntax** - Tests read like documentation
- **Higher-order tests** - Reusable test blocks
- **Built-in datasets** - Parameterized testing
- **Automatic expectations** - Less boilerplate
- **Fluent assertions** - Natural language assertions

## Test Structure

### Directory Layout

```
tests/
├── Feature/                    # End-to-end tests
│   ├── Invoicing/
│   │   ├── CreateInvoiceTest.php
│   │   ├── PublishInvoiceTest.php
│   │   └── RecordPaymentTest.php
│   ├── Customer/
│   ├── Expense/
│   └── ...
├── Unit/                       # Business logic tests
│   ├── Services/
│   │   └── CreateInvoiceServiceTest.php
│   ├── Models/
│   │   └── InvoiceTest.php
│   └── ...
├── Fixtures/                   # Test data
│   ├── factories.php
│   └── seeders.php
└── Pest.php                    # Configuration
```

### Test Naming Convention

- Feature tests: `{Feature}Test.php` (e.g., `CreateInvoiceTest.php`)
- Unit tests: `{Component}Test.php` (e.g., `CreateInvoiceServiceTest.php`)
- Test methods: `test{description}` (e.g., `testCanCreateInvoice`)

## Running Tests

### Run All Tests

```bash
php artisan test --compact
```

### Run Specific Test File

```bash
php artisan test tests/Feature/Invoicing/CreateInvoiceTest.php
```

### Run Tests Matching Pattern

```bash
php artisan test --filter=CreateInvoice
php artisan test --filter=testCanCreate
```

### Run with Coverage

```bash
php artisan test --coverage
php artisan test --coverage --min=80  # Minimum coverage percentage
```

### Run Tests in Parallel

```bash
php artisan test --parallel
```

### Stop on First Failure

```bash
php artisan test --stop-on-failure
```

### Watch Mode (Re-run on file change)

```bash
php artisan test --watch
```

## Writing Feature Tests

Feature tests verify complete features through HTTP requests.

### Basic Structure

```php
<?php

use App\Models\Invoice;
use App\Models\Company;
use Laravel\Sanctum\Sanctum;

describe('Invoice Management', function () {
    beforeEach(function () {
        // Setup for each test
        $this->company = Company::factory()->create();
        $this->user = $this->company->users()->factory()->create();
        Sanctum::actingAs($this->user);
    });

    test('can retrieve invoices', function () {
        Invoice::factory(3)->create(['company_id' => $this->company->id]);

        $response = $this->getJson('/api/v1/invoices', [
            'Company' => $this->company->slug,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'meta'])
            ->assertJsonCount(3, 'data');
    });

    test('can create invoice', function () {
        $data = [
            'customer_id' => 1,
            'invoice_date' => '2024-08-01',
            'due_date' => '2024-09-01',
            'items' => [
                [
                    'item_id' => 1,
                    'quantity' => 2,
                    'price' => 100.00,
                ]
            ],
        ];

        $response = $this->postJson('/api/v1/invoices', $data, [
            'Company' => $this->company->slug,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('invoices', [
            'company_id' => $this->company->id,
            'customer_id' => 1,
        ]);
    });

    test('cannot create invoice without permission', function () {
        $user = $this->company->users()->factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/invoices', [], [
            'Company' => $this->company->slug,
        ]);

        $response->assertStatus(403);
    });
});
```

### Testing Authorization

```php
test('cannot view invoice from different company', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => 2,  // Different company
    ]);

    Sanctum::actingAs($this->user);  // User from company 1

    $response = $this->getJson("/api/v1/invoices/{$invoice->id}", [
        'Company' => $this->company->slug,
    ]);

    $response->assertStatus(403);  // Forbidden
});
```

### Testing State Changes

```php
test('can change invoice status from draft to published', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'status' => 'draft',
    ]);

    $response = $this->postJson(
        "/api/v1/invoices/{$invoice->id}/status",
        ['status' => 'published'],
        ['Company' => $this->company->slug]
    );

    $response->assertStatus(200);
    $this->assertTrue($invoice->fresh()->status === 'published');
});
```

### Testing Validation

```php
test('validation fails with missing required fields', function () {
    $response = $this->postJson('/api/v1/invoices', [
        'customer_id' => 1,
        // Missing invoice_date, due_date, etc.
    ], [
        'Company' => $this->company->slug,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['invoice_date', 'due_date'])
        ->assertJsonPath('errors.invoice_date.0', 'The invoice date field is required.');
});
```

### Testing Events

```php
test('publishes event when invoice is created', function () {
    Event::fake();

    $this->postJson('/api/v1/invoices', $data, [
        'Company' => $this->company->slug,
    ]);

    Event::assertDispatched(InvoiceCreated::class);
});
```

### Testing Pagination

```php
test('paginated response includes metadata', function () {
    Invoice::factory(20)->create(['company_id' => $this->company->id]);

    $response = $this->getJson('/api/v1/invoices?per_page=10&page=1', [
        'Company' => $this->company->slug,
    ]);

    $response->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 20)
        ->assertJsonCount(10, 'data');
});
```

## Writing Unit Tests

Unit tests verify business logic in isolation.

### Testing Services

```php
<?php

use App\Domains\Invoicing\Application\CreateInvoiceService;
use App\Domains\Invoicing\Models\Invoice;

describe('CreateInvoiceService', function () {
    test('creates invoice with items', function () {
        $service = new CreateInvoiceService(
            new EloquentInvoiceRepository(),
            new EloquentItemRepository(),
        );

        $invoice = $service->execute(new CreateInvoiceDTO(
            company_id: 1,
            customer_id: 1,
            invoice_date: '2024-08-01',
            items: [
                ['item_id' => 1, 'quantity' => 2, 'price' => 100],
            ],
        ));

        expect($invoice->id)->toBeGreaterThan(0)
            ->and($invoice->total)->toBe(200);
    });

    test('emits domain event when invoice created', function () {
        Event::fake();

        $service->execute($dto);

        Event::assertDispatched(InvoiceCreated::class);
    });
});
```

### Testing Models

```php
<?php

use App\Models\Invoice;

describe('Invoice Model', function () {
    test('scopes invoices by company', function () {
        Invoice::factory()->create(['company_id' => 1]);
        Invoice::factory()->create(['company_id' => 2]);

        $invoices = Invoice::forCompany(1)->get();

        expect($invoices)->toHaveCount(1)
            ->and($invoices->first()->company_id)->toBe(1);
    });

    test('calculates total correctly', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory(2)->state([
                'quantity' => 2,
                'amount' => 100,
            ]))
            ->create();

        expect($invoice->total)->toBe(400);  // 2 items * 2 qty * 100
    });

    test('marks as paid when full amount recorded', function () {
        $invoice = Invoice::factory()->create(['total' => 1000]);

        $invoice->recordPayment(1000);

        expect($invoice->status)->toBe('paid');
    });
});
```

### Testing Policies

```php
<?php

use App\Models\User;
use App\Models\Invoice;

describe('InvoicePolicy', function () {
    test('owner can update own company invoices', function () {
        $invoice = Invoice::factory()->create();
        $owner = $invoice->company->owner;

        expect($owner->can('update', $invoice))->toBeTrue();
    });

    test('user cannot update invoice from other company', function () {
        $invoice = Invoice::factory()->create(['company_id' => 1]);
        $user = User::factory()->create(['company_id' => 2]);

        expect($user->can('update', $invoice))->toBeFalse();
    });
});
```

## Mocking & Fixtures

### Factory Usage

Factories generate test data:

```php
// Single model
$invoice = Invoice::factory()->create();

// Multiple models
$invoices = Invoice::factory(5)->create();

// With specific attributes
$invoice = Invoice::factory()->create([
    'company_id' => 1,
    'status' => 'published',
]);

// Relationships
$invoice = Invoice::factory()
    ->for(Customer::factory())
    ->hasItems(3)
    ->create();
```

### Faker Data

Generate realistic data:

```php
$invoice = Invoice::factory()->create([
    'invoice_number' => fake()->unique()->numberBetween(1000, 9999),
    'invoice_date' => fake()->dateTime(),
    'customer_name' => fake()->name(),
    'email' => fake()->email(),
]);
```

### Mocking Services

```php
test('uses mocked payment service', function () {
    $paymentService = Mockery::mock(PaymentService::class);
    $paymentService->shouldReceive('process')
        ->once()
        ->with(100)
        ->andReturn(true);

    app()->instance(PaymentService::class, $paymentService);

    // Test code that uses PaymentService
});

// Or with Laravel's mock helper
$service = mock(PaymentService::class)
    ->shouldReceive('process')
    ->andReturn(true)
    ->getMock();
```

### Mocking HTTP Requests

```php
test('calls external API', function () {
    Http::fake([
        'api.example.com/*' => Http::response([
            'status' => 'success',
        ]),
    ]);

    // Code that makes HTTP request
    $result = app(ExternalService::class)->fetch();

    expect($result['status'])->toBe('success');
    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.com/data';
    });
});
```

### Database Transactions

Tests automatically rollback database changes:

```php
// Test data is isolated per test
test('first test creates invoice', function () {
    $invoice = Invoice::factory()->create();
    expect(Invoice::count())->toBe(1);
});

test('second test starts fresh', function () {
    expect(Invoice::count())->toBe(0);  // Previous data was rolled back
});
```

## Best Practices

### 1. Arrange-Act-Assert Pattern

Structure tests clearly:

```php
test('can record payment', function () {
    // Arrange - Setup test data
    $invoice = Invoice::factory()->create(['total' => 1000]);

    // Act - Perform the action
    $response = $this->postJson(
        '/api/v1/payments',
        ['invoice_id' => $invoice->id, 'amount' => 1000],
        ['Company' => $this->company->slug]
    );

    // Assert - Verify results
    $response->assertStatus(201);
    expect($invoice->fresh()->status)->toBe('paid');
});
```

### 2. Test One Thing

Each test should verify a single behavior:

```php
// Good - Tests single behavior
test('cannot create invoice with invalid amount', function () {
    $response = $this->postJson('/api/v1/invoices', [
        'amount' => -100,  // Invalid
    ]);

    $response->assertStatus(422);
});

// Bad - Tests multiple things
test('invoice creation validation', function () {
    // Tests customer_id, amount, date, items, etc.
    // Too many assertions in one test
});
```

### 3. Descriptive Test Names

Test names should describe the behavior:

```php
// Good
test('cannot create invoice without customer')
test('publishes email when invoice sent')
test('prevents access to other company invoices')

// Bad
test('test invoice creation')
test('test authorization')
test('validation')
```

### 4. Use Shared Setup

Avoid repetition with `beforeEach`:

```php
describe('Invoice Operations', function () {
    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->user = $this->company->users()->factory()->create();
        Sanctum::actingAs($this->user);
    });

    test('can create invoice', function () {
        // Can now use $this->company and $this->user
    });

    test('can update invoice', function () {
        // Same setup applied automatically
    });
});
```

### 5. Test Negative Cases

Test what should fail:

```php
// Positive case
test('can create invoice with valid data', function () {
    $response = $this->postJson('/api/v1/invoices', validInvoiceData());
    $response->assertStatus(201);
});

// Negative cases
test('cannot create invoice without customer', function () {
    $response = $this->postJson('/api/v1/invoices', [
        // Missing customer_id
    ]);
    $response->assertStatus(422);
});

test('cannot create invoice with past due date', function () {
    $response = $this->postJson('/api/v1/invoices', [
        'due_date' => now()->subDay(),
    ]);
    $response->assertStatus(422);
});
```

### 6. Test at the Right Level

Use feature tests for workflows, unit tests for logic:

```php
// Feature test - Tests complete workflow
test('can send invoice and record payment', function () {
    $response = $this->postJson('/api/v1/invoices/1/send', ...);
    $response = $this->postJson('/api/v1/payments', ...);
});

// Unit test - Tests individual service
test('service calculates tax correctly', function () {
    $service = new CalculateTaxService();
    $tax = $service->calculate(1000, 0.1);
    expect($tax)->toBe(100);
});
```

## Coverage Goals

### Target Coverage

- **Overall:** Minimum 80% code coverage
- **Critical paths:** 100% (invoicing, payments, auth)
- **Services:** 100% unit test coverage
- **Controllers:** 100% feature test coverage
- **Models:** 90%+ test coverage

### Measuring Coverage

```bash
# Generate coverage report
php artisan test --coverage

# HTML coverage report
php artisan test --coverage --coverage-html=coverage/

# Specific minimum
php artisan test --coverage --min=80
```

### Coverage by Domain

| Domain | Target | Recommended |
|--------|--------|-------------|
| Invoicing | 100% | Critical path |
| Customer | 90% | High importance |
| Expense | 85% | Medium importance |
| Product | 80% | Support functionality |
| Settings | 70% | Configuration |
| Transport | 85% | Specialized domain |

### Test Examples by Coverage

```php
// Service coverage - Critical
test('service executes correctly')
test('service handles validation')
test('service throws exception on error')
test('service triggers events')
test('service persists data')

// Controller coverage
test('endpoint returns correct status')
test('endpoint validates input')
test('endpoint checks authorization')
test('endpoint returns correct schema')

// Model coverage
test('scope filters correctly')
test('relationship loads')
test('accessor transforms data')
test('mutator stores data')

// Policy coverage
test('policy allows authorized user')
test('policy denies unauthorized user')
test('policy checks company scoping')
```

## Integration Testing

Test multiple domains working together:

```php
test('complete invoice workflow', function () {
    // 1. Create customer
    $customer = Customer::factory()->create([
        'company_id' => $this->company->id,
    ]);

    // 2. Create items
    $items = Item::factory(2)->create([
        'company_id' => $this->company->id,
    ]);

    // 3. Create invoice with items
    $response = $this->postJson('/api/v1/invoices', [
        'customer_id' => $customer->id,
        'items' => [
            ['item_id' => $items[0]->id, 'quantity' => 2, 'price' => 100],
            ['item_id' => $items[1]->id, 'quantity' => 1, 'price' => 50],
        ],
    ], ['Company' => $this->company->slug]);

    $invoiceId = $response->json('data.id');

    // 4. Send invoice
    $this->postJson(
        "/api/v1/invoices/{$invoiceId}/send",
        [],
        ['Company' => $this->company->slug]
    )->assertStatus(200);

    // 5. Record payment
    $this->postJson(
        '/api/v1/payments',
        ['invoice_id' => $invoiceId, 'amount' => 250],
        ['Company' => $this->company->slug]
    )->assertStatus(201);

    // Verify final state
    $invoice = Invoice::find($invoiceId);
    expect($invoice->status)->toBe('paid');
});
```

## Continuous Integration

Tests run automatically on every push:

```yaml
# .github/workflows/test.yml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - run: composer install
      - run: php artisan test --parallel
```

Test results appear in PR checks before merging.
