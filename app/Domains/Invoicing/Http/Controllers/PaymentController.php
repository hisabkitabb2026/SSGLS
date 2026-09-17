<?php

namespace App\Domains\Invoicing\Http\Controllers;

use App\Domains\Invoicing\Application\RecordPaymentService;
use App\Domains\Invoicing\Contracts\PaymentRepository;
use App\Domains\Invoicing\Data\CreatePaymentData;
use App\Domains\Invoicing\Http\Requests\StorePaymentRequest;
use App\Domains\Invoicing\Http\Resources\PaymentResource;
use App\Domains\Invoicing\Models\Payment;
use App\Http\Controllers\Controller;

class PaymentController extends Controller
{
    public function __construct(
        private RecordPaymentService $service,
        private PaymentRepository $repository,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Payment::class);
        $payments = $this->repository->all(auth()->user()->company);

        return PaymentResource::collection($payments);
    }

    public function store(StorePaymentRequest $request)
    {
        $this->authorize('create', Payment::class);

        $data = new CreatePaymentData(
            invoice_id: $request->invoice_id,
            amount: $request->amount,
            payment_method: $request->payment_method,
            reference: $request->reference,
            notes: $request->notes,
            company_id: auth()->user()->company_id,
        );

        $payment = $this->service->execute($data);

        return new PaymentResource($payment);
    }

    public function show(Payment $payment)
    {
        $this->authorize('view', $payment);

        return new PaymentResource($payment);
    }

    public function destroy(Payment $payment)
    {
        $this->authorize('delete', $payment);
        $this->repository->delete($payment->id);

        return response()->noContent();
    }
}
