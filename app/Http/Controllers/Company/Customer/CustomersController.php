<?php

namespace App\Http\Controllers\Company\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Http\Requests\DeleteCustomersRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomersController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Customer::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        // Transport templates (lorry_receipt, lr_receipt, office_invoice) should NOT
        // contribute to the customer's "Amount Due" — only standard invoices do.
        $transportTemplates = ['lorry_receipt', 'lr_receipt', 'office_invoice'];

        $customers = Customer::with(['creator', 'billingAddress', 'shippingAddress', 'fields.customField', 'currency', 'company'])
            ->whereCompany()
            ->applyFilters($request->all())
            ->with(['invoices' => function ($query) use ($transportTemplates) {
                $query->whereNotIn('template_name', $transportTemplates)
                    ->select('id', 'customer_id', 'due_amount', 'base_due_amount');
            }])
            ->withSum(['invoices as base_due_amount' => function ($query) use ($transportTemplates) {
                $query->whereNotIn('template_name', $transportTemplates);
            }], 'base_due_amount')
            ->withSum(['invoices as due_amount' => function ($query) use ($transportTemplates) {
                $query->whereNotIn('template_name', $transportTemplates);
            }], 'due_amount')
            ->paginateData($limit);

        return CustomerResource::collection($customers)
            ->additional(['meta' => [
                'customer_total_count' => Customer::whereCompany()->count(),
            ]]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Requests\CustomerRequest $request)
    {
        $this->authorize('create', Customer::class);

        $customer = $this->customerService->create($request);

        $customer->load(['billingAddress', 'shippingAddress', 'fields.customField', 'currency', 'company']);

        return new CustomerResource($customer);
    }

    /**
     * Display the specified resource.
     *
     * @return JsonResponse
     */
    public function show(Customer $customer)
    {
        $this->authorize('view', $customer);

        $customer->load(['billingAddress', 'shippingAddress', 'fields.customField', 'currency', 'company']);

        return new CustomerResource($customer);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function update(Requests\CustomerRequest $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        $customer = $this->customerService->update($request, $customer);

        $customer->load(['billingAddress', 'shippingAddress', 'fields.customField', 'currency', 'company']);

        return new CustomerResource($customer);
    }

    /**
     * Remove a list of Customers along side all their resources (ie. Estimates, Invoices, Payments and Addresses)
     *
     * @return JsonResponse
     */
    public function delete(DeleteCustomersRequest $request)
    {
        $this->authorize('delete multiple customers');

        return $this->deleteBulk(Customer::class, $request->ids, $this->customerService);
    }
}
