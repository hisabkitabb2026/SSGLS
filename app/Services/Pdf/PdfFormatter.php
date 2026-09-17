<?php

namespace App\Services\Pdf;

use Illuminate\Support\Collection;

class PdfFormatter
{
    private string $normalize_pattern = '/[^a-z0-9]+/i';

    public static function make(): self
    {
        return new self;
    }

    public function normalize(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $value));
    }

    public function extractField(Collection $fields, array $keys): string
    {
        $keys = array_map(fn ($k) => $this->normalize($k), $keys);
        $matchedValue = null;

        foreach ($fields as $field) {
            $customField = $field->customField ?? null;
            $labels = [
                $customField->label ?? '',
                $customField->name ?? '',
            ];

            $matches = collect($labels)
                ->contains(fn ($label) => in_array($this->normalize($label), $keys, true));

            if (! $matches) {
                continue;
            }

            if (trim((string) $field->defaultAnswer) !== '') {
                return (string) $field->defaultAnswer;
            }

            $matchedValue = (string) $field->defaultAnswer;
        }

        return $matchedValue ?? '';
    }

    public function fieldValue(Collection $fields, $keys, string $fallback = ''): string
    {
        $value = $this->extractField($fields, (array) $keys);

        return trim((string) $value) === '' ? $fallback : $value;
    }

    public function parseNumber($keys, Collection $fields): ?float
    {
        $value = trim(str_replace(',', '', (string) $this->fieldValue($fields, $keys)));

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        $amount = (float) $value;

        return (float) (int) $amount === $amount ? (int) $amount : $amount;
    }

    public function sumAmounts(array $values): ?float
    {
        $values = collect($values)->filter(fn ($value) => $value !== null);

        return $values->isEmpty() ? null : $values->sum();
    }

    public function formatAmount($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (! is_numeric($value)) {
            return (string) $value;
        }

        $amount = (float) $value;

        return (string) ((float) (int) $amount === $amount ? (int) $amount : $amount);
    }

    public function splitLines(string $value, array $widths): array
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
        $lines = [];

        foreach (explode("\n", $value) as $rawLine) {
            $rawLine = trim(preg_replace('/[ \t]+/', ' ', $rawLine));
            if ($rawLine === '') {
                continue;
            }

            $lineWidth = $widths[min(count($lines), count($widths) - 1)];
            foreach (explode("\n", wordwrap($rawLine, $lineWidth, "\n", true)) as $wrappedLine) {
                if (count($lines) >= count($widths)) {
                    $lines[count($widths) - 1] = trim($lines[count($widths) - 1].' '.$wrappedLine);

                    continue;
                }
                $lines[] = $wrappedLine;
            }
        }

        return array_pad(array_slice($lines, 0, count($widths)), count($widths), '');
    }

    public function addressLine(Collection $fields, $keys, array $widths, int $index): string
    {
        return $this->splitLines($this->fieldValue($fields, $keys), $widths)[$index] ?? '';
    }

    public function numberToWords(int $num): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        if ($num === 0) {
            return 'Zero';
        }

        if ($num < 20) {
            return $ones[$num];
        }

        if ($num < 100) {
            return trim($tens[intdiv($num, 10)].' '.$ones[$num % 10]);
        }

        if ($num < 1000) {
            $text = $ones[intdiv($num, 100)].' Hundred';
            if ($num % 100) {
                $text .= ' '.$this->numberToWords($num % 100);
            }

            return trim($text);
        }

        foreach ([10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand'] as $divisor => $label) {
            if ($num >= $divisor) {
                $text = $this->numberToWords(intdiv($num, $divisor)).' '.$label;
                if ($num % $divisor) {
                    $text .= ' '.$this->numberToWords($num % $divisor);
                }

                return trim($text);
            }
        }

        return (string) $num;
    }

    public function rupeesInWords($value): string
    {
        if ($value === '' || $value === null) {
            return '';
        }

        $numericValue = round((float) str_replace(',', '', (string) $value));

        return trim($this->numberToWords($numericValue).' Rupees Only');
    }

    public function getFontForWidth(string $value, float $widthLimit, float $baseSize = 8.4): string
    {
        $length = strlen((string) $value);
        $estimatedWidth = $length * ($baseSize * 0.55);

        if ($estimatedWidth > $widthLimit) {
            $shrunkSize = ($widthLimit / $length) / 0.55;
            $finalSize = number_format(max(6.5, min($baseSize, $shrunkSize)), 1);

            return 'font-size: '.$finalSize.'pt;';
        }

        return '';
    }

    public function prepareLorryReceiptData($invoice): array
    {
        $fields = $invoice->fields ?? collect();
        $fv = fn ($keys, $fallback = '') => $this->fieldValue($fields, $keys, $fallback);
        $num = fn ($keys) => $this->parseNumber($keys, $fields);

        $lorryHireNumber = $num(['Lorry Hire', 'Lorry Hire Amount']);
        $otherChargesNumber = $num(['Add Other Charges', 'Other Charges Amount']);
        $advanceNumber = $num(['Advance Paid Rs', 'Advance Amount']) ?? 0;

        $grossHireNumber = $num(['Gross Hire Amount', 'Gross Hire Rupees'])
            ?? $this->sumAmounts([$lorryHireNumber, $otherChargesNumber]);

        $balanceNumber = $num(['Balance Amount', 'Balance Rupees']);
        if ($balanceNumber === null && $grossHireNumber !== null) {
            $balanceNumber = $grossHireNumber - $advanceNumber;
        }

        $detentionNumber = $num(['Add Detention Rs.', 'Detention Amount']);
        $extraHireNumber = $num(['Extra Hire Rs', 'Extra Hire Amount']);
        $finalOtherNumber = $num(['Other Rs', 'Final Other Amount']);
        $lessAdvanceOtherBranchNumber = $num(['Less Adv. at other branch', 'Less Advance Other Branch Amount']);
        $lessDeductionClaimsNumber = $num(['Less Deduction for Claims', 'Less Deduction Claims Amount']);

        $hasFinalPaymentOperation = collect([
            $detentionNumber,
            $extraHireNumber,
            $finalOtherNumber,
            $lessAdvanceOtherBranchNumber,
            $lessDeductionClaimsNumber,
            $num(['Final Total Extra Amount']),
            $num(['Grand Total']),
            $num(['Total Less Amount']),
            $num(['Net Amount Payable']),
        ])->contains(fn ($value) => $value !== null);

        $finalTotalExtraNumber = $num(['Final Total Extra Amount'])
            ?? $this->sumAmounts([$detentionNumber, $extraHireNumber, $finalOtherNumber]);

        $grandTotalNumber = $num(['Grand Total']);
        if ($grandTotalNumber === null && $hasFinalPaymentOperation && $balanceNumber !== null) {
            $grandTotalNumber = $balanceNumber + ($finalTotalExtraNumber ?? 0);
        }

        $deductionTotalNumber = $num(['Total Less Amount'])
            ?? $this->sumAmounts([$lessAdvanceOtherBranchNumber, $lessDeductionClaimsNumber]);

        if ($deductionTotalNumber === null && $hasFinalPaymentOperation) {
            $deductionTotalNumber = 0;
        }

        $netAmountPayableNumber = $num(['Net Amount Payable']);
        if ($netAmountPayableNumber === null && $grandTotalNumber !== null) {
            $netAmountPayableNumber = $grandTotalNumber - ($deductionTotalNumber ?? 0);
        }

        return [
            'fv' => $fv,
            'num' => $num,
            'formatAmount' => fn ($v) => $this->formatAmount($v),
            'rupeesInWords' => fn ($v) => $this->rupeesInWords($v),
            'splitLines' => fn ($v, $w) => $this->splitLines($v, $w),
            'addressLine' => fn ($k, $w, $i) => $this->addressLine($fields, $k, $w, $i),
            'getFontForWidth' => fn ($v, $w, $b = 8.4) => $this->getFontForWidth($v, $w, $b),
            'lorryHireAmount' => $fv(['Lorry Hire', 'Lorry Hire Amount']),
            'otherChargesAmount' => $fv(['Add Other Charges', 'Other Charges Amount']),
            'advanceAmount' => $fv(['Advance Paid Rs', 'Advance Amount']),
            'grossHireRupees' => $this->formatAmount($grossHireNumber),
            'grossHireRupeesOnly' => $fv('Gross Hire Rupees') ?: $this->rupeesInWords($this->formatAmount($grossHireNumber)),
            'balanceAmount' => $this->formatAmount($balanceNumber),
            'balanceRupeesOnly' => $fv('Balance Rupees Only') ?: $this->rupeesInWords($this->formatAmount($balanceNumber)),
            'detentionAmount' => $fv(['Add Detention Rs.', 'Detention Amount']),
            'extraHireAmount' => $fv(['Extra Hire Rs', 'Extra Hire Amount']),
            'finalOtherAmount' => $fv(['Other Rs', 'Final Other Amount']),
            'lessAdvanceOtherBranchAmount' => $fv(['Less Adv. at other branch', 'Less Advance Other Branch Amount']),
            'lessDeductionClaimsAmount' => $fv(['Less Deduction for Claims', 'Less Deduction Claims Amount']),
            'finalTotalExtraAmount' => $this->formatAmount($finalTotalExtraNumber),
            'grandTotalAmount' => $this->formatAmount($grandTotalNumber),
            'totalLessAmount' => $this->formatAmount($deductionTotalNumber),
            'netAmountPayable' => $this->formatAmount($netAmountPayableNumber),
            'finalRupeesOnly' => $fv('Final Rupees Only') ?: $this->rupeesInWords($this->formatAmount($netAmountPayableNumber)),
            'cTotalAmount' => $this->formatAmount($num('Gross Hire Amount') ?? $grossHireNumber),
        ];
    }
}
