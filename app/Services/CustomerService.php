<?php

namespace App\Services;

use App\Models\Customer;
use App\Services\Cache\CustomerStatsCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(
        private readonly CustomerStatsCacheService $cacheService,
    ) {}

    public function create(Request $request): Customer
    {
        $customer = Customer::create($request->getCustomerPayload());

        if ($request->shipping) {
            if ($request->hasAddress($request->shipping)) {
                $customer->addresses()->create($request->getShippingAddress());
            }
        }

        if ($request->billing) {
            if ($request->hasAddress($request->billing)) {
                $customer->addresses()->create($request->getBillingAddress());
            }
        }

        $customFields = $request->customFields;

        if ($customFields) {
            $customer->addCustomFields($customFields);
        }

        return Customer::with('billingAddress', 'shippingAddress', 'fields')->find($customer->id);
    }

    /**
     * @throws ValidationException
     */
    public function update(Request $request, Customer $customer): Customer
    {
        $condition = $customer->estimates()->exists() || $customer->invoices()->exists() || $customer->payments()->exists() || $customer->recurringInvoices()->exists();

        if (($customer->currency_id !== $request->currency_id) && $condition) {
            throw ValidationException::withMessages([
                'currency_id' => ['you_cannot_edit_currency'],
            ]);
        }

        $customer->update($request->getCustomerPayload());

        $customer->addresses()->delete();

        if ($request->shipping) {
            if ($request->hasAddress($request->shipping)) {
                $customer->addresses()->create($request->getShippingAddress());
            }
        }

        if ($request->billing) {
            if ($request->hasAddress($request->billing)) {
                $customer->addresses()->create($request->getBillingAddress());
            }
        }

        $customFields = $request->customFields;

        if ($customFields) {
            $customer->updateCustomFields($customFields);
        }

        return Customer::with('billingAddress', 'shippingAddress', 'fields')->find($customer->id);
    }

    public function delete(Collection $ids): bool
    {
        foreach ($ids as $id) {
            $customer = Customer::find($id);

            // Bulk delete with eager loading to avoid N+1 queries
            $customer->estimates()->delete();
            $customer->payments()->delete();
            $customer->addresses()->delete();
            $customer->expenses()->delete();

            // Delete invoices with their transactions
            $invoices = $customer->invoices;
            if ($invoices->isNotEmpty()) {
                foreach ($invoices as $invoice) {
                    $invoice->transactions()->delete();
                    $invoice->delete();
                }
            }

            // Delete recurring invoices with their items
            $recurringInvoices = $customer->recurringInvoices;
            if ($recurringInvoices->isNotEmpty()) {
                foreach ($recurringInvoices as $recurringInvoice) {
                    $recurringInvoice->items()->delete();
                    $recurringInvoice->delete();
                }
            }

            $customer->delete();
        }

        return true;
    }

    public function getStats(Customer $customer, int $companyId, bool $previousYear = false): array
    {
        return $this->cacheService->getStats($customer, $companyId, $previousYear);
    }
}
