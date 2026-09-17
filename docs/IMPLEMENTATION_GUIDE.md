# Transport Domain DDD Implementation Guide

## Overview
This guide provides step-by-step instructions for implementing the Transport Domain using Domain-Driven Design principles. The implementation is organized into logical phases with code templates.

## Phase 1: Models & Repositories (CRITICAL)

### Step 1.1: Copy Models to Domain
```bash
# Copy existing models to new domain location
cp app/Models/LorryReceipt.php app/Domains/Transport/Models/
cp app/Models/WarehouseItem.php app/Domains/Transport/Models/
cp app/Models/LorryPartyProfile.php app/Domains/Transport/Models/
cp app/Models/ConsolidationGroup.php app/Domains/Transport/Models/
cp app/Models/LoadTrip.php app/Domains/Transport/Models/
```

### Step 1.2: Update Model Namespaces
Update the namespace in each Model file from `namespace App\Models;` to `namespace App\Domains\Transport\Models;`

### Step 1.3: Create Repository Interfaces (Contracts)
**File**: `app/Domains/Transport/Contracts/LorryReceiptRepository.php`

```php
<?php
namespace App\Domains\Transport\Contracts;

interface LorryReceiptRepository
{
    public function create(array $data);
    public function update(int $id, array $data);
    public function find(int $id);
    public function delete(int $id);
    public function all($company);
    public function getByStatus(string $status, $company);
}
```

Repeat for: `WarehouseItemRepository`, `PartyProfileRepository`, `ConsolidationRepository`, `LoadTripRepository`

### Step 1.4: Create Eloquent Repository Implementations
**File**: `app/Domains/Transport/Repositories/EloquentLorryReceiptRepository.php`

```php
<?php
namespace App\Domains\Transport\Repositories;

use App\Domains\Transport\Contracts\LorryReceiptRepository;
use App\Domains\Transport\Models\LorryReceipt;

class EloquentLorryReceiptRepository implements LorryReceiptRepository
{
    public function create(array $data)
    {
        return LorryReceipt::create($data);
    }

    public function update(int $id, array $data)
    {
        $receipt = LorryReceipt::findOrFail($id);
        $receipt->update($data);
        return $receipt;
    }

    public function find(int $id)
    {
        return LorryReceipt::findOrFail($id);
    }

    public function delete(int $id)
    {
        return LorryReceipt::destroy($id);
    }

    public function all($company)
    {
        return LorryReceipt::where('company_id', $company->id)->get();
    }

    public function getByStatus(string $status, $company)
    {
        return LorryReceipt::where('company_id', $company->id)
            ->where('status', $status)
            ->get();
    }
}
```

Repeat for other entities.

---

## Phase 2: Application Services

### Step 2.1: Create DTOs (Data Transfer Objects)
**File**: `app/Domains/Transport/Data/CreateLorryReceiptData.php`

```php
<?php
namespace App\Domains\Transport\Data;

class CreateLorryReceiptData
{
    public function __construct(
        public string $vehicle_number,
        public string $owner_name,
        public string $driver_name,
        public string $from_location,
        public string $to_location,
        public float $freight_amount,
        public int $company_id,
    ) {}
}
```

### Step 2.2: Create Application Services
**File**: `app/Domains/Transport/Application/CreateLorryReceiptService.php`

```php
<?php
namespace App\Domains\Transport\Application;

use App\Domains\Transport\Contracts\LorryReceiptRepository;
use App\Domains\Transport\Data\CreateLorryReceiptData;
use App\Domains\Transport\Events\LorryReceiptCreated;
use Illuminate\Support\Facades\Event;

class CreateLorryReceiptService
{
    public function __construct(
        private LorryReceiptRepository $repository,
    ) {}

    public function execute(CreateLorryReceiptData $data)
    {
        // Create the entity
        $receipt = $this->repository->create([
            'vehicle_number' => $data->vehicle_number,
            'owner_name' => $data->owner_name,
            'driver_name' => $data->driver_name,
            'from_location' => $data->from_location,
            'to_location' => $data->to_location,
            'freight_amount' => $data->freight_amount,
            'company_id' => $data->company_id,
        ]);

        // Publish domain event
        Event::dispatch(new LorryReceiptCreated($receipt));

        return $receipt;
    }
}
```

---

## Phase 3: HTTP Layer (Controllers, Requests, Resources)

### Step 3.1: Migrate Controllers
**File**: `app/Domains/Transport/Http/Controllers/LorryReceiptController.php`

```php
<?php
namespace App\Domains\Transport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Transport\Application\CreateLorryReceiptService;
use App\Domains\Transport\Contracts\LorryReceiptRepository;
use App\Domains\Transport\Http\Requests\StoreLorryReceiptRequest;
use App\Domains\Transport\Http\Resources\LorryReceiptResource;
use App\Domains\Transport\Data\CreateLorryReceiptData;

class LorryReceiptController extends Controller
{
    public function __construct(
        private CreateLorryReceiptService $service,
        private LorryReceiptRepository $repository,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', LorryReceipt::class);

        $receipts = $this->repository->all(auth()->user()->company);

        return LorryReceiptResource::collection($receipts);
    }

    public function store(StoreLorryReceiptRequest $request)
    {
        $data = new CreateLorryReceiptData(
            vehicle_number: $request->vehicle_number,
            owner_name: $request->owner_name,
            driver_name: $request->driver_name,
            from_location: $request->from_location,
            to_location: $request->to_location,
            freight_amount: $request->freight_amount,
            company_id: auth()->user()->company_id,
        );

        $receipt = $this->service->execute($data);

        return new LorryReceiptResource($receipt);
    }
}
```

### Step 3.2: Create Request Classes
**File**: `app/Domains/Transport/Http/Requests/StoreLorryReceiptRequest.php`

```php
<?php
namespace App\Domains\Transport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLorryReceiptRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'vehicle_number' => 'required|string|max:50',
            'owner_name' => 'required|string|max:255',
            'driver_name' => 'required|string|max:255',
            'from_location' => 'required|string|max:255',
            'to_location' => 'required|string|max:255',
            'freight_amount' => 'required|numeric|min:0',
        ];
    }
}
```

### Step 3.3: Create API Resources
**File**: `app/Domains/Transport/Http/Resources/LorryReceiptResource.php`

```php
<?php
namespace App\Domains\Transport\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LorryReceiptResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'vehicle_number' => $this->vehicle_number,
            'owner_name' => $this->owner_name,
            'driver_name' => $this->driver_name,
            'from_location' => $this->from_location,
            'to_location' => $this->to_location,
            'freight_amount' => $this->freight_amount,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

---

## Phase 4: Domain Events & Listeners

### Step 4.1: Create Events
**File**: `app/Domains/Transport/Events/LorryReceiptCreated.php`

```php
<?php
namespace App\Domains\Transport\Events;

use App\Domains\Transport\Models\LorryReceipt;
use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LorryReceiptCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public LorryReceipt $receipt) {}

    public function broadcastOn(): array
    {
        return [new Channel('transport')];
    }
}
```

### Step 4.2: Create Listeners
**File**: `app/Domains/Transport/Listeners/NotifyOnLorryReceiptCreated.php`

```php
<?php
namespace App\Domains\Transport\Listeners;

use App\Domains\Transport\Events\LorryReceiptCreated;
use App\Domains\Transport\Mail\LorryReceiptNotification;
use Illuminate\Support\Facades\Mail;

class NotifyOnLorryReceiptCreated
{
    public function handle(LorryReceiptCreated $event): void
    {
        // Send email notification
        Mail::send(new LorryReceiptNotification($event->receipt));

        // Log event
        \Log::info('Lorry Receipt Created', [
            'receipt_id' => $event->receipt->id,
            'vehicle' => $event->receipt->vehicle_number,
        ]);
    }
}
```

---

## Phase 5: Authorization Policies

**File**: `app/Policies/TransportPolicies/LorryReceiptPolicy.php`

```php
<?php
namespace App\Policies\TransportPolicies;

use App\Models\User;
use App\Domains\Transport\Models\LorryReceipt;

class LorryReceiptPolicy
{
    public function viewAny(User $user)
    {
        return $user->company_id !== null;
    }

    public function view(User $user, LorryReceipt $receipt)
    {
        return $user->company_id === $receipt->company_id;
    }

    public function create(User $user)
    {
        return $user->bouncer()->can('create-lorry-receipt');
    }

    public function update(User $user, LorryReceipt $receipt)
    {
        return $user->company_id === $receipt->company_id &&
               $user->bouncer()->can('edit-lorry-receipt');
    }

    public function delete(User $user, LorryReceipt $receipt)
    {
        return $user->company_id === $receipt->company_id &&
               $user->bouncer()->can('delete-lorry-receipt');
    }
}
```

---

## Phase 6: Routes

**File**: `app/Domains/Transport/routes/api.php`

```php
<?php
use Illuminate\Support\Facades\Route;
use App\Domains\Transport\Http\Controllers\LorryReceiptController;
use App\Domains\Transport\Http\Controllers\WarehouseItemController;
use App\Domains\Transport\Http\Controllers\LorryPartyProfileController;

Route::middleware(['auth:sanctum', 'company'])->group(function () {
    Route::apiResource('lorry-receipts', LorryReceiptController::class);
    Route::apiResource('warehouse-items', WarehouseItemController::class);
    Route::apiResource('party-profiles', LorryPartyProfileController::class);

    // Additional endpoints
    Route::post('warehouse-items/{id}/status', [WarehouseItemController::class, 'updateStatus']);
    Route::get('warehouse-items/dashboard', [WarehouseItemController::class, 'dashboard']);
});
```

---

## Phase 7: Testing

### Unit Tests
**File**: `app/Domains/Transport/Tests/Unit/CreateLorryReceiptServiceTest.php`

```php
<?php
namespace App\Domains\Transport\Tests\Unit;

use Tests\TestCase;
use App\Domains\Transport\Application\CreateLorryReceiptService;
use App\Domains\Transport\Data\CreateLorryReceiptData;
use App\Domains\Transport\Repositories\EloquentLorryReceiptRepository;

class CreateLorryReceiptServiceTest extends TestCase
{
    public function test_create_lorry_receipt()
    {
        $service = app(CreateLorryReceiptService::class);

        $data = new CreateLorryReceiptData(
            vehicle_number: 'ABC-123',
            owner_name: 'John Doe',
            driver_name: 'Jane Smith',
            from_location: 'City A',
            to_location: 'City B',
            freight_amount: 1000.50,
            company_id: 1,
        );

        $receipt = $service->execute($data);

        $this->assertNotNull($receipt->id);
        $this->assertEquals('ABC-123', $receipt->vehicle_number);
    }
}
```

### Feature Tests
**File**: `app/Domains/Transport/Tests/Feature/LorryReceiptApiTest.php`

```php
<?php
namespace App\Domains\Transport\Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

class LorryReceiptApiTest extends TestCase
{
    public function test_get_lorry_receipts()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeader('company', $user->company_id)
            ->getJson('/api/v1/lorry-receipts');

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) =>
                $json->has('data')->etc()
            );
    }

    public function test_create_lorry_receipt()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withHeader('company', $user->company_id)
            ->postJson('/api/v1/lorry-receipts', [
                'vehicle_number' => 'XYZ-789',
                'owner_name' => 'Test Owner',
                'driver_name' => 'Test Driver',
                'from_location' => 'Location A',
                'to_location' => 'Location B',
                'freight_amount' => 5000,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.vehicle_number', 'XYZ-789');
    }
}
```

---

## Implementation Checklist

### Critical Files (MUST CREATE):
- [ ] Models (copy to new location, update namespace)
- [ ] Repository interfaces (5 contracts)
- [ ] Repository implementations (5 implementations)
- [ ] Application Services (5 services)
- [ ] DTOs (5 data classes)
- [ ] Controllers (5 controllers)
- [ ] Request validation (10 request classes)
- [ ] API Resources (10 resources)
- [ ] Policies (5 policy classes)
- [ ] Events (5 events)
- [ ] Listeners (5 listeners)
- [ ] Routes (api.php)
- [ ] ServiceProvider (TransportServiceProvider.php)

### Testing:
- [ ] Unit tests for services
- [ ] Feature tests for API endpoints
- [ ] Verify 80%+ code coverage

### Documentation:
- [ ] API documentation
- [ ] Architecture guide
- [ ] Extension guide

---

## Quick Start Commands

```bash
# 1. Create directory structure
mkdir -p app/Domains/Transport/{Application,Contracts,Models,Http/{Controllers,Requests,Resources},Policies,Jobs,Console,Mail,Events,Listeners,Repositories,Services,Data,routes,Tests/{Feature,Unit}}

# 2. Update config/app.php to register TransportServiceProvider
# Add: App\Domains\Transport\TransportServiceProvider::class,

# 3. Update routes/api.php to load Transport routes
# Route::group(['prefix' => 'v1'], function () {
#     require __DIR__ . '/../app/Domains/Transport/routes/api.php';
# });

# 4. Run tests
php artisan test app/Domains/Transport/Tests

# 5. Check code quality
vendor/bin/pint app/Domains/Transport
```

---

## Success Criteria

✅ All models migrated to domain
✅ Repositories implementing contracts
✅ Application services orchestrating business logic
✅ Controllers using services, not models directly
✅ All tests passing (80%+ coverage)
✅ Routes registered correctly
✅ Policies enforcing authorization
✅ Events published for domain changes
✅ Documentation complete
✅ Code passes Pint formatting
