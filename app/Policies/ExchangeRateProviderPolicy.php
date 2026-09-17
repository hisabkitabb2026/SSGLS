<?php

namespace App\Policies;

use App\Models\ExchangeRateProvider;

class ExchangeRateProviderPolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'exchange-rate-provider';

    protected string $modelClass = ExchangeRateProvider::class;
}
