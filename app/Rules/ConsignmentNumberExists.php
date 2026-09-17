<?php

namespace App\Rules;

use App\Models\Invoice;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates that the given consignment number corresponds to an existing
 * LR Receipt (template_name = 'lr_receipt') within the current company.
 *
 * The company is resolved from the 'company' request header, which is set
 * by the CompanyMiddleware.
 *
 * Used on Office Invoice items to ensure that each consignment_number
 * references a real LR Receipt (Docket No) before the invoice can be saved.
 */
class ConsignmentNumberExists implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return; // 'required' rule handles empty values
        }

        $companyId = request()->header('company');

        $exists = Invoice::where('company_id', $companyId)
            ->where('template_name', 'lr_receipt')
            ->where('invoice_number', $value)
            ->exists();

        if (! $exists) {
            $fail('The consignment number :value does not match any existing LR Receipt.');
        }
    }
}
