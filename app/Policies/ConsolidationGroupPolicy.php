<?php

namespace App\Policies;

use App\Models\ConsolidationGroup;

/**
 * Policy for ConsolidationGroup — extends BaseCompanyPolicy so that
 * access is controlled by Bouncer abilities (view-consolidation-group,
 * create-consolidation-group, edit-consolidation-group, delete-consolidation-group).
 *
 * Owners and super admins bypass ability checks via hasFullCompanyAccess().
 */
class ConsolidationGroupPolicy extends BaseCompanyPolicy
{
    protected string $abilityPrefix = 'consolidation-group';

    protected string $modelClass = ConsolidationGroup::class;
}
