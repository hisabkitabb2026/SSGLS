<?php

namespace App\Policies;

use App\Models\WarehouseItem;

/**
 * Policy for WarehouseItem — extends BaseCompanyPolicy so that
 * access is controlled by Bouncer abilities (view-warehouse-item,
 * create-warehouse-item, edit-warehouse-item, delete-warehouse-item).
 *
 * Owners and super admins bypass ability checks via hasFullCompanyAccess().
 */
class WarehouseItemPolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'warehouse-item';

    protected string $modelClass = WarehouseItem::class;
}
