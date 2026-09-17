<?php

namespace App\Domains\Invoicing\Adapters;

use App\Domains\Invoicing\Contracts\PaymentRepository;
use App\Domains\Invoicing\Models\Payment;

class EloquentPaymentRepository implements PaymentRepository
{
    public function create(array $data)
    {
        return Payment::create($data);
    }

    public function update(int $id, array $data)
    {
        $payment = Payment::findOrFail($id);
        $payment->update($data);

        return $payment;
    }

    public function find(int $id)
    {
        return Payment::findOrFail($id);
    }

    public function delete(int $id)
    {
        return Payment::destroy($id);
    }

    public function all($company)
    {
        return Payment::where('company_id', $company->id)->get();
    }

    public function getByInvoice(int $invoiceId)
    {
        return Payment::where('invoice_id', $invoiceId)->get();
    }
}
