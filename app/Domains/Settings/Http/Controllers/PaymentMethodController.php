<?php

namespace App\Domains\Settings\Http\Controllers;

use App\Domains\Settings\Application\CreatePaymentMethodService;
use App\Domains\Settings\Contracts\PaymentMethodRepository;
use App\Domains\Settings\Data\CreatePaymentMethodData;
use App\Domains\Settings\Http\Requests\StorePaymentMethodRequest;
use App\Domains\Settings\Http\Resources\PaymentMethodResource;
use App\Domains\Settings\Models\PaymentMethod;
use App\Http\Controllers\Controller;

class PaymentMethodController extends Controller
{
    public function __construct(
        private PaymentMethodRepository $repository,
        private CreatePaymentMethodService $service,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', PaymentMethod::class);
        $methods = $this->repository->all(auth()->user()->company_id);

        return PaymentMethodResource::collection($methods);
    }

    public function store(StorePaymentMethodRequest $request)
    {
        $this->authorize('create', PaymentMethod::class);
        $method = $this->service->execute(
            new CreatePaymentMethodData(
                name: $request->validated('name'),
                type: $request->validated('type'),
                company_id: auth()->user()->company_id,
            )
        );

        return new PaymentMethodResource($method);
    }

    public function show(PaymentMethod $method)
    {
        $this->authorize('view', $method);

        return new PaymentMethodResource($method);
    }

    public function update(StorePaymentMethodRequest $request, PaymentMethod $method)
    {
        $this->authorize('update', $method);
        $updated = $this->repository->update($method->id, $request->validated());

        return new PaymentMethodResource($updated);
    }

    public function destroy(PaymentMethod $method)
    {
        $this->authorize('delete', $method);
        $this->repository->delete($method->id);

        return response()->noContent();
    }
}
