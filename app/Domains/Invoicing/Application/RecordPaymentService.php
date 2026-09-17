<?php

namespace App\Domains\Invoicing\Application;

use App\Domains\Invoicing\Contracts\PaymentRepository;
use App\Domains\Invoicing\Data\CreatePaymentData;
use App\Domains\Invoicing\Events\InvoicePaid;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class RecordPaymentService
{
    public function __construct(
        private PaymentRepository $repository,
    ) {}

    public function execute(CreatePaymentData $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $payment = $this->repository->create($data->toArray());

            $invoice = Invoice::findOrFail($data->invoice_id);
            $invoice->update(['paid_status' => 'PAID']);

            Event::dispatch(new InvoicePaid($invoice, $payment));

            return $payment;
        });
    }
}
