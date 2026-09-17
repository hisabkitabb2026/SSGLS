<!DOCTYPE html>
<html>

<head>
    <title>Bill - {{ $invoice->invoice_number }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

    <style type="text/css">
        @page {
            margin: 10mm;
            size: 297mm 210mm;
        }

        * {
            box-sizing: border-box;
        }

        @media print {
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
            .footer { page-break-inside: avoid; }
            .words-row { page-break-inside: avoid; }
        }

        body {
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            margin: 0;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        td,
        th {
            border: 1px solid #666;
            padding: 4px 6px;
            vertical-align: top;
        }

        .no-border {
            border: 0 !important;
        }

        .invoice-shell {
            width: 100%;
        }

        .invoice-shell > .master {
            border: 2px solid #000;
        }

        .jurisdiction {
            font-size: 11px;
            line-height: 13px;
            margin-bottom: 2px;
            text-align: right;
            text-decoration: underline;
        }

        .master {
            border: 0;
            table-layout: fixed;
        }

        .master > tbody > tr > td,
        .master > tbody > tr > th {
            padding: 0;
        }

        .left-zone {
            width: 65%;
        }

        .right-zone {
            width: 35%;
        }

        .right-zone table td:first-child,
        .right-zone table th:first-child {
            border-left: 0;
        }

        .brand-row {
            background: #f8f8f8;
            border-bottom: 2px solid #000;
            min-height: 104px;
            table-layout: fixed;
        }

        .brand-row td {
            border-left: 0;
            border-right: 0;
            border-top: 0;
            padding: 6px 8px;
            vertical-align: middle;
        }

        .logo-cell {
            text-align: center;
            width: 20%;
        }

        .company-logo {
            max-height: 100px;
            max-width: 150px;
        }

        .brand-fallback {
            color: #111;
            font-size: 34px;
            font-weight: bold;
            line-height: 34px;
            padding-top: 8px;
        }

        .brand-fallback span {
            display: block;
            font-size: 11px;
            letter-spacing: 0;
            line-height: 13px;
        }

        .company-cell {
            text-align: center;
            width: 80%;
        }

        .company-name {
            font-family: "Arial Narrow", Arial, Helvetica, sans-serif;
            font-size: 30px;
            font-weight: bold;
            line-height: 32px;
            margin-top: 1px;
        }

        .company-tagline {
            font-size: 16px;
            font-weight: bold;
            line-height: 18px;
        }

        .company-address {
            font-size: 13px;
            line-height: 15px;
            margin-top: 4px;
            text-align: center;
        }

        .company-contact {
            font-size: 13px;
            font-weight: bold;
            line-height: 15px;
            margin-top: 2px;
            text-align: center;
        }

        .branch-box {
            min-height: 76px;
            line-height: 14px;
            padding: 4px 6px !important;
            vertical-align: middle;
            white-space: normal;
            word-break: break-word;
        }

        .branch-label {
            font-size: 14px;
            font-weight: bold;
        }

        .branch-address {
            display: block;
            line-height: 14px;
            margin-top: 2px;
            overflow-wrap: anywhere;
            white-space: normal;
        }

        .tax-box {
            min-height: 26px;
            padding: 5px 6px !important;
        }

        .tax-box div {
            font-size: 13px;
            font-weight: bold;
            line-height: 16px;
            overflow: hidden;
            overflow-wrap: anywhere;
            white-space: normal;
            word-break: break-all;
        }

        .party-box {
            border-top: 0 !important;
            min-height: 130px;
            padding: 0 !important;
        }

        .party-head {
            table-layout: fixed;
        }

        .party-head td {
            border-bottom: 0;
            border-left: 0;
            border-right: 0;
            border-top: 0;
            font-size: 19px;
            min-height: 18px;
            padding: 3px 8px;
        }

        .party-address-lines {
            font-size: 16px;
            line-height: 20px;
            overflow: hidden;
            padding: 3px 8px 20px;
        }

        .party-display-name {
            font-size: 17px;
            font-weight: normal;
            margin-bottom: 2px;
        }

        .party-gstin {
            border-top: 1px solid #111;
            bottom: 0;
            font-size: 16px;
            line-height: 20px;
            overflow: hidden;
            padding: 4px 8px;
            text-overflow: clip;
            white-space: nowrap;
        }

        .party-gstin b {
            font-size: 19px;
        }

        .bill-details td {
            font-size: 13px;
            min-height: 16px;
            padding: 3px 6px;
            vertical-align: middle;
        }

        .payment-table th,
        .payment-table td {
            border: 1px solid #000;
            font-size: 11px;
            min-height: 20px;
            overflow: hidden;
            padding: 3px 4px;
            text-align: center;
            vertical-align: middle;
            word-break: break-word;
        }

        .basis-row {
            font-size: 12px;
            line-height: 15px;
            min-height: 23px;
            padding: 3px 6px !important;
        }

        .items thead tr:first-child th {
            border-top: 2px solid #000;
        }

        .items th {
            background-color: #e8e8e8;
            border: 1px solid #000;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 0.3px;
            line-height: 14px;
            padding: 5px 3px;
            text-align: center;
            text-transform: uppercase;
            vertical-align: middle;
        }

        .items .group-head th {
            min-height: 20px;
        }

        .items {
            table-layout: fixed;
        }

        .items td {
            border: 0;
            border-bottom: 1px solid #999;
            font-size: 12px;
            padding: 4px 3px;
            text-align: center;
            vertical-align: top;
        }

        .items tbody tr.alt-row td {
            background-color: #f7f7f7;
        }

        .items tbody tr:last-child td {
            border-bottom: 1px solid #000;
        }

        .words-row {
            border-top: 2px solid #000;
        }

        .words-row td {
            background-color: #e0e0e0;
            font-size: 12px;
            padding: 6px 8px;
            vertical-align: middle;
        }

        .grand-label {
            background-color: #d0d0d0;
            font-size: 14px;
            font-weight: bold;
            text-align: center;
        }

        .grand-total-value {
            background-color: #d0d0d0;
            font-size: 16px;
            font-weight: bold;
        }

        .footer td {
            border: 1px solid #666;
            font-size: 12px;
            padding: 5px 7px;
        }

        .footer-head td {
            min-height: 20px;
        }

        .footer-body td {
            min-height: 58px;
        }

        .terms {
            font-size: 12px;
            line-height: 17px;
        }

        .term-item {
            margin-bottom: 4px;
            padding-left: 12px;
        }

        .prepared {
            font-size: 11px;
            line-height: 14px;
            overflow: hidden;
            vertical-align: top !important;
            word-break: break-word;
        }

        .emp-box {
            border: 1px solid #111;
            display: inline-block;
            line-height: 14px;
            margin-top: 4px;
            min-height: 38px;
            overflow: hidden;
            padding-top: 9px;
            text-align: center;
            width: 52px;
            word-break: break-all;
        }

        .for-company {
            font-family: "Arial Narrow", Arial, Helvetica, sans-serif;
            font-size: 15px;
            font-weight: bold;
            line-height: 18px;
            text-align: center;
        }

        .signature-cell {
            overflow: hidden;
        }

        .signature-table {
            border-collapse: collapse;
            width: 100%;
        }

        .signature-table td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }

        .sig-emp {
            text-align: center;
            width: 60px;
        }

        .sig-image-area {
            min-height: 42px;
            text-align: center;
        }

        .signature-image {
            display: block;
            margin: 0 auto;
            max-height: 42px;
            max-width: 180px;
            object-fit: contain;
        }

        .sig-label {
            border-top: 1px solid #000;
            font-size: 12px;
            font-weight: bold;
            padding-top: 4px;
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }

        .text-left {
            text-align: left !important;
        }

        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }

        .highlight-value {
            background: #f0f0f0;
            display: inline;
            font-weight: bold;
            line-height: 1.4;
            padding: 2px 8px;
        }

        .tax-box div {
            font-size: 15px;
        }

        .tax-box .highlight-value {
            font-size: 15px;
        }

        .bill-details td {
            font-size: 14px;
            overflow: hidden;
            word-break: break-word;
        }

        .bill-details .highlight-value {
            font-size: 15px;
            white-space: nowrap;
        }

        .words-row .highlight-value {
            font-size: 14px;
        }

        .footer-head .highlight-value {
            font-size: 14px;
        }

        .sig-label {
            font-size: 15px;
            letter-spacing: 1px;
            padding-top: 6px;
        }
    </style>
</head>

<body>
@php
    $normalize = function ($value) {
        return strtoupper(trim(preg_replace('/[^A-Z0-9]+/i', '_', (string) $value), '_'));
    };

    $fieldValue = function ($fields, $keys) use ($normalize) {
        $keys = collect((array) $keys)->map($normalize);

        foreach ($fields as $field) {
            if (! $field->customField) {
                continue;
            }

            $candidates = collect([
                $field->customField->slug,
                $field->customField->name,
                $field->customField->label,
            ])->filter()->map($normalize);

            foreach ($keys as $key) {
                if (
                    $candidates->contains($key)
                    || $candidates->contains('CUSTOM_INVOICE_'.$key)
                    || $candidates->contains('CUSTOM_ITEM_'.$key)
                    || $candidates->contains('CUSTOM_CUSTOMER_'.$key)
                ) {
                    return $field->defaultAnswer;
                }
            }
        }

        return '';
    };

    $numericField = function ($value) {
        return (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
    };

    $numberToWords = function ($number) use (&$numberToWords) {
        $number = (int) $number;

        if ($number === 0) {
            return 'Zero';
        }

        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        if ($number < 20) {
            return $ones[$number];
        }

        if ($number < 100) {
            return trim($tens[intdiv($number, 10)].' '.$ones[$number % 10]);
        }

        if ($number < 1000) {
            $remainder = $number % 100;

            return trim($ones[intdiv($number, 100)].' Hundred'.($remainder ? ' '.$numberToWords($remainder) : ''));
        }

        if ($number < 100000) {
            $remainder = $number % 1000;

            return trim($numberToWords(intdiv($number, 1000)).' Thousand'.($remainder ? ' '.$numberToWords($remainder) : ''));
        }

        if ($number < 10000000) {
            $remainder = $number % 100000;

            return trim($numberToWords(intdiv($number, 100000)).' Lakh'.($remainder ? ' '.$numberToWords($remainder) : ''));
        }

        $remainder = $number % 10000000;

        return trim($numberToWords(intdiv($number, 10000000)).' Crore'.($remainder ? ' '.$numberToWords($remainder) : ''));
    };

    $invoiceField = function ($keys) use ($invoice, $fieldValue) {
        foreach ((array) $keys as $key) {
            $normalizedKey = strtolower(trim($key));
            
            // Map common aliases to native columns
            if ($normalizedKey === 'e_way_bill_no') {
                $normalizedKey = 'eway_bill_no';
            }
            if ($normalizedKey === 'gst_no' || $normalizedKey === 'gstin') {
                if (isset($invoice->gstin) && trim((string)$invoice->gstin) !== '') {
                    return $invoice->gstin;
                }
                if (isset($invoice->gst_no) && trim((string)$invoice->gst_no) !== '') {
                    return $invoice->gst_no;
                }
            }

            // Direct mapping for From / To location
            if ($normalizedKey === 'from') {
                if (isset($invoice->from_name) && trim((string)$invoice->from_name) !== '') {
                    return $invoice->from_name;
                }
                if (isset($invoice->from_code) && trim((string)$invoice->from_code) !== '') {
                    return $invoice->from_code;
                }
            }
            if ($normalizedKey === 'to') {
                if (isset($invoice->to_name) && trim((string)$invoice->to_name) !== '') {
                    return $invoice->to_name;
                }
                if (isset($invoice->to_code) && trim((string)$invoice->to_code) !== '') {
                    return $invoice->to_code;
                }
            }

            // Check if column exists directly on the invoice model
            if (isset($invoice->$normalizedKey) && trim((string)$invoice->$normalizedKey) !== '') {
                return $invoice->$normalizedKey;
            }
            
            // Check camelCase versions
            $camelKey = \Illuminate\Support\Str::camel($normalizedKey);
            if (isset($invoice->$camelKey) && trim((string)$invoice->$camelKey) !== '') {
                return $camelKey === 'invoicePdfUrl' ? $invoice->invoicePdfUrl : $invoice->$camelKey;
            }
        }

        // Fallback to custom fields relationship
        return $fieldValue($invoice->fields, $keys);
    };

    $customerField = function ($keys) use ($invoice, $fieldValue) {
        return $invoice->customer ? $fieldValue($invoice->customer->fields, $keys) : '';
    };

    $itemField = function ($item, $keys) use ($fieldValue, $normalize) {
        // Check native item columns first (transport fields are now native
        // columns on invoice_items, not custom fields).
        foreach ((array) $keys as $key) {
            $normalizedKey = strtolower(trim($key));

            // Map template field names to actual database column names
            $columnMap = [
                'from' => 'from_code',
                'to' => 'to_code',
                'destination' => 'to_code',
                'vehicle_no' => 'truck_no',
                'vehicle_number' => 'truck_no',
                'consignment_no' => 'consignment_number',
                'consignment_number' => 'consignment_number',
                'old_bill_number' => 'consignment_number',
                'old_bill_date' => 'consignment_date',
                'date' => 'consignment_date',
                'invoice_no' => 'party_inv_no',
                'invoice_number' => 'party_inv_no',
                'package' => 'pkg',
                'packages' => 'pkg',
                'charged_weight_kgs' => 'weight',
                'charged_weight' => 'weight',
            ];

            $columnName = $columnMap[$normalizedKey] ?? $normalizedKey;

            if (isset($item->$columnName) && trim((string) $item->$columnName) !== '') {
                return $item->$columnName;
            }

            // Also check the original key name directly
            if (isset($item->$normalizedKey) && trim((string) $item->$normalizedKey) !== '') {
                return $item->$normalizedKey;
            }
        }

        // Fallback to custom fields relationship
        return $fieldValue($item->fields, $keys);
    };

    $companyName = $invoice->company?->name ?: '';
    $companyInitials = collect(preg_split('/\s+/', trim($companyName)))
        ->filter()
        ->map(fn ($word) => mb_substr($word, 0, 1))
        ->take(2)
        ->implode('');
    $billingBranch = $invoice->company?->billing_branch ?: $invoiceField(['billing_branch_name_address', 'billing_branch_address', 'billing_branch']);
    $billingBranchHtml = preg_replace('/<br\s*\/?>/i', "\n", (string) $billingBranch);
    $billingBranchHtml = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n", $billingBranchHtml);
    $billingBranchHtml = preg_replace('/<\/?p[^>]*>/i', "\n", $billingBranchHtml);
    $billingBranchText = html_entity_decode(strip_tags($billingBranchHtml), ENT_QUOTES, 'UTF-8');
    $billingBranchLines = collect(preg_split('/\r\n|\r|\n/', $billingBranchText))
        ->map(fn ($line) => trim(preg_replace('/\s+/', ' ', $line)))
        ->filter()
        ->values();
    $companyTagline = $invoice->company?->tagline ?: '';
    $companyTopHeading = $invoice->company?->top_heading ?: 'Subject to Vapi Jurisdiction';
    $companyGstin = $invoiceField(['gstin', 'gst_no']) ?: ($invoice->company?->gstin ?: '');
    $companyEnrollmentNo = $invoice->company?->enrollment_no ?: $invoiceField(['enrollment_no', 'enrollment']);
    $companyTaxIdentityLabel = $companyEnrollmentNo ? 'Enrollment No' : 'GSTIN';
    $companyTaxIdentityValue = $companyEnrollmentNo ?: $companyGstin;
    $panNo = $invoiceField(['pan_no', 'pan']) ?: ($invoice->company?->pan_no ?: '');
    $partyGstin = $invoice->customer->tax_id ?: $customerField(['gstin', 'gst_no']);
    $partyCode = $invoiceField(['party_code']);
    $branchCode = $invoiceField(['branch_code']);
    $tickBillType = $invoiceField(['tick_bill_type', 'bill_type']);
    $basisOfCharges = $invoiceField(['basis_of_charges', 'basis']);
    $enclosures = $invoiceField(['enclosures']);
    $gstTaxThrough = $invoice->gst_tax_payable_by ?: $invoiceField(['gst_tax_through', 'service_tax_through']);

    $empCode = $invoiceField(['emp_code', 'employee_code']);
    $preparedBy = $invoiceField(['prepared_by']);
    $checkedBy = $invoiceField(['checked_by']);
    $billingAddress = $invoice->customer?->billingAddress;
    $partyDisplayName = $billingAddress?->name ?: $invoice->customer?->display_name ?: $invoice->customer?->name;
    $partyAddressLines = collect();

    if ($billingAddress) {
        $cityState = collect([$billingAddress->city, $billingAddress->state])->filter()->implode(', ');
        $cityStateZip = collect([$cityState, $billingAddress->zip])->filter()->implode(' ');
        $phone = $billingAddress->phone ?: ($invoice->customer?->phone ?? null);

        $partyAddressLines = collect([
            $billingAddress->address_street_1,
            $billingAddress->address_street_2,
            $cityStateZip,
            $billingAddress->country?->name,
            $phone ? 'Phone: ' . $phone : null,
        ])->filter()->values();
    }

    if ($partyAddressLines->isEmpty()) {
        $partyAddressHtml = preg_replace('/<br\s*\/?>/i', "\n", (string) $billing_address);
        $partyAddressHtml = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n", $partyAddressHtml);
        $partyAddressHtml = preg_replace('/<\/?p[^>]*>/i', "\n", $partyAddressHtml);
        $partyAddressText = html_entity_decode(strip_tags($partyAddressHtml), ENT_QUOTES, 'UTF-8');
        $partyAddressLines = collect(preg_split('/\r\n|\r|\n/', $partyAddressText))
            ->map(fn ($line) => trim(preg_replace('/\s+/', ' ', $line)))
            ->reject(fn ($line) => $partyDisplayName && strcasecmp($line, $partyDisplayName) === 0)
            ->filter()
            ->values();

        if (! $partyDisplayName && $partyAddressLines->isNotEmpty()) {
            $partyDisplayName = $partyAddressLines->shift();
        }
    }
    $companyPhone = $invoice->company?->address?->phone;
    $companyEmail = $invoice->company?->address?->email ?: ($invoice->company?->notification_email ?: \App\Models\CompanySetting::getSetting('notification_email', $invoice->company_id));
    $mobile = $invoiceField(['mobile', 'phone']) ?: ($companyPhone ?: '');
    $email = $invoiceField(['email']) ?: ($companyEmail ?: '');
    $displayCompanyAddress = preg_replace('/^\s*<h[1-6][^>]*>.*?<\/h[1-6]>\s*/is', '', (string) $company_address);
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
    $officeGrandTotal = 0;
    $signaturePath = base_path('resources/static/img/PDF/authorized_signature.jpeg');
    $userSignatureMedia = auth()->user()?->getMedia('user_signature')->first();
    $userSignaturePath = $userSignatureMedia ? $userSignatureMedia->getPath() : null;

    // Auto-fit font sizing: shrinks font size for text that would overflow
    // its container. Each block shrinks independently so other blocks are
    // not disturbed. Uses the same pattern as lorry_receipt.blade.php.
    // $widthLimit is in px (matching the template's unit system).
    // $baseSize is the default font size; $minSize is the floor.
    $getFontForWidth = function ($value, $widthLimit, $baseSize = 11.5, $minSize = 6.5) {
        $length = strlen((string) $value);
        if ($length === 0) {
            return '';
        }
        $estimatedWidth = $length * ($baseSize * 0.55);
        if ($estimatedWidth > $widthLimit) {
            $shrunkSize = ($widthLimit / $length) / 0.55;
            return 'font-size: ' . number_format(max($minSize, min($baseSize, $shrunkSize)), 1) . 'px;';
        }
        return '';
    };

    // Pre-calculate auto-fit styles for key fields that commonly overflow.
    // Widths are estimated from the landscape A4 layout (297mm ≈ 1123px at 96 DPI).
    // Party box is 50% of left-zone (65% of page) ≈ 349px usable.
    $partyDisplayNameStyle = $getFontForWidth($partyDisplayName, 330, 11.5, 6.5);
    // Party GSTIN box is 55% of party box ≈ 184px usable, nowrap.
    $partyGstinStyle = $getFontForWidth($partyGstin, 170, 16, 6.5);
    // Company name is in center cell (63% of left zone) ≈ 450px usable.
    $companyNameStyle = $getFontForWidth($companyName, 430, 27, 10);
    // Billing branch address is in right zone (35%) ≈ 370px usable.
    $branchAddressStyle = $getFontForWidth($billingBranchLines->implode(' '), 360, 11, 6.5);
    // Item table column widths (percentages of ~1123px page width).
    // From/Destination: 7% ≈ 73px usable.
    // Consignment No: 7% ≈ 73px. Vehicle No: 9% ≈ 88px. Invoice No: 8% ≈ 78px.
@endphp


    <div class="jurisdiction">{{ $companyTopHeading }}</div>
    <div class="invoice-shell">
        <table class="master">
            <tr>
                <td class="left-zone">
                    <table class="brand-row">
                        <tr>
                            <td class="logo-cell">
                                @if ($logo)
                                    <img class="company-logo" src="{{ \App\Support\Pdf\ImageUtils::toBase64Src($logo) }}" alt="Company Logo">
                                @else
                                    <div class="brand-fallback">{{ $companyInitials }}</div>
                                @endif
                            </td>
                            <td class="company-cell">
                                <div class="company-name" style="{{ $companyNameStyle }}">{{ $companyName }}</div>
                                <div class="company-tagline">{{ $companyTagline }}</div>
                                <div class="company-address">{!! $displayCompanyAddress !!}</div>
                                <div class="company-contact">Mob. {{ $mobile }} &nbsp;|&nbsp; E-mail : {{ $email }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="right-zone branch-box">
                    <span class="branch-label">Billing Br. Name & Address :</span>
                    <span class="branch-address">{!! nl2br(e($billingBranchLines->implode("\n"))) ?: '&nbsp;' !!}</span>
                </td>
            </tr>

            <tr>
                <td rowspan="3" class="party-box">
                    <table class="party-head">
                        <tr>
                            <td width="50%"><b>Party Name & Address :</b></td>
                            <td><b>Party Code :</b> {{ $partyCode }}</td>
                        </tr>
                        <tr>
                            <td></td>
                            <td class="party-gstin" style="{{ $partyGstinStyle }}"><b>GSTIN :</b> {{ $partyGstin }}</td>
                        </tr>
                    </table>
                    <div class="party-address-lines">
                        <div class="party-display-name" style="{{ $partyDisplayNameStyle }}">{{ $partyDisplayName }}</div>
                        {!! nl2br(e($partyAddressLines->implode("\n"))) ?: "\u{00A0}" !!}
                    </div>
                </td>
                <td class="tax-box">
                    <div>PAN No.: <span class="highlight-value">{{ $panNo }}</span></div>
                    <div>{{ $companyTaxIdentityLabel }} : <span class="highlight-value">{{ $companyTaxIdentityValue }}</span></div>
                </td>
            </tr>
            <tr>
                <td>
                    <table class="bill-details">
                        <tr>
                            <td width="50%"><b>Bill No.:</b> <span class="highlight-value">{{ $invoice->invoice_number }}</span></td>
                            <td><b>Branch Code :</b> {{ $branchCode }}</td>
                        </tr>
                        <tr>
                            <td><b>Bill Date :</b> <span class="highlight-value">{{ $invoice->formattedInvoiceDate }}</span></td>
                            <td><b>Due Date :</b> {{ $invoice->formattedDueDate }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td>
                    <table class="payment-table">
                        <tr>
                            <th rowspan="2" width="16%">Tick<br>Bill Type<br>{{ $tickBillType }}</th>
                            <th>Cash</th>
                            <th>Cheque No.</th>
                            <th>Date</th>
                            <th>Bank</th>
                            <th>Others</th>
                        </tr>
                        <tr>
                            <td>{{ $invoiceField(['cash']) }}</td>
                            <td>{{ $invoiceField(['cheque_no']) }}</td>
                            <td>{{ $invoiceField(['payment_date']) }}</td>
                            <td>{{ $invoiceField(['bank']) }}</td>
                            <td>{{ $invoiceField(['others']) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="items">
            <colgroup>
                <col style="width: 3%;">
                <col style="width: 7%;">
                <col style="width: 7%;">
                <col style="width: 8%;">
                <col style="width: 6.8%;">
                <col style="width: 6.8%;">
                <col style="width: 9%;">
                <col style="width: 5.5%;">
                <col style="width: 7.4%;">
                <col style="width: 8.5%;">
                <col style="width: 6.8%;">
                <col style="width: 6.8%;">
                <col style="width: 6.8%;">
                <col style="width: 12.2%;">
            </colgroup>
            <thead>
                <tr class="group-head">
                    <th rowspan="2">Sl.<br>No.</th>
                    <th colspan="2">Consignment / Old Bill</th>
                    <th rowspan="2">Invoice<br>No.</th>
                    <th colspan="2">Destination</th>
                    <th rowspan="2">Vehicle No.</th>
                    <th rowspan="2">Pkg.</th>
                    <th rowspan="2">Charged<br>Weight Kgs.</th>
                    <th rowspan="2">Rate</th>
                    <th rowspan="2">Other Charge</th>
                    <th rowspan="2">LR Charge</th>
                    <th rowspan="2">DD Charge</th>
                    <th rowspan="2">Amount</th>
                </tr>
                <tr>
                    <th>Number</th>
                    <th>Date</th>
                    <th>From</th>
                    <th>To</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $slNo = 0;
                @endphp
                @foreach ($invoice->items as $item)
                    @php
                        $rate = $itemField($item, ['rate']);
                        $otherCharge = $itemField($item, ['other_charge']);
                        $lrCharge = $itemField($item, ['lr_charge']);
                        $ddCharge = $itemField($item, ['dd_charge']);
                        $amount = $itemField($item, ['amount']);

                        // Auto-fit styles for item table cells that commonly overflow.
                        // Column widths: consignment_no 7%≈73px, from 6.8%≈71px,
                        // destination 6.8%≈71px, vehicle_no 9%≈88px, invoice_no 8%≈78px.
                        $consignmentNo = $itemField($item, ['consignment_no', 'consignment_number', 'old_bill_number']);
                        $consignmentDate = $itemField($item, ['consignment_date', 'old_bill_date', 'date']);
                        $partyInvNo = $itemField($item, ['party_inv_no', 'invoice_no', 'invoice_number']);

                        $fromPlace = $itemField($item, ['from']);
                        $toPlace = $itemField($item, ['destination', 'to']);
                        $vehicleNo = $itemField($item, ['vehicle_no', 'vehicle_number']);
                        $pkg = $itemField($item, ['pkg', 'package', 'packages']);
                        $weight = $itemField($item, ['weight', 'charged_weight_kgs', 'charged_weight']);

                        $consignmentNoStyle = $getFontForWidth($consignmentNo, 65, 11.8, 6.5);
                        $fromStyle = $getFontForWidth($fromPlace, 63, 11.8, 6.5);
                        $toStyle = $getFontForWidth($toPlace, 63, 11.8, 6.5);
                        $vehicleNoStyle = $getFontForWidth($vehicleNo, 80, 11.8, 6.5);
                        $partyInvNoStyle = $getFontForWidth($partyInvNo, 70, 11.8, 6.5);

                        $calculatedAmount = null;

                        if ($rate !== '' || $otherCharge !== '' || $lrCharge !== '' || $ddCharge !== '') {
                            $calculatedAmount = (int) round((
                                $numericField($rate)
                                + $numericField($otherCharge)
                                + $numericField($lrCharge)
                                + $numericField($ddCharge)
                            ) * 100);
                        }

                        $officeLineTotal = $calculatedAmount ?? ($amount !== '' ? (int) round($numericField($amount) * 100) : $item->total);
                        $officeGrandTotal += $officeLineTotal;
                        $slNo++;
                    @endphp
                    <tr class="{{ $slNo % 2 === 0 ? 'alt-row' : '' }}">
                        <td>{{ $slNo }}</td>
                        <td style="{{ $consignmentNoStyle }}">{{ $consignmentNo }}</td>
                        <td>{{ $consignmentDate }}</td>
                        <td style="{{ $partyInvNoStyle }}">{{ $partyInvNo }}</td>
                        <td class="text-left" style="{{ $fromStyle }}">{{ $fromPlace }}</td>
                        <td class="text-left" style="{{ $toStyle }}">{{ $toPlace }}</td>
                        <td style="{{ $vehicleNoStyle }}">{{ $vehicleNo }}</td>
                        <td>{{ $pkg }}</td>
                        <td>{{ $weight }}</td>
                        <td class="text-right">{{ $rate }}</td>
                        <td class="text-right">{{ $otherCharge }}</td>
                        <td class="text-right">{{ $lrCharge }}</td>
                        <td class="text-right">{{ $ddCharge }}</td>
                        <td class="text-right">{!! format_money_pdf($officeLineTotal, $invoice->customer->currency) !!}</td>
                    </tr>
                @endforeach
            </tbody>

        </table>

        @php
            $grandTotalForWords = $officeGrandTotal ?: $invoice->total;
            $rupeesInWords = $invoiceField(['rupees_in_words', 'amount_in_words']) ?: trim($numberToWords((int) floor($grandTotalForWords / 100)).' Rupees Only');

        @endphp

        <table class="words-row">
            <colgroup>
                <col style="width: 62%;">
                <col style="width: 20%;">
                <col style="width: 18%;">
            </colgroup>
            <tr>
                <td><b>Rupees in words :</b> <span class="highlight-value">{{ $rupeesInWords }}</span></td>
                <td class="grand-label">GRAND TOTAL</td>
                <td class="text-right bold grand-total-value">{!! format_money_pdf($officeGrandTotal ?: $invoice->total, $invoice->customer->currency) !!}</td>
            </tr>
        </table>

        <table class="footer">
            <tr class="footer-head">
                <td width="42%" class="bold">Enclosures : {{ $enclosures }}</td>
                <td width="20%" colspan="2"><b>GST Through :</b> <span class="highlight-value">{{ $gstTaxThrough }}</span></td>
                <td width="38%" class="text-center">
                    <div class="for-company">For {{ $companyName }}</div>
                </td>
            </tr>
            <tr class="footer-body">
                <td width="42%" class="terms">
                    <div class="term-item">1) Payment should be made by payee A/c Cheque /<br>D.D. Favour of {{ $companyName }}</div>
                    <div class="term-item">2) Interest @ 10% per annum will be charged if bill<br>not paid within 7 days from date of bill</div>
                </td>

                <td width="10%" class="prepared text-center">Prepared by :<br>{{ $preparedBy }}</td>
                <td width="10%" class="prepared text-center">Checked by :<br>{{ $checkedBy }}</td>
                <td width="38%" class="signature-cell">
                    <table class="signature-table">
                        <tr>
                            <td class="sig-emp">
                                <span class="emp-box">EMP Code<br>{{ $empCode }}</span>
                            </td>
                            <td class="sig-image-area">
                                @if ($userSignaturePath && file_exists($userSignaturePath))
                                    <img class="signature-image" src="{{ \App\Support\Pdf\ImageUtils::toBase64Src($userSignaturePath) }}" alt="Signature">
                                @elseif (file_exists($signaturePath))
                                    <img class="signature-image" src="{{ \App\Support\Pdf\ImageUtils::toBase64Src($signaturePath) }}" alt="Signature">
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="font-size: 16px; font-weight: bold; text-align: center; padding-top: 4px;">
                                {{ auth()->user()?->name ?: $preparedBy }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" class="sig-label">Signature</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>
