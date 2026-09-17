<?php

namespace App\Domains\Settings\Http\Controllers;

use App\Domains\Settings\Application\CreateCurrencyService;
use App\Domains\Settings\Contracts\CurrencyRepository;
use App\Domains\Settings\Data\CreateCurrencyData;
use App\Domains\Settings\Http\Requests\StoreCurrencyRequest;
use App\Domains\Settings\Http\Resources\CurrencyResource;
use App\Domains\Settings\Models\Currency;
use App\Http\Controllers\Controller;

class CurrencyController extends Controller
{
    public function __construct(
        private CurrencyRepository $repository,
        private CreateCurrencyService $service,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Currency::class);
        $currencies = $this->repository->all();

        return CurrencyResource::collection($currencies);
    }

    public function store(StoreCurrencyRequest $request)
    {
        $this->authorize('create', Currency::class);
        $currency = $this->service->execute(
            new CreateCurrencyData(
                name: $request->validated('name'),
                code: $request->validated('code'),
                symbol: $request->validated('symbol'),
            )
        );

        return new CurrencyResource($currency);
    }

    public function show(Currency $currency)
    {
        $this->authorize('view', $currency);

        return new CurrencyResource($currency);
    }

    public function update(StoreCurrencyRequest $request, Currency $currency)
    {
        $this->authorize('update', $currency);
        $updated = $this->repository->update($currency->id, $request->validated());

        return new CurrencyResource($updated);
    }

    public function destroy(Currency $currency)
    {
        $this->authorize('delete', $currency);
        $this->repository->delete($currency->id);

        return response()->noContent();
    }
}
