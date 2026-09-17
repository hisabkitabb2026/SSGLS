@php
    $normalize = fn ($value) => strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $value));
    $fieldValue = function ($fields, $keys) use ($normalize) {
        $keys = array_map($normalize, (array) $keys);
        $matchedValue = null;

        foreach ($fields as $field) {
            $customField = $field->customField ?? null;
            $labels = [
                $customField->label ?? '',
                $customField->name ?? '',
            ];

            $matches = collect($labels)
                ->contains(fn ($label) => in_array($normalize($label), $keys, true));

            if (! $matches) {
                continue;
            }

            if (trim((string) $field->defaultAnswer) !== '') {
                return (string) $field->defaultAnswer;
            }

            $matchedValue = (string) $field->defaultAnswer;
        }

        return $matchedValue ?? '';
    };
    $fv = function ($keys, $fallback = '') use ($fieldValue, $invoice) {
        $value = '';
        foreach ((array) $keys as $key) {
            // Convert spaces to underscores so "No Of Pages" → "no_of_pages",
            // matching the actual snake_case column names on the invoices table.
            $normalizedKey = strtolower(str_replace(' ', '_', trim($key)));

            // Map common label-to-column aliases where the display label
            // doesn't directly match the database column name.
            $columnAliases = [
                'e_way_bill_no' => 'eway_bill_no',
                'charge_weight' => 'charged_weight',
                'lorry_no' => 'truck_no',
                'model' => 'vehicle_model',
                'owner_phone_no' => 'owner_phone',
                'lorry_hire_contract_no' => 'contract_no',
                'driver_rto' => 'driver_rto_address',
                'final_balance_date' => 'final_balance_on',
                'final_cash_cheque_no' => 'final_cash_cheque_no',
                'final_balance_code' => 'final_balance_paid_at',
                'final_balance_amount_paid_at' => 'final_balance_paid_at',
                'received_no_of_bilties' => 'received_no_bilties',
            ];
            $normalizedKey = $columnAliases[$normalizedKey] ?? $normalizedKey;

            if ($normalizedKey === 'gst_no' || $normalizedKey === 'gstin') {
                if (isset($invoice->gstin) && trim((string)$invoice->gstin) !== '') {
                    $value = $invoice->gstin;
                    break;
                }
                if (isset($invoice->gst_no) && trim((string)$invoice->gst_no) !== '') {
                    $value = $invoice->gst_no;
                    break;
                }
            }

            // Direct mapping for From / To location
            if ($normalizedKey === 'from') {
                if (isset($invoice->from_name) && trim((string)$invoice->from_name) !== '') {
                    $value = $invoice->from_name;
                    break;
                }
                if (isset($invoice->from_code) && trim((string)$invoice->from_code) !== '') {
                    $value = $invoice->from_code;
                    break;
                }
            }
            if ($normalizedKey === 'to') {
                if (isset($invoice->to_name) && trim((string)$invoice->to_name) !== '') {
                    $value = $invoice->to_name;
                    break;
                }
                if (isset($invoice->to_code) && trim((string)$invoice->to_code) !== '') {
                    $value = $invoice->to_code;
                    break;
                }
            }

            // Check if column exists directly on the invoice model
            if (isset($invoice->$normalizedKey) && trim((string)$invoice->$normalizedKey) !== '') {
                $value = $invoice->$normalizedKey;
                break;
            }
            
            // Check camelCase versions
            $camelKey = \Illuminate\Support\Str::camel($normalizedKey);
            if (isset($invoice->$camelKey) && trim((string)$invoice->$camelKey) !== '') {
                $value = ($camelKey === 'invoicePdfUrl' ? $invoice->invoicePdfUrl : $invoice->$camelKey);
                break;
            }
        }

        if (trim((string)$value) === '') {
            $value = $fieldValue($invoice->fields, $keys);
        }

        return trim((string) $value) === '' ? $fallback : $value;
    };
    $v = fn ($keys, $fallback = '') => $fv($keys, $fallback);
    $number = function ($keys) use ($fv) {
        $value = trim(str_replace(',', '', (string) $fv($keys)));

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        $amount = (float) $value;

        return (float) (int) $amount === $amount ? (int) $amount : $amount;
    };
    $sumAmounts = function (array $values) {
        $values = collect($values)->filter(fn ($value) => $value !== null);

        return $values->isEmpty() ? null : $values->sum();
    };
    $formatAmount = function ($value) {
        if ($value === null || $value === '') {
            return '';
        }

        if (! is_numeric($value)) {
            return (string) $value;
        }

        $amount = (float) $value;

        return (string) ((float) (int) $amount === $amount ? (int) $amount : $amount);
    };
    $splitLines = function ($value, array $widths) {
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
    };
    $addressLine = fn ($keys, array $widths, int $index) => $splitLines($v($keys), $widths)[$index] ?? '';
    $numberToWords = function ($num) use (&$numberToWords) {
        $num = (int) $num;
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
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
            return trim($ones[intdiv($num, 100)].' Hundred '.($num % 100 ? $numberToWords($num % 100) : ''));
        }
        foreach ([10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand'] as $divisor => $label) {
            if ($num >= $divisor) {
                return trim($numberToWords(intdiv($num, $divisor)).' '.$label.' '.($num % $divisor ? $numberToWords($num % $divisor) : ''));
            }
        }
    };
    $rupeesInWords = fn ($value) => $value === '' || $value === null ? '' : trim($numberToWords(round((float) str_replace(',', '', (string) $value))).' Rupees Only');
    $lorryHireAmount = $fv(['Lorry Hire', 'Lorry Hire Amount']);
    $otherChargesAmount = $fv(['Add Other Charges', 'Other Charges Amount']);
    $advanceAmount = $fv(['Advance Paid Rs', 'Advance Amount']);
    $lorryHireNumber = $number(['Lorry Hire', 'Lorry Hire Amount']);
    $otherChargesNumber = $number(['Add Other Charges', 'Other Charges Amount']);
    $advanceNumber = $number(['Advance Paid Rs', 'Advance Amount']) ?? 0;
    $grossHireNumber = $number(['Gross Hire Amount', 'Gross Hire Rupees'])
        ?? $sumAmounts([$lorryHireNumber, $otherChargesNumber]);
    $grossHireRupees = $formatAmount($grossHireNumber);
    $balanceNumber = $number(['Balance Amount', 'Balance Rupees']);
    if ($balanceNumber === null && $grossHireNumber !== null) {
        $balanceNumber = $grossHireNumber - $advanceNumber;
    }
    $balanceAmount = $formatAmount($balanceNumber);
    $grossHireRupeesOnly = $fv('Gross Hire Rupees') ?: $rupeesInWords($grossHireRupees);
    $balanceRupeesOnly = $fv('Balance Rupees Only') ?: $rupeesInWords($balanceAmount);
    $detentionAmount = $fv(['Add Detention Rs.', 'Detention Amount']);
    $extraHireAmount = $fv(['Extra Hire Rs', 'Extra Hire Amount']);
    $finalOtherAmount = $fv(['Other Rs', 'Final Other Amount']);
    $lessAdvanceOtherBranchAmount = $fv(['Less Adv. at other branch', 'Less Advance Other Branch Amount']);
    $lessDeductionClaimsAmount = $fv(['Less Deduction for Claims', 'Less Deduction Claims Amount']);
    $detentionNumber = $number(['Add Detention Rs.', 'Detention Amount']);
    $extraHireNumber = $number(['Extra Hire Rs', 'Extra Hire Amount']);
    $finalOtherNumber = $number(['Other Rs', 'Final Other Amount']);
    $lessAdvanceOtherBranchNumber = $number(['Less Adv. at other branch', 'Less Advance Other Branch Amount']);
    $lessDeductionClaimsNumber = $number(['Less Deduction for Claims', 'Less Deduction Claims Amount']);
    $hasFinalPaymentOperation = collect([
        $detentionNumber,
        $extraHireNumber,
        $finalOtherNumber,
        $lessAdvanceOtherBranchNumber,
        $lessDeductionClaimsNumber,
        $number(['Final Total Extra Amount']),
        $number(['Grand Total']),
        $number(['Total Less Amount']),
        $number(['Net Amount Payable']),
    ])->contains(fn ($value) => $value !== null);
    $finalTotalExtraNumber = $number(['Final Total Extra Amount'])
        ?? $sumAmounts([$detentionNumber, $extraHireNumber, $finalOtherNumber]);
    $grandTotalNumber = $number(['Grand Total']);
    if ($grandTotalNumber === null && $hasFinalPaymentOperation && $balanceNumber !== null) {
        $grandTotalNumber = $balanceNumber + ($finalTotalExtraNumber ?? 0);
    }
    $deductionTotalNumber = $number(['Total Less Amount'])
        ?? $sumAmounts([$lessAdvanceOtherBranchNumber, $lessDeductionClaimsNumber]);
    if ($deductionTotalNumber === null && $hasFinalPaymentOperation) {
        $deductionTotalNumber = 0;
    }
    $netAmountPayableNumber = $number(['Net Amount Payable']);
    if ($netAmountPayableNumber === null && $grandTotalNumber !== null) {
        $netAmountPayableNumber = $grandTotalNumber - ($deductionTotalNumber ?? 0);
    }
    $finalTotalExtraAmount = $formatAmount($finalTotalExtraNumber);
    $grandTotalAmount = $formatAmount($grandTotalNumber);
    $totalLessAmount = $formatAmount($deductionTotalNumber);
    $netAmountPayable = $formatAmount($netAmountPayableNumber);
    $finalRupeesValue = $netAmountPayable !== '' ? $netAmountPayable : '';
    $finalRupeesOnly = $fv('Final Rupees Only') ?: $rupeesInWords($finalRupeesValue);
    $company = $invoice->company ?? ($lorryReceipt->company ?? null);
    $companyName = $company?->name ?: '';
    $companyInitials = collect(preg_split('/\s+/', trim($companyName)))
        ->filter()
        ->map(fn ($word) => mb_substr($word, 0, 1))
        ->take(2)
        ->implode('');
    $companyTagline = $company?->tagline ?: '';
    $companyPhone = $company?->address?->phone ?: '';
    $companyId = $invoice->company_id ?? ($company->id ?? null);
    $companyEmail = $company?->address?->email
        ?: ($company?->notification_email
            ?: ($companyId ? \App\Models\CompanySetting::getSetting('notification_email', $companyId) : ''));
    $logo = $logo ?? ($company->logo_path ?? null);
    $displayCompanyAddress = trim(strip_tags((string) ($company_address ?? '')))
        ? preg_replace('/^\s*<h[1-6][^>]*>.*?<\/h[1-6]>\s*/is', '', (string) $company_address)
        : '';
    if ($companyName) {
        $cleanNamePattern = '/^\s*(?:<[^>]+>)*\s*' . preg_quote($companyName, '/') . '\s*(?:<\/[^>]+>)*\s*(?:<br\s*\/?>)?/i';
        $displayCompanyAddress = preg_replace($cleanNamePattern, '', $displayCompanyAddress);
    }
    $displayCompanyAddress = preg_replace('/(?:<br\s*\/?>|\s)*E-?mail\s*:?\s*[^<\r\n]+/i', '', $displayCompanyAddress);
    $displayCompanyAddress = preg_replace('/(?:<br\s*\/?>|\s)*Mob(?:ile)?\.?\s*:?\s*[^<\r\n]+/i', '', $displayCompanyAddress);
    if ($companyPhone) {
        $displayCompanyAddress = preg_replace('/(?:<br\s*\/?>|\s)*'.preg_quote($companyPhone, '/').'\s*/i', '', $displayCompanyAddress);
    }
    if ($companyEmail) {
        $displayCompanyAddress = preg_replace('/(?:<br\s*\/?>|\s)*'.preg_quote($companyEmail, '/').'\s*/i', '', $displayCompanyAddress);
    }
    if ($displayCompanyAddress === '' && $company) {
        $address = $company->address;
        $displayCompanyAddress = implode('<br>', array_filter([
            e($address?->address_street_1),
            e($address?->address_street_2),
            e(trim(implode(' ', array_filter([$address?->city, $address?->state, $address?->zip])))),
            e($address?->country_name),
        ]));
    }
    $cTotalAmount = $formatAmount($number('Gross Hire Amount') ?? $grossHireNumber);
    $imageDataUri = function ($path) {
        if (! $path || ! file_exists($path)) {
            return null;
        }
        $mime = mime_content_type($path) ?: '';
        if (! str_starts_with($mime, 'image/')) {
            return null;
        }
        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    };
    $lorryAttachments = collect($lorryDocumentCollections ?? [])->map(function ($label, $collection) use ($invoice, $imageDataUri) {
        if (! method_exists($invoice, 'getFirstMedia')) {
            return null;
        }
        $media = $invoice->getFirstMedia($collection);
        if (! $media) {
            return null;
        }
        return ['label' => $label, 'name' => $media->file_name, 'mime' => $media->mime_type, 'image' => $imageDataUri($media->getPath())];
    });
    $getFontForWidth = function ($value, $widthLimit, $baseSize = 8.4) {
        $length = strlen((string) $value);
        $estimatedWidth = $length * ($baseSize * 0.55);
        if ($estimatedWidth > $widthLimit) {
            $shrunkSize = ($widthLimit / $length) / 0.55;
            return 'font-size: ' . number_format(max(6.5, min($baseSize, $shrunkSize)), 1) . 'pt;';
        }
        return '';
    };
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lorry Receipt - {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 0; size: 612pt 1008pt; }
        * { box-sizing: border-box; }
        body { color: #222; font-family: Arial, Helvetica, sans-serif; margin: 0; }
        .sheet { border: 1.35pt solid #222; height: 960pt; left: 36pt; position: absolute; top: 16pt; width: 540pt; }
        .section-b-content .t { font-size: 8pt; }
        .section-b-content .line { font-size: 8.5pt; }
        .a { position: absolute; }
        .box { border: .75pt solid #222; position: absolute; }
        .section-box { border: 1.15pt solid #222; position: absolute; }
        .section-outline { border: 1.2pt solid #111; position: absolute; }
        .sig-box { border: .75pt solid #222; position: absolute; text-align: center; }
        .top { border-top: 1.15pt solid #222; left: 0; position: absolute; width: 540pt; }
        .v { border-left: .75pt solid #222; position: absolute; }
        .h { border-top: .75pt solid #222; position: absolute; }
        .t { font-size: 8.5pt; line-height: 10.5pt; position: absolute; white-space: nowrap; }
        .small { font-size: 7.5pt; line-height: 8.5pt; }
        .tiny { font-size: 7.5pt; line-height: 8.5pt; }
        .b { font-weight: bold; }
        .c { text-align: center; }
        .r { text-align: right; }
        .brand { font-family: "Arial Narrow", Arial, Helvetica, sans-serif; font-size: 18pt; font-weight: bold; line-height: 19pt; white-space: nowrap; }
        .sub { font-size: 8.5pt; font-weight: bold; line-height: 9.5pt; }
        .logo { max-height: 63pt; max-width: 96pt; }
        .letter { font-size: 14pt; font-weight: bold; line-height: 14pt; position: absolute; }
        .title { font-size: 11pt; font-weight: bold; line-height: 12pt; position: absolute; }
        .line { border-bottom: .7pt solid #222; font-size: 9pt; font-weight: bold; height: 12pt; line-height: 10pt; overflow: hidden; padding-left: 1pt; position: absolute; white-space: nowrap; }
        .sig { border-top: .75pt solid #222; height: 1pt; position: absolute; }
        .mini-box { border: .75pt solid #222; font-size: 9.5pt; height: 15pt; line-height: 12.5pt; padding-left: 4pt; position: absolute; }
        .e-label { font-size: 8.5pt; line-height: 9.5pt; overflow: hidden; position: absolute; text-align: right; white-space: nowrap; }
        .e-rs { font-size: 9pt; font-weight: bold; line-height: 9.5pt; overflow: hidden; position: absolute; white-space: nowrap; }
        .e-amt { font-size: 8.5pt; font-weight: bold; line-height: 9.5pt; overflow: hidden; position: absolute; white-space: nowrap; }
        .attachments-page { page-break-before: always; padding: 30pt 34pt; }
        .attachments-title { font-size: 15pt; font-weight: bold; margin-bottom: 14pt; text-align: center; }
        .attachments-grid { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .attachment-cell { border: .8pt solid #222; height: 274pt; padding: 8pt; vertical-align: top; width: 50%; }
        .attachment-label { font-size: 9pt; font-weight: bold; margin-bottom: 6pt; }
        .attachment-image { max-height: 234pt; max-width: 238pt; }
        .attachment-pdf { border: .7pt solid #777; font-size: 9pt; height: 228pt; padding-top: 82pt; text-align: center; }
        .attachment-name { font-size: 7pt; margin-top: 5pt; word-break: break-all; }
    </style>
</head>
<body>
<div class="sheet">
    {{-- Company Header: Logo left, Name/Address/Phone centered --}}
    <div class="a c" style="left:8pt; top:10pt; width:96pt; height:72pt;">@if($logo && file_exists($logo))<img class="logo" src="{{ \App\Support\Pdf\ImageUtils::toBase64Src($logo) }}">@else<div class="brand" style="font-size:26pt; line-height:28pt;">{{ $companyInitials }}</div>@endif</div>
    <div class="a c" style="left:110pt; top:8pt; width:320pt;">
        <div class="brand" style="font-size:22pt; line-height:24pt;">{{ $companyName }}</div>
        @if($companyTagline)<div class="sub" style="font-size:10pt; line-height:12pt; margin-top:2pt;">{{ $companyTagline }}</div>@endif
        <div style="width:318pt; font-size:9.5pt; line-height:12pt; margin-top:4pt;">{!! $displayCompanyAddress !!}</div>
        <div style="width:318pt; font-size:9.5pt; line-height:12pt; margin-top:2pt;">@if($companyPhone)Mob. {{ $companyPhone }}@endif @if($companyEmail) &nbsp;|&nbsp; E-mail : {{ $companyEmail }}@endif</div>
    </div>

    {{-- Horizontal Info Bar: From, To, Challan, Pages, Pkgs, Actual Wt, Charge Wt, Lorry No, Rate, Dist Kms --}}
    <div class="box" style="left:0; top:104pt; width:540pt; height:44pt;"></div>
    <div class="h" style="left:0; top:122pt; width:540pt;"></div>
    {{-- 10 vertical dividers, each column 54pt wide --}}
    <div class="v" style="left:54pt; top:104pt; height:44pt;"></div>
    <div class="v" style="left:108pt; top:104pt; height:44pt;"></div>
    <div class="v" style="left:162pt; top:104pt; height:44pt;"></div>
    <div class="v" style="left:216pt; top:104pt; height:44pt;"></div>
    <div class="v" style="left:270pt; top:104pt; height:44pt;"></div>
    <div class="v" style="left:324pt; top:104pt; height:44pt;"></div>
    <div class="v" style="left:378pt; top:104pt; height:44pt;"></div>
    <div class="v" style="left:432pt; top:104pt; height:44pt;"></div>
    <div class="v" style="left:486pt; top:104pt; height:44pt;"></div>
    {{-- Labels (top row) --}}
    <div class="t c" style="left:0; top:107pt; width:54pt;">From</div>
    <div class="t c" style="left:54pt; top:107pt; width:54pt;">To</div>
    <div class="t c" style="left:108pt; top:107pt; width:54pt;">Challan No.</div>
    <div class="t c" style="left:162pt; top:107pt; width:54pt;">No. Of Pages</div>
    <div class="t c" style="left:216pt; top:107pt; width:54pt;">No. Pkgs.</div>
    <div class="t c" style="left:270pt; top:107pt; width:54pt;">Actual Wt.</div>
    <div class="t c" style="left:324pt; top:107pt; width:54pt;">Charge Wt.</div>
    <div class="t c" style="left:378pt; top:107pt; width:54pt;">Lorry No.</div>
    <div class="t c" style="left:432pt; top:107pt; width:54pt;">Rate</div>
    <div class="t c" style="left:486pt; top:107pt; width:54pt;">Dist. Kms.</div>
    {{-- Values (bottom row, bold) --}}
    <div class="t c b" style="left:0; top:126pt; width:54pt; font-size:9.5pt;">{{ $v('From') }}</div>
    <div class="t c b" style="left:54pt; top:126pt; width:54pt; font-size:9.5pt;">{{ $v('To') }}</div>
    <div class="t c b" style="left:108pt; top:126pt; width:54pt; font-size:9.5pt;">{{ $invoice->invoice_number }}</div>
    <div class="t c b" style="left:162pt; top:126pt; width:54pt; font-size:9.5pt;">{{ $v('No Of Pages') }}</div>
    <div class="t c b" style="left:216pt; top:126pt; width:54pt; font-size:9.5pt;">{{ $v('No Of Packages') }}</div>
    <div class="t c b" style="left:270pt; top:126pt; width:54pt; font-size:9pt;">{{ $v('Actual Weight') }}</div>
    <div class="t c b" style="left:324pt; top:126pt; width:54pt; font-size:9pt;">{{ $v('Charge Weight') }}</div>
    <div class="t c b" style="left:378pt; top:126pt; width:54pt; font-size:9pt;">{{ $v('Lorry No') }}</div>
    <div class="t c b" style="left:432pt; top:126pt; width:54pt; font-size:9.5pt;">{{ $v('Rate') }}</div>
    <div class="t c b" style="left:486pt; top:126pt; width:54pt; font-size:9.5pt;">{{ $v('Distance Kms') }}</div>
    <div class="top" style="top:158pt;"></div>

    <div class="section-outline" style="left:0; top:158pt; width:540pt; height:48pt;"></div><div class="section-box" style="left:0; top:158pt; width:18pt; height:16pt;"></div><div class="letter" style="left:3pt; top:162pt;">A</div><div class="section-box" style="left:18pt; top:158pt; width:522pt; height:16pt;"></div><div class="title" style="left:34pt; top:163pt; width:200pt;">VEHICLE PARTICULARS</div>
    <div class="t" style="left:6pt; top:181pt;">Regd at</div><div class="line" style="left:37pt; top:180pt; width:70pt;">{{ $v(['Regd at', 'Registered At']) }}</div><div class="t" style="left:108pt; top:181pt;">Body Type</div><div class="line" style="left:149pt; top:180pt; width:72pt;">{{ $v('Body Type') }}</div><div class="t" style="left:223pt; top:181pt;">Make</div><div class="line" style="left:247pt; top:180pt; width:82pt;">{{ $v('Make') }}</div><div class="t" style="left:331pt; top:181pt;">Model</div><div class="line" style="left:359pt; top:180pt; width:67pt;">{{ $v('Model') }}</div><div class="t" style="left:428pt; top:181pt;">Colour</div><div class="line" style="left:457pt; top:180pt; width:58pt;">{{ $v('Colour') }}</div>
    <div class="t" style="left:6pt; top:197pt;">Chasis No.</div><div class="line" style="left:46pt; top:196pt; width:150pt;">{{ $v('Chasis No') }}</div><div class="t" style="left:198pt; top:197pt;">Engine No.</div><div class="line" style="left:241pt; top:196pt; width:107pt;">{{ $v('Engine No') }}</div><div class="t" style="left:350pt; top:197pt;">Fitness Validity</div><div class="line" style="left:412pt; top:196pt; width:70pt;"></div><div class="t" style="left:482pt; top:197pt;">20</div><div class="line" style="left:494pt; top:196pt; width:22pt;"></div>
    <div class="top" style="top:211pt;"></div>

    <div class="section-outline" style="left:0; top:211pt; width:540pt; height:180pt;"></div><div class="v" style="left:172pt; top:211pt; height:180pt;"></div><div class="v" style="left:374pt; top:211pt; height:180pt;"></div>
    <div class="section-box" style="left:0; top:211pt; width:18pt; height:16pt;"></div><div class="letter" style="left:3pt; top:215pt;">B</div><div class="title" style="left:30pt; top:216pt;">OWNER</div><div class="title" style="left:178pt; top:216pt;">DRIVER</div><div class="title" style="left:380pt; top:216pt;">BROKER</div>
    <div class="section-b-content">
        <div class="t" style="left:6pt; top:235pt;">Name</div><div class="line" style="left:28pt; top:234pt; width:139pt; {{ $getFontForWidth($splitLines($v('Owner Name'), [30, 34])[0] ?? '', 139, 8.2) }}">{{ $splitLines($v('Owner Name'), [30, 34])[0] ?? '' }}</div><div class="line" style="left:28pt; top:252pt; width:139pt; {{ $getFontForWidth($splitLines($v('Owner Name'), [30, 34])[1] ?? '', 139, 8.2) }}">{{ $splitLines($v('Owner Name'), [30, 34])[1] ?? '' }}</div><div class="t" style="left:6pt; top:271pt;">Full Address</div><div class="line" style="left:57pt; top:270pt; width:110pt;">{{ $addressLine('Owner Address', [25, 36, 36], 0) }}</div><div class="line" style="left:6pt; top:289pt; width:161pt;">{{ $addressLine('Owner Address', [25, 36, 36], 1) }}</div><div class="line" style="left:6pt; top:307pt; width:161pt;">{{ $addressLine('Owner Address', [25, 36, 36], 2) }}</div><div class="t" style="left:6pt; top:325pt;">Phone No.</div><div class="line" style="left:43pt; top:324pt; width:124pt;">{{ $v('Owner Phone No') }}</div><div class="t" style="left:6pt; top:343pt;">Owner PAN No .</div><div class="line" style="left:68pt; top:342pt; width:99pt;">{{ $v(['Owner PAN No', 'Financer Name']) }}</div><div class="t" style="left:6pt; top:361pt;">Bank A/c No.</div><div class="line" style="left:58pt; top:360pt; width:109pt;">{{ $v('Owner Bank Account No') }}</div>
        <div class="t" style="left:178pt; top:235pt;">Name</div><div class="line" style="left:201pt; top:234pt; width:167pt; {{ $getFontForWidth($splitLines($v('Driver Name'), [36, 40])[0] ?? '', 167, 8.2) }}">{{ $splitLines($v('Driver Name'), [36, 40])[0] ?? '' }}</div><div class="line" style="left:201pt; top:252pt; width:167pt; {{ $getFontForWidth($splitLines($v('Driver Name'), [36, 40])[1] ?? '', 167, 8.2) }}">{{ $splitLines($v('Driver Name'), [36, 40])[1] ?? '' }}</div><div class="t" style="left:178pt; top:271pt;">Full Address</div><div class="line" style="left:229pt; top:270pt; width:139pt;">{{ $addressLine('Driver Address', [25, 36, 36], 0) }}</div><div class="line" style="left:178pt; top:289pt; width:190pt;">{{ $addressLine('Driver Address', [25, 36, 36], 1) }}</div><div class="line" style="left:178pt; top:307pt; width:190pt;">{{ $addressLine('Driver Address', [25, 36, 36], 2) }}</div><div class="t" style="left:178pt; top:325pt;">Licence No.</div><div class="line" style="left:224pt; top:324pt; width:144pt;">{{ $v('Driver Licence No') }}</div><div class="t" style="left:178pt; top:343pt;">Issued Dt.</div><div class="line" style="left:223pt; top:342pt; width:45pt;">{{ $v(['Driver Licence Date', 'Issued Dt.']) }}</div><div class="t" style="left:270pt; top:343pt;">Valid Dt.</div><div class="line" style="left:306pt; top:342pt; width:62pt;">{{ $v('Driver Valid Up To') }}</div><div class="t" style="left:178pt; top:361pt;">RTO</div><div class="line" style="left:198pt; top:360pt; width:170pt;">{{ $v('Driver RTO') }}</div>
        <div class="t" style="left:380pt; top:235pt;">Name</div><div class="line" style="left:405pt; top:234pt; width:130pt;">{{ $splitLines($v('Broker Name'), [34, 40])[0] ?? '' }}</div>
        <div class="line" style="left:380pt; top:254pt; width:155pt;">{{ $splitLines($v('Broker Name'), [34, 40])[1] ?? '' }}</div>
        <div class="t" style="left:380pt; top:273pt;">Full Address</div><div class="line" style="left:431pt; top:273pt; width:104pt;">{{ $addressLine('Broker Address', [24, 38, 38, 38], 0) }}</div>
        <div class="line" style="left:380pt; top:291pt; width:155pt;">{{ $addressLine('Broker Address', [24, 38, 38, 38], 1) }}</div>
        <div class="line" style="left:380pt; top:309pt; width:155pt;">{{ $addressLine('Broker Address', [24, 38, 38, 38], 2) }}</div>
        <div class="line" style="left:380pt; top:327pt; width:155pt;">{{ $addressLine('Broker Address', [24, 38, 38, 38], 3) }}</div>
        <div class="t" style="left:380pt; top:345pt;">Broker Pan No.</div><div class="line" style="left:439pt; top:344pt; width:96pt;">{{ $v('Broker Pan No') }}</div>
        <div class="t" style="left:380pt; top:363pt;">Phone No.</div><div class="line" style="left:421pt; top:362pt; width:114pt;">{{ $v('Broker Phone No') }}</div>
    </div>
    <div class="top" style="top:391pt;"></div>

    <div class="section-outline" style="left:0; top:391pt; width:540pt; height:175pt;"></div><div class="section-box" style="left:0; top:391pt; width:18pt; height:16pt;"></div><div class="letter" style="left:3pt; top:394pt;">C</div><div class="section-box" style="left:18pt; top:391pt; width:522pt; height:16pt;"></div><div class="title" style="left:34pt; top:395pt; width:200pt;">HIRE PARTICULARS</div><div class="v" style="left:430pt; top:391pt; height:175pt;"></div><div class="h" style="left:430pt; top:426pt; width:90pt;"></div><div class="h" style="left:430pt; top:451pt; width:90pt;"></div><div class="h" style="left:430pt; top:471pt; width:90pt;"></div><div class="h" style="left:430pt; top:491pt; width:90pt;"></div><div class="h" style="left:430pt; top:511pt; width:90pt;"></div>
    @php
        $paidToVal = $v('Paid To');
        $ownerBankAccount = $v('Owner Bank Account No');
        if (trim($ownerBankAccount) !== '') {
            $paidToVal .= ' (A/c No: ' . $ownerBankAccount . ')';
        }
    @endphp
    <div class="t" style="left:22pt; top:417pt;">Paid to Shri</div><div class="line" style="left:72pt; top:416pt; width:160pt; {{ $getFontForWidth($paidToVal, 160) }}">{{ $paidToVal }}</div><div class="t" style="left:235pt; top:417pt;">Lorry Hire (Rate X Wt.)</div><div class="t r" style="left:305pt; top:439pt; width:92pt;">Add Other Charges</div><div class="t" style="left:22pt; top:466pt;">Gross Hire Rupees</div><div class="line" style="left:95pt; top:465pt; width:250pt;">{{ $grossHireRupeesOnly }}</div><div class="t" style="left:348pt; top:466pt;">Only</div>
    <div class="t" style="left:22pt; top:485pt;">Advance Paid by Cash/Cheque No.</div><div class="line" style="left:152pt; top:484pt; width:68pt; {{ $getFontForWidth($v(['Advance Paid by Cash/Cheque No', 'Advance Cash Cheque No']), 68) }}">{{ $v(['Advance Paid by Cash/Cheque No', 'Advance Cash Cheque No']) }}</div><div class="t" style="left:224pt; top:485pt;">On</div><div class="line" style="left:237pt; top:484pt; width:43pt;">{{ $v('Advance On') }}</div><div class="t" style="left:284pt; top:485pt;">Bank</div><div class="line" style="left:305pt; top:484pt; width:100pt; {{ $getFontForWidth($v(['Bank', 'Advance Bank']), 100) }}">{{ $v(['Bank', 'Advance Bank']) }}</div><div class="t" style="left:22pt; top:504pt;">Balance Payable at</div><div class="line" style="left:95pt; top:503pt; width:115pt;">{{ $v(['Balance Payable at', 'Balance Payable At']) }}</div><div class="t" style="left:218pt; top:504pt;">Rupees</div><div class="line" style="left:251pt; top:503pt; width:80pt;">{{ $balanceAmount }}</div><div class="line" style="left:22pt; top:523pt; width:328pt;">{{ preg_replace('/(?:\s+only)+$/i', '', (string) $balanceRupeesOnly) }}</div><div class="t" style="left:350pt; top:523pt;">Only</div>
    <div class="t b" style="left:405pt; top:416pt;">Rs.:</div><div class="t b tiny" style="left:440pt; top:416pt; width:70pt;">{{ $lorryHireAmount }}</div><div class="t b" style="left:405pt; top:436pt;">Rs.:</div><div class="t b tiny" style="left:440pt; top:436pt; width:70pt;">{{ $otherChargesAmount }}</div><div class="t b" style="left:405pt; top:461pt;">Rs.:</div><div class="t b tiny" style="left:440pt; top:461pt; width:70pt;">{{ $cTotalAmount }}</div><div class="t b" style="left:405pt; top:481pt;">Rs.:</div><div class="t b tiny" style="left:440pt; top:481pt; width:70pt;">{{ $advanceAmount }}</div><div class="t b" style="left:405pt; top:501pt;">Rs.:</div><div class="t b tiny" style="left:440pt; top:501pt; width:70pt;">{{ $balanceAmount }}</div>
    <div class="sig" style="left:22pt; top:551pt; width:83pt;"></div><div class="t c" style="left:38pt; top:551pt; width:70pt;">Passed by</div><div class="sig" style="left:167pt; top:551pt; width:105pt;"></div><div class="t c" style="left:184pt; top:551pt; width:80pt;">Certified by</div><div class="sig" style="left:318pt; top:551pt; width:97pt;"></div><div class="t c" style="left:334pt; top:551pt; width:80pt;">Prepared by</div><div class="sig-box" style="left:442pt; top:531pt; width:87pt; height:24pt; font-size:7.5pt; font-weight:bold; line-height:8.5pt; padding-top:2pt;">ADVANCE<br>RECD BY ME</div><div class="top" style="top:566pt;"></div>

    <div class="section-outline" style="left:0; top:566pt; width:540pt; height:40pt;"></div><div class="section-box" style="left:0; top:566pt; width:18pt; height:16pt;"></div><div class="letter" style="left:3pt; top:569pt;">D</div><div class="section-box" style="left:18pt; top:566pt; width:522pt; height:16pt;"></div><div class="title" style="left:34pt; top:571pt;">LOADING REMARKS</div><div class="t" style="left:275pt; top:591pt;">Loaded by</div><div class="line" style="left:317pt; top:591pt; width:197pt;">{{ $v('Loaded By') }}</div><div class="top" style="top:606pt;"></div>

    <div class="section-outline" style="left:0; top:606pt; width:540pt; height:225pt;"></div><div class="section-box" style="left:0; top:606pt; width:18pt; height:16pt;"></div><div class="letter" style="left:3pt; top:610pt;">E</div><div class="section-box" style="left:18pt; top:606pt; width:522pt; height:16pt;"></div><div class="title" style="left:34pt; top:611pt;">FINAL PAYMENT PARTICULARS</div>
    <div class="v" style="left:438pt; top:606pt; height:225pt;"></div><div class="h" style="left:438pt; top:644pt; width:82pt;"></div><div class="h" style="left:438pt; top:664pt; width:82pt;"></div><div class="h" style="left:438pt; top:684pt; width:82pt;"></div><div class="h" style="left:438pt; top:704pt; width:82pt;"></div><div class="h" style="left:438pt; top:724pt; width:82pt;"></div>

    @php
        $finalPaidToVal = $v('Final Paid To');
        if (trim($ownerBankAccount) !== '') {
            $finalPaidToVal .= ' (A/c No: ' . $ownerBankAccount . ')';
        }
    @endphp
    <div class="t" style="left:22pt; top:643pt;">Paid to shri</div><div class="line" style="left:75pt; top:642pt; width:350pt; {{ $getFontForWidth($finalPaidToVal, 350) }}">{{ $finalPaidToVal }}</div>
    <div class="e-label" style="left:318pt; top:648pt; width:96pt;">Balance Payable</div><div class="e-rs" style="left:419pt; top:648pt; width:17pt;">Rs.:</div><div class="e-amt" style="left:450pt; top:648pt; width:58pt;">{{ $balanceAmount }}</div>

    <div class="t" style="left:22pt; top:664pt;">Add&nbsp; Detention&nbsp; Rs.</div><div class="mini-box" style="left:96pt; top:663pt; width:42pt;">{{ $detentionAmount !== '' ? $detentionAmount : 'I' }}</div>
    <div class="t" style="left:146pt; top:664pt;">Extra&nbsp; Hire&nbsp; Rs.</div><div class="mini-box" style="left:204pt; top:663pt; width:42pt;">{{ $extraHireAmount !== '' ? $extraHireAmount : 'II' }}</div>
    <div class="t" style="left:256pt; top:664pt;">Other Rs.</div><div class="mini-box" style="left:294pt; top:663pt; width:38pt;">{{ $finalOtherAmount !== '' ? $finalOtherAmount : 'III' }}</div>
    <div class="e-label" style="left:336pt; top:665pt; width:78pt;">Total I+II+III</div><div class="e-rs" style="left:419pt; top:665pt; width:17pt;">Rs.:</div><div class="e-amt" style="left:450pt; top:665pt; width:58pt;">{{ $finalTotalExtraAmount }}</div>

    <div class="e-label" style="left:336pt; top:684pt; width:78pt;">Grand Total</div><div class="e-rs" style="left:419pt; top:684pt; width:17pt;">Rs.:</div><div class="e-amt" style="left:450pt; top:684pt; width:58pt;">{{ $grandTotalAmount }}</div>

    <div class="t" style="left:32pt; top:704pt;">Less Adv. at other branch</div><div class="mini-box" style="left:130pt; top:703pt; width:48pt;">{{ $lessAdvanceOtherBranchAmount !== '' ? $lessAdvanceOtherBranchAmount : 'IV' }}</div>
    <div class="t" style="left:198pt; top:704pt;">Less Deduction for Claims</div><div class="mini-box" style="left:294pt; top:703pt; width:38pt;">{{ $lessDeductionClaimsAmount !== '' ? $lessDeductionClaimsAmount : 'V' }}</div>
    <div class="e-label" style="left:336pt; top:705pt; width:78pt;">Total (IV+V)</div><div class="e-rs" style="left:419pt; top:706pt; width:17pt;">Rs.:</div><div class="e-amt" style="left:450pt; top:706pt; width:58pt;">{{ $totalLessAmount }}</div>

    <div class="t" style="left:32pt; top:724pt;">Final Balance Amount Paid at</div><div class="mini-box" style="left:145pt; top:723pt; width:50pt;">{{ $v(['Final Balance Amount Paid at', 'Final Balance Code']) }}</div>
    <div class="t" style="left:226pt; top:724pt;">On</div><div class="mini-box" style="left:242pt; top:723pt; width:64pt;">{{ $v('Final Balance Date') }}</div>
    <div class="e-label" style="left:310pt; top:725pt; width:104pt;">Net Amount payable</div><div class="e-rs" style="left:419pt; top:727pt; width:17pt;">Rs.:</div><div class="e-amt" style="left:450pt; top:727pt; width:58pt;">{{ $netAmountPayable }}</div>

    <div class="t" style="left:22pt; top:744pt;">Cash/Cheque No.</div><div class="line" style="left:88pt; top:743pt; width:120pt;">{{ $v(['Cash/Cheque No.', 'Final Cash Cheque No']) }}</div>
    <div class="t" style="left:212pt; top:744pt;">On</div><div class="line" style="left:225pt; top:743pt; width:60pt;">{{ $v(['Final Cash Cheque On', 'Final Balance Date']) }}</div><div class="t" style="left:290pt; top:744pt;">Bank</div><div class="line" style="left:312pt; top:743pt; width:95pt; {{ $getFontForWidth($v('Final Bank'), 95) }}">{{ $v('Final Bank') }}</div>
    <div class="t" style="left:22pt; top:764pt;">Rupees</div><div class="line" style="left:53pt; top:763pt; width:380pt;">{{ $finalRupeesOnly }}</div><div class="t" style="left:418pt; top:764pt;">Only</div>
    <div class="sig-box" style="left:449pt; top:777pt; width:80pt; height:24pt; font-size:7.2pt; font-weight:bold; line-height:8pt; padding-top:9pt;">FINAL PAYMENT<br>RECD BY ME</div>

    <div class="sig" style="left:20pt; top:801pt; width:80pt;"></div><div class="t c" style="left:30pt; top:806pt; width:75pt;">Passed by</div><div class="sig" style="left:157pt; top:801pt; width:105pt;"></div><div class="t c" style="left:172pt; top:806pt; width:80pt;">Certified by</div><div class="sig" style="left:315pt; top:801pt; width:90pt;"></div><div class="t c" style="left:326pt; top:806pt; width:85pt;">Prepared by</div><div class="top" style="top:831pt;"></div>

    {{-- UNDERTAKING SECTION — formatted to use full available space below Section E --}}
    <div class="section-outline" style="left:0; top:831pt; width:540pt; height:129pt;"></div>
    <div class="section-box" style="left:0; top:831pt; width:18pt; height:16pt;"></div><div class="letter" style="left:3pt; top:835pt;">F</div><div class="section-box" style="left:18pt; top:831pt; width:522pt; height:16pt;"></div><div class="title" style="left:34pt; top:836pt; width:200pt;">UNDERTAKING</div>

    {{-- Undertaking text — larger font, proper line spacing --}}
    <div class="t" style="left:7pt; top:854pt; width:526pt; white-space:normal; line-height:11pt; font-size:8.5pt;">Please pay the freight if the goods are delivered in full and in good and proper conditions, fulfilling all the terms and conditions.</div>
    <div class="t" style="left:7pt; top:876pt; width:526pt; white-space:normal; line-height:11pt; font-size:8.5pt;"><span class="b">Note :</span> The weight noted in challan is mostly correct, it is the responsibility of the owner/driver to weight the vehicle before leaving the starting point. In no case the company should be held liable for damage of penalty whatsoever due to overloading but extra lorry hire may be paid by the company.</div>

    {{-- Recd. No. of Bilties — label centered in box, reduced height --}}
    <div class="box" style="left:7pt; top:908pt; width:225pt; height:28pt;"></div>
    <div class="t c b" style="left:7pt; top:914pt; width:225pt; font-size:8.5pt;">Recd. No. of Bilties</div>
    <div class="t c b" style="left:7pt; top:924pt; width:225pt; font-size:10pt;">{{ $v('Received No Of Bilties') }}</div>

    {{-- ORIGINAL / PAYMENT COPY — text above signature, no box --}}
    <div class="t c b" style="left:380pt; top:916pt; width:150pt; font-size:8.5pt; line-height:10pt;">ORIGINAL<br>PAYMENT COPY</div>

    {{-- Signature area for Driver / Owner --}}
    <div class="sig" style="left:380pt; top:934pt; width:150pt;"></div>
    <div class="t c r" style="left:380pt; top:938pt; width:150pt; font-size:8pt;">Left Thumb Impression or Signature of Driver / Owner</div>

    {{-- Bottom note --}}
    <div class="t b" style="left:8pt; top:948pt; font-size:8pt;">Note : <span style="font-weight:normal;">Payment will be made on only production of original copy.</span></div>
</div>

@if($lorryAttachments->isNotEmpty())
    <div class="attachments-page">
        <div class="attachments-title">Lorry Receipt Documents</div>
        <table class="attachments-grid" cellspacing="0" cellpadding="0">
            @foreach($lorryAttachments->chunk(2) as $row)
                <tr>
                    @foreach($row as $attachment)
                        <td class="attachment-cell">
                            <div class="attachment-label">{{ $attachment['label'] }}</div>
                            @if($attachment['image'])
                                <div class="c"><img class="attachment-image" src="{{ $attachment['image'] }}"></div>
                            @else
                                <div class="attachment-pdf">PDF document uploaded<div class="attachment-name">{{ $attachment['name'] }}</div></div>
                            @endif
                        </td>
                    @endforeach
                    @if($row->count() === 1)<td class="attachment-cell">&nbsp;</td>@endif
                </tr>
            @endforeach
        </table>
    </div>
@endif
</body>
</html>
