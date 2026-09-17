<?php

namespace App\Domains\Settings\Http\Controllers;

use App\Domains\Settings\Application\CreateTaxService;
use App\Domains\Settings\Contracts\TaxRepository;
use App\Domains\Settings\Data\CreateTaxData;
use App\Domains\Settings\Http\Requests\StoreTaxRequest;
use App\Domains\Settings\Http\Resources\TaxResource;
use App\Domains\Settings\Models\Tax;
use App\Http\Controllers\Controller;

class TaxController extends Controller
{
    public function __construct(
        private TaxRepository $repository,
        private CreateTaxService $service,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Tax::class);
        $taxes = $this->repository->all(auth()->user()->company_id);

        return TaxResource::collection($taxes);
    }

    public function store(StoreTaxRequest $request)
    {
        $this->authorize('create', Tax::class);
        $tax = $this->service->execute(
            new CreateTaxData(
                name: $request->validated('name'),
                rate: $request->validated('rate'),
                company_id: auth()->user()->company_id,
            )
        );

        return new TaxResource($tax);
    }

    public function show(Tax $tax)
    {
        $this->authorize('view', $tax);

        return new TaxResource($tax);
    }

    public function update(StoreTaxRequest $request, Tax $tax)
    {
        $this->authorize('update', $tax);
        $updated = $this->repository->update($tax->id, $request->validated());

        return new TaxResource($updated);
    }

    public function destroy(Tax $tax)
    {
        $this->authorize('delete', $tax);
        $this->repository->delete($tax->id);

        return response()->noContent();
    }
}
