<?php

namespace App\Domains\Customer\Http\Controllers;

use App\Domains\Customer\Application\CreateCustomerService;
use App\Domains\Customer\Contracts\CustomerRepository;
use App\Domains\Customer\Data\CreateCustomerData;
use App\Domains\Customer\Http\Requests\StoreCustomerRequest;
use App\Domains\Customer\Http\Requests\UpdateCustomerRequest;
use App\Domains\Customer\Http\Resources\CustomerResource;
use App\Domains\Customer\Models\Customer;
use App\Http\Controllers\Controller;
use App\Services\Cache\DashboardCacheService;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerRepository $repository,
        private CreateCustomerService $service,
        private DashboardCacheService $cacheService,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Customer::class);
        $customers = $this->repository->all(auth()->user()->company_id);

        return CustomerResource::collection($customers);
    }

    public function store(StoreCustomerRequest $request)
    {
        $this->authorize('create', Customer::class);
        $customer = $this->service->execute(
            new CreateCustomerData(
                name: $request->validated('name'),
                email: $request->validated('email'),
                phone: $request->validated('phone'),
                customer_type: $request->validated('customer_type'),
                active: $request->validated('active', true),
                company_id: auth()->user()->company_id,
            )
        );

        // Invalidate dashboard cache after customer creation
        $this->cacheService->invalidate(auth()->user()->company_id);

        return new CustomerResource($customer);
    }

    public function show(Customer $customer)
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $this->authorize('update', $customer);
        $updated = $this->repository->update($customer->id, $request->validated());

        // Invalidate dashboard cache after customer update
        $this->cacheService->invalidate($customer->company_id);

        return new CustomerResource($updated);
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);
        $companyId = $customer->company_id;
        $this->repository->delete($customer->id);

        // Invalidate dashboard cache after customer deletion
        $this->cacheService->invalidate($companyId);

        return response()->noContent();
    }
}
