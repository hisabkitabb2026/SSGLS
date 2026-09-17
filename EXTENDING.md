# InvoiceShelf Extension Guide

Guide for extending InvoiceShelf with new features, domains, and functionality.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Adding Features to Existing Domains](#adding-features-to-existing-domains)
3. [Creating a New Domain](#creating-a-new-domain)
4. [Creating API Endpoints](#creating-api-endpoints)
5. [Adding Domain Events](#adding-domain-events)
6. [Custom Fields & Extensibility](#custom-fields--extensibility)
7. [Module System](#module-system)
8. [Best Practices](#best-practices)
9. [Code Examples](#code-examples)

## Architecture Overview

InvoiceShelf uses Domain-Driven Design with clear separation of concerns:

```
Domain Structure:
├── Models/              # Eloquent entities
├── Application/         # Application services
├── Adapters/           # Repository implementations
├── Events/             # Domain events
├── Listeners/          # Event handlers
├── Policies/           # Authorization
├── Http/
│   ├── Controllers/    # API endpoints
│   ├── Requests/       # Validation
│   └── Resources/      # Response formatting
└── Services/           # Complex domain logic
```

## Adding Features to Existing Domains

### Example: Add Tax Calculation to Invoices

#### 1. Create Application Service

Create `/app/Domains/Invoicing/Application/CalculateTaxService.php`:

```php
<?php

namespace App\Domains\Invoicing\Application;

use App\Domains\Invoicing\Models\Invoice;

class CalculateTaxService {
    public function __construct(
        private TaxRepository $taxRepository,
    ) {}

    public function execute(Invoice $invoice): decimal {
        $total = 0;

        foreach ($invoice->items as $item) {
            $itemTax = $item->getTaxAmount();
            $total += $itemTax;
        }

        return $total;
    }
}
```

#### 2. Create Domain Event

Create `/app/Domains/Invoicing/Events/TaxCalculated.php`:

```php
<?php

namespace App\Domains\Invoicing\Events;

use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaxCalculated {
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public decimal $taxAmount,
    ) {}
}
```

#### 3. Create Event Listener

Create `/app/Domains/Invoicing/Listeners/UpdateInvoiceTotalOnTaxCalculated.php`:

```php
<?php

namespace App\Domains\Invoicing\Listeners;

use App\Domains\Invoicing\Events\TaxCalculated;

class UpdateInvoiceTotalOnTaxCalculated {
    public function handle(TaxCalculated $event): void {
        $event->invoice->tax_amount = $event->taxAmount;
        $event->invoice->total = $event->invoice->subtotal + $event->taxAmount;
        $event->invoice->save();
    }
}
```

#### 4. Update Service Provider

Register event and listener in `InvoicingServiceProvider`:

```php
public function boot(): void {
    $this->registerEvents();
}

private function registerEvents(): void {
    Event::listen(
        InvoiceCreated::class,
        [SendEmailOnInvoiceCreated::class, 'handle'],
    );

    Event::listen(
        TaxCalculated::class,
        [UpdateInvoiceTotalOnTaxCalculated::class, 'handle'],
    );
}
```

#### 5. Add API Endpoint

Add to `/app/Domains/Invoicing/Http/Controllers/TaxController.php`:

```php
<?php

namespace App\Domains\Invoicing\Http\Controllers;

use App\Domains\Invoicing\Application\CalculateTaxService;
use App\Domains\Invoicing\Models\Invoice;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class TaxController extends Controller {
    public function __construct(
        private CalculateTaxService $taxService,
    ) {}

    public function __invoke(Invoice $invoice): JsonResponse {
        $this->authorize('view', $invoice);

        $taxAmount = $this->taxService->execute($invoice);

        return response()->json([
            'success' => true,
            'data' => [
                'invoice_id' => $invoice->id,
                'tax_amount' => $taxAmount,
            ],
        ]);
    }
}
```

Register route in `routes/api.php` or domain routes:

```php
Route::post('/invoices/{invoice}/calculate-tax', TaxController::class);
```

#### 6. Add Tests

Create `/tests/Feature/Invoicing/TaxCalculationTest.php`:

```php
<?php

use App\Domains\Invoicing\Models\Invoice;
use Laravel\Sanctum\Sanctum;

test('can calculate invoice tax', function () {
    $invoice = Invoice::factory()->create();
    Sanctum::actingAs($invoice->company->owner);

    $response = $this->postJson(
        "/api/v1/invoices/{$invoice->id}/calculate-tax",
        [],
        ['Company' => $invoice->company->slug]
    );

    $response->assertStatus(200)
        ->assertJsonStructure(['success', 'data']);
});
```

## Creating a New Domain

### Structure for New Domain

Let's create a "Reporting" domain for analytics:

```
app/Domains/Reporting/
├── Models/
│   └── Report.php
├── Application/
│   ├── GenerateReportService.php
│   └── ExportReportService.php
├── Adapters/
│   └── EloquentReportRepository.php
├── Events/
│   └── ReportGenerated.php
├── Http/
│   ├── Controllers/
│   │   └── ReportController.php
│   ├── Requests/
│   │   └── GenerateReportRequest.php
│   └── Resources/
│       └── ReportResource.php
├── Policies/
│   └── ReportPolicy.php
├── ReportingServiceProvider.php
└── routes/
    └── api.php
```

### Step 1: Create Service Provider

`/app/Domains/Reporting/ReportingServiceProvider.php`:

```php
<?php

namespace App\Domains\Reporting;

use Illuminate\Support\ServiceProvider;

class ReportingServiceProvider extends ServiceProvider {
    public function register(): void {
        // Service bindings
        $this->app->singleton(
            ReportRepository::class,
            EloquentReportRepository::class,
        );
    }

    public function boot(): void {
        // Route loading
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');

        // Authorization policies
        $this->registerPolicies();
    }

    private function registerPolicies(): void {
        Gate::policy(Report::class, ReportPolicy::class);
    }
}
```

### Step 2: Register Service Provider

In `/config/app.php` or in main `AppServiceProvider`:

```php
'providers' => [
    // ...
    App\Domains\Reporting\ReportingServiceProvider::class,
],
```

### Step 3: Create Model

`/app/Domains/Reporting/Models/Report.php`:

```php
<?php

namespace App\Domains\Reporting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model {
    protected $fillable = [
        'company_id',
        'name',
        'type',
        'filters',
        'data',
    ];

    protected $casts = [
        'filters' => 'array',
        'data' => 'array',
    ];

    public function company(): BelongsTo {
        return $this->belongsTo(Company::class);
    }
}
```

### Step 4: Create Migration

`/database/migrations/create_reports_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id');
            $table->string('name');
            $table->enum('type', ['sales', 'expense', 'profit']);
            $table->json('filters')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->foreign('company_id')
                ->references('id')
                ->on('companies');
        });
    }

    public function down(): void {
        Schema::dropIfExists('reports');
    }
};
```

Run migration:
```bash
php artisan migrate
```

### Step 5: Create Repository Interface

`/app/Domains/Reporting/Adapters/ReportRepository.php`:

```php
<?php

namespace App\Domains\Reporting\Adapters;

use App\Domains\Reporting\Models\Report;

interface ReportRepository {
    public function store(Report $report): void;
    public function find(int $id): ?Report;
    public function findByCompany(int $companyId): array;
    public function delete(Report $report): void;
}
```

### Step 6: Implement Repository

`/app/Domains/Reporting/Adapters/EloquentReportRepository.php`:

```php
<?php

namespace App\Domains\Reporting\Adapters;

use App\Domains\Reporting\Models\Report;

class EloquentReportRepository implements ReportRepository {
    public function store(Report $report): void {
        $report->save();
    }

    public function find(int $id): ?Report {
        return Report::find($id);
    }

    public function findByCompany(int $companyId): array {
        return Report::where('company_id', $companyId)->get()->all();
    }

    public function delete(Report $report): void {
        $report->delete();
    }
}
```

### Step 7: Create Application Service

`/app/Domains/Reporting/Application/GenerateReportService.php`:

```php
<?php

namespace App\Domains\Reporting\Application;

use App\Domains\Reporting\Adapters\ReportRepository;
use App\Domains\Reporting\Models\Report;

class GenerateReportService {
    public function __construct(
        private ReportRepository $reportRepository,
        private InvoiceRepository $invoiceRepository,
    ) {}

    public function execute(GenerateReportDTO $dto): Report {
        // Generate report data
        $data = $this->generateData($dto);

        // Create report
        $report = Report::create([
            'company_id' => $dto->company_id,
            'name' => $dto->name,
            'type' => $dto->type,
            'filters' => $dto->filters,
            'data' => $data,
        ]);

        $this->reportRepository->store($report);

        return $report;
    }

    private function generateData(GenerateReportDTO $dto): array {
        // Complex report generation logic
        return [];
    }
}
```

### Step 8: Create Controller

`/app/Domains/Reporting/Http/Controllers/ReportController.php`:

```php
<?php

namespace App\Domains\Reporting\Http\Controllers;

use App\Domains\Reporting\Application\GenerateReportService;
use App\Domains\Reporting\Http\Requests\GenerateReportRequest;
use App\Domains\Reporting\Http\Resources\ReportResource;
use App\Http\Controllers\Controller;

class ReportController extends Controller {
    public function __construct(
        private GenerateReportService $generateReportService,
    ) {}

    public function store(GenerateReportRequest $request) {
        $this->authorize('create', Report::class);

        $report = $this->generateReportService->execute(
            $request->toDTO()
        );

        return ReportResource::make($report);
    }
}
```

### Step 9: Create Routes

`/app/Domains/Reporting/routes/api.php`:

```php
<?php

use App\Domains\Reporting\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company', 'bouncer'])->group(function () {
    Route::resource('reports', ReportController::class);
});
```

## Creating API Endpoints

### RESTful Endpoint Pattern

```php
// List resources
Route::get('/resource', [ResourceController::class, 'index']);

// Create resource
Route::post('/resource', [ResourceController::class, 'store']);

// Show single resource
Route::get('/resource/{resource}', [ResourceController::class, 'show']);

// Update resource
Route::put('/resource/{resource}', [ResourceController::class, 'update']);

// Delete resource
Route::delete('/resource/{resource}', [ResourceController::class, 'destroy']);
```

### Controller Example

```php
class InvoiceController extends Controller {
    public function __construct(
        private CreateInvoiceService $createService,
        private UpdateInvoiceService $updateService,
        private DeleteInvoiceService $deleteService,
    ) {}

    public function index(Request $request) {
        $invoices = Invoice::paginate($request->input('per_page', 15));

        return InvoiceResource::collection($invoices);
    }

    public function store(CreateInvoiceRequest $request) {
        $this->authorize('create', Invoice::class);

        $invoice = $this->createService->execute($request->toDTO());

        return InvoiceResource::make($invoice)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Invoice $invoice) {
        $this->authorize('view', $invoice);

        return InvoiceResource::make($invoice);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice) {
        $this->authorize('update', $invoice);

        $invoice = $this->updateService->execute($invoice, $request->toDTO());

        return InvoiceResource::make($invoice);
    }

    public function destroy(Invoice $invoice) {
        $this->authorize('delete', $invoice);

        $this->deleteService->execute($invoice);

        return response()->noContent();
    }
}
```

## Adding Domain Events

### Event Definition

```php
<?php

namespace App\Domains\Invoicing\Events;

use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoicePublished {
    use Dispatchable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
    ) {}
}
```

### Event Dispatcher

In service:

```php
class PublishInvoiceService {
    public function execute(Invoice $invoice): void {
        // Business logic...

        event(new InvoicePublished($invoice));
    }
}
```

### Event Listener

```php
<?php

namespace App\Domains\Invoicing\Listeners;

use App\Domains\Invoicing\Events\InvoicePublished;
use App\Mail\InvoicePublishedMail;
use Illuminate\Support\Facades\Mail;

class SendEmailOnInvoicePublished {
    public function handle(InvoicePublished $event): void {
        Mail::to($event->invoice->customer->email)
            ->queue(new InvoicePublishedMail($event->invoice));
    }
}
```

### Register Listener

In ServiceProvider:

```php
public function boot(): void {
    Event::listen(
        InvoicePublished::class,
        SendEmailOnInvoicePublished::class,
    );
}
```

## Custom Fields & Extensibility

### Using Custom Fields

```php
// In table migration
$table->json('custom_fields')->nullable();

// In model
protected $casts = [
    'custom_fields' => 'array',
];

// Usage
$invoice->custom_fields = [
    'field_1' => 'value_1',
    'field_2' => 'value_2',
];

// Access
$value = $invoice->custom_fields['field_1'];
```

### Custom Field API

```http
POST /v1/custom-fields
{
  "name": "Invoice Color",
  "type": "select",
  "entity": "invoices",
  "options": ["red", "blue", "green"]
}
```

## Module System

InvoiceShelf includes extensible module system for adding plugins.

### Module Structure

```
Modules/
└── MyModule/
    ├── composer.json
    ├── config/
    ├── src/
    │   ├── Models/
    │   ├── Services/
    │   └── Http/
    ├── routes/
    ├── migrations/
    └── module.json
```

### Creating a Module

```bash
php artisan make:module MyModule
```

## Best Practices

### 1. Follow DDD Principles

- **Isolate domain logic** in domain services
- **Use repositories** for data access
- **Emit domain events** for side effects
- **Define policies** for authorization

### 2. Service Layer Pattern

```php
// Good - service handles business logic
class CreateInvoiceService {
    public function execute(CreateInvoiceDTO $dto): Invoice {
        // Business logic here
    }
}

// Bad - logic in controller
public function store(Request $request) {
    // Too much logic
    $invoice = Invoice::create(...);
    $items = ...;
    event(...);
}
```

### 3. Validation in Form Requests

```php
// Good
class CreateInvoiceRequest extends FormRequest {
    public function rules(): array {
        return [
            'customer_id' => 'required|exists:customers',
            'items' => 'required|array|min:1',
        ];
    }
}

// Bad - validation in controller
public function store(Request $request) {
    $request->validate([...]);
}
```

### 4. Authorization via Policies

```php
// Good
$this->authorize('update', $invoice);

// Bad
if (auth()->user()->id !== $invoice->user_id) {
    abort(403);
}
```

### 5. Database Transactions

```php
// For complex operations
DB::transaction(function () {
    $invoice = $this->createInvoice();
    $this->recordPayment($invoice);
    event(new InvoiceCreated($invoice));
});
```

## Code Examples

### Complete Feature Addition Example

Adding "Invoice Reminder" feature:

1. **Create event:**
   ```php
   class ReminderScheduled {
       public function __construct(
           public Invoice $invoice,
           public Carbon $scheduledAt,
       ) {}
   }
   ```

2. **Create service:**
   ```php
   class ScheduleReminderService {
       public function execute(Invoice $invoice, Carbon $date): void {
           $invoice->reminder_sent_at = $date;
           $invoice->save();

           event(new ReminderScheduled($invoice, $date));
       }
   }
   ```

3. **Create listener:**
   ```php
   class SendInvoiceReminderEmail {
       public function handle(ReminderScheduled $event): void {
           Mail::to($event->invoice->customer->email)
               ->send(new ReminderMail($event->invoice));
       }
   }
   ```

4. **Add controller method:**
   ```php
   public function scheduleReminder(ScheduleReminderRequest $request, Invoice $invoice) {
       $this->authorize('update', $invoice);

       $this->scheduleReminderService->execute(
           $invoice,
           $request->scheduled_at
       );

       return response()->json(['success' => true]);
   }
   ```

5. **Register route:**
   ```php
   Route::post('/invoices/{invoice}/schedule-reminder',
       [InvoiceController::class, 'scheduleReminder']
   );
   ```

6. **Write tests:**
   ```php
   test('can schedule invoice reminder', function () {
       $invoice = Invoice::factory()->create();
       Sanctum::actingAs($invoice->company->owner);

       $response = $this->postJson(
           "/api/v1/invoices/{$invoice->id}/schedule-reminder",
           ['scheduled_at' => now()->addDays(7)],
           ['Company' => $invoice->company->slug]
       );

       $response->assertStatus(200);
       $this->assertNotNull($invoice->reminder_sent_at);
   });
   ```

This follows all DDD and framework conventions while adding a complete new feature.
