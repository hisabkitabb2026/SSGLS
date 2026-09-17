<?php

namespace App\Domains\Settings\Application;

use App\Domains\Settings\Contracts\PaymentMethodRepository;
use App\Domains\Settings\Data\CreatePaymentMethodData;

class CreatePaymentMethodService
{
    public function __construct(private PaymentMethodRepository $repository) {}

    public function execute(CreatePaymentMethodData $data)
    {
        return $this->repository->create($data->toArray());
    }
}
