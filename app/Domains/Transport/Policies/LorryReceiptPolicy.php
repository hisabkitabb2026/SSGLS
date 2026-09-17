<?php

namespace App\Domains\Transport\Policies;

use App\Domains\Transport\Models\LorryReceipt;
use App\Models\User;

class LorryReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->company_id !== null;
    }

    public function view(User $user, LorryReceipt $receipt): bool
    {
        return $user->company_id === $receipt->company_id;
    }

    public function create(User $user): bool
    {
        return $user->company_id !== null;
    }

    public function update(User $user, LorryReceipt $receipt): bool
    {
        return $user->company_id === $receipt->company_id;
    }

    public function delete(User $user, LorryReceipt $receipt): bool
    {
        return $user->company_id === $receipt->company_id;
    }
}
