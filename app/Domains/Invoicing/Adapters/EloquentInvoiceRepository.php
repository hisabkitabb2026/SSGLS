<?php

namespace App\Domains\Invoicing\Adapters;

use App\Domains\Invoicing\Contracts\InvoiceRepository;
use App\Domains\Invoicing\Models\Invoice;

class EloquentInvoiceRepository implements InvoiceRepository
{
    public function create(array $data)
    {
        return Invoice::create($data);
    }

    public function update(int $id, array $data)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->update($data);

        return $invoice;
    }

    public function find(int $id)
    {
        return Invoice::findOrFail($id);
    }

    public function delete(int $id)
    {
        return Invoice::destroy($id);
    }

    public function all($company)
    {
        return Invoice::where('company_id', $company->id)->get();
    }

    public function getByStatus(string $status, $company)
    {
        return Invoice::where('company_id', $company->id)
            ->where('status', $status)
            ->get();
    }

    public function getDraft($company)
    {
        return $this->getByStatus('draft', $company);
    }

    public function getPublished($company)
    {
        return $this->getByStatus('published', $company);
    }
}
