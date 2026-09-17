<?php

namespace App\Domains\Settings\Data;

readonly class CreateCurrencyData
{
    public function __construct(
        public string $name,
        public string $code,
        public string $symbol,
    ) {}

    public function toArray(): array
    {
        return ['name' => $this->name, 'code' => $this->code, 'symbol' => $this->symbol];
    }
}
