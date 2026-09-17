<?php

namespace App\Domains\Invoicing\Adapters;

use App\Domains\Invoicing\Contracts\RecurringInvoiceRepository;
use App\Domains\Invoicing\Models\RecurringInvoice;

class EloquentRecurringInvoiceRepository implements RecurringInvoiceRepository
{
    public function create(array $data)
    {
        return RecurringInvoice::create($data);
    }

    public function update(int $id, array $data)
    {
        $recurring = RecurringInvoice::findOrFail($id);
        $recurring->update($data);

        return $recurring;
    }

    public function find(int $id)
    {
        return RecurringInvoice::findOrFail($id);
    }

    public function delete(int $id)
    {
        return RecurringInvoice::destroy($id);
    }

    public function all($company)
    {
        return RecurringInvoice::where('company_id', $company->id)->get();
    }

    public function getActive($company)
    {
        return RecurringInvoice::where('company_id', $company->id)
            ->where('is_active', true)
            ->get();
    }
}
