<?php

namespace App\Domains\Settings\Policies;

use App\Domains\Settings\Models\PaymentMethod;
use App\Models\User;

class PaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->company_id;
    }

    public function view(User $user, PaymentMethod $method): bool
    {
        return $user->company_id === $method->company_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PaymentMethod $method): bool
    {
        return $user->company_id === $method->company_id;
    }

    public function delete(User $user, PaymentMethod $method): bool
    {
        return $user->company_id === $method->company_id;
    }
}
