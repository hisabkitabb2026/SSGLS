<!DOCTYPE html>
<html>

<head>
    <title>LR Receipt - {{ $invoice->invoice_number }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

    <style type="text/css">
        /* ── Page setup: landscape A4 with comfortable print margins ── */
        @page {
            margin: 10mm;
            size: 297mm 210mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            margin: 0;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        /* ── Cell borders: darker for better print contrast, wrapper keeps 2px ── */
        td,
        th {
            border: 1px solid #444;
            padding: 3px 6px;
            vertical-align: top;
        }

        .wrapper {
            page-break-inside: avoid;
            width: 100%;
        }

        .wrapper > table {
            border: 2px solid #000;
        }

        .no-border {
            border: 0;
        }

        /* ── Print rules: repeat headers, avoid row splits across pages ── */
        @media print {
            thead {
                display: table-header-group;
            }
            tr {
                page-break-inside: avoid;
            }
        }

        .jurisdiction {
            font-size: 10px;
            line-height: 12px;
            margin-bottom: 4px;
            text-align: right;
            text-decoration: underline;
        }

        .jurisdiction-top {
            font-size: 10px;
            line-height: 12px;
            margin-bottom: 4px;
            text-align: right;
            text-decoration: underline;
        }

        .header-left {
            border-right: 2px solid #444 !important;
            padding: 0;
            vertical-align: top;
            width: 61%;
        }

        .header-right {
            padding: 0;
            vertical-align: top;
            width: 39%;
        }

        /* ── Brand row: horizontal layout matching office_invoice ── */
        .brand-row {
            background-color: #f8f8f8;
            border-bottom: 2px solid #000;
            table-layout: fixed;
            width: 100%;
        }

        .brand-row td {
            border: 0;
            padding: 6px 8px;
            vertical-align: middle;
        }

        .logo-cell {
            text-align: center;
            width: 20%;
        }

        .company-logo {
            max-height: 72px;
            max-width: 130px;
        }

        .brand-mark {
            color: #27324a;
            font-size: 42px;
            font-weight: bold;
            letter-spacing: -4px;
            line-height: 44px;
            text-align: center;
        }

        .brand-small {
            display: block;
            font-size: 9px;
            letter-spacing: 0;
            line-height: 11px;
            margin-top: 2px;
        }

        .company-cell {
            text-align: left;
            width: 80%;
        }

        .company-name {
            color: #111;
            font-family: "Arial Narrow", Arial, Helvetica, sans-serif;
            font-size: 26px;
            font-weight: bold;
            line-height: 28px;
            margin-top: 1px;
        }

        .company-tagline {
            font-size: 13px;
            font-weight: bold;
            line-height: 15px;
        }

        .company-address {
            font-family: "Arial Narrow", Arial, Helvetica, sans-serif;
            font-size: 15px;
            line-height: 17px;
            margin-top: 2px;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .company-contact {
            font-size: 13px;
            font-weight: bold;
            line-height: 16px;
            margin-top: 2px;
        }

        .header-left table,
        .header-right table {
            border: 0;
        }

        .top-detail-table {
            table-layout: fixed;
        }

        .top-detail-table td {
            font-size: 13px;
            min-height: 24px;
            padding: 4px 7px;
            vertical-align: middle;
        }

        .top-detail-table .tax-line {
            font-size: 13px;
            min-height: 28px;
        }

        .party-table {
            border-top: 2px solid #000 !important;
        }

        .party-table td {
            border-bottom: 0;
            border-top: 0;
        }

        /* ── Party cells: min-height + overflow protection + light bg ── */
        .party-cell {
            background-color: #fafafa;
            border-left: 1px solid #ccc;
            min-height: 132px;
            padding: 6px 8px;
            width: 50%;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        /* First party cell: no left border (it's the leftmost) */
        .party-cell:first-child {
            border-left: 0;
        }

        .party-lines {
            border-bottom: 1px solid #ddd;
            font-size: 12.5px;
            min-height: 24px;
            line-height: 22px;
            margin-top: 0;
        }

        .party-details {
            min-height: 59px;
            line-height: 16px;
            padding-top: 4px;
        }

        .side-cell {
            padding: 0;
            width: 39%;
        }

        .side-table td {
            min-height: 20px;
            padding: 2px 6px;
            vertical-align: middle;
        }

        .docket-no {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-align: left;
        }

        .owner-risk {
            font-size: 14px;
            font-weight: bold;
        }

        .tax-line {
            font-size: 13.5px;
            font-weight: bold;
            line-height: 16px;
            overflow-wrap: anywhere;
        }

        .goods {
            border-top: 2px solid #000;
            table-layout: fixed;
        }

        .goods td {
            font-size: 13px;
            min-height: 22px;
            line-height: 15px;
            padding: 4px 6px;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .goods .large {
            min-height: 36px;
        }

        .delivery-cell {
            font-size: 13px;
            line-height: 15px;
        }

        .eway-inline {
            border-top: 1px solid #444;
            margin: 15px -6px 0;
            padding: 4px 6px 0;
        }

        .left-panel {
            padding: 0;
            vertical-align: top;
            width: 100%;
        }

        .freight-panel {
            padding: 0;
            vertical-align: top;
            width: 100%;
        }

        /* ── Charges table: fixed layout, header bg, zebra, net-row highlight ── */
        .charges {
            table-layout: fixed;
        }

        .charges th {
            background-color: #e0e0e0;
            border: 1px solid #000;
            font-size: 12px;
            font-weight: bold;
            min-height: 28px;
            line-height: 14px;
            text-align: center;
            vertical-align: middle;
        }

        .charges td {
            font-size: 13px;
            min-height: 22px;
            line-height: 15px;
            padding: 4px 6px;
        }

        /* Zebra striping via class (dompdf :nth-child unreliable) */
        .charges .alt-row {
            background-color: #eef2f7;
        }

        /* Net Amount row: highlighted like grand total in office_invoice */
        .charges .net-row td {
            background-color: #d0d0d0;
            border-top: 2px solid #000;
            font-size: 15px;
            font-weight: bold;
            min-height: 28px;
            padding: 6px;
        }

        .mode {
            background-color: #ffffff;
            font-size: 15px;
            font-weight: bold;
            line-height: 16px;
            text-align: center;
            vertical-align: middle;
        }

        .mode-struck {
            color: #666;
            text-decoration: line-through;
        }

        /* display: inline (not inline-block) — dompdf renders this reliably */
        .mode-selected {
            border-bottom: 1px solid #111;
            display: inline;
            padding-bottom: 1px;
        }

        .copy-label-box {
            border: 1px solid #444;
            background-color: #f8f8f8;
            font-size: 12px;
            font-weight: normal;
            min-height: 60px;
            line-height: 16px;
            padding: 6px 8px;
            text-align: left;
        }

        .goods-fill {
            min-height: 18px;
        }

        .footer-left {
            table-layout: fixed;
        }

        .footer-left td {
            min-height: 88px;
        }

        /* ── Declaration: readable font, min-height, no overflow:hidden ── */
        .declaration {
            font-family: "Arial Narrow", Arial, Helvetica, sans-serif;
            font-size: 10.5px;
            line-height: 12px;
            min-height: 51px;
            padding: 4px 6px;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .agreement {
            border-top: 1px solid #444;
            font-size: 12.5px;
            font-weight: bold;
            min-height: 36px;
            line-height: 15px;
            padding-top: 7px;
            text-align: center;
        }

        .consignee-sign {
            min-height: 88px;
            line-height: 15px;
            padding: 4px 6px;
        }

        .gst-payable {
            border: 1px solid #444;
            background-color: #f5f5f5;
            font-size: 14px;
            font-weight: bold;
            min-height: 42px;
            line-height: 17px;
            padding: 8px 6px;
            text-align: center;
            vertical-align: middle;
        }

        .for-company {
            border-bottom: 0 !important;
            border-top: 2px solid #000 !important;
            font-size: 16px;
            font-weight: bold;
            min-height: 49px;
            line-height: 20px;
            padding-top: 12px;
            position: relative;
            text-align: center;
        }

        .signature-image {
            display: block;
            height: 34px;
            left: 0;
            margin: 0 auto;
            max-width: 180px;
            object-fit: contain;
            position: absolute;
            right: 0;
            top: 27px;
        }

        .company-separator {
            display: none;
        }

        /* ── Label styling: distinct from values for visual hierarchy ── */
        .label {
            color: #555;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        /* Party/section labels: slightly larger for key section headers */
        .party-cell .label {
            color: #333;
            font-size: 12px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* ── Value styling: distinct from labels — values in BOLD ── */
        .value {
            color: #111;
            font-size: 13px;
            font-weight: bold;
        }

        /* ── Page-break protection for key sections ── */
        .charges,
        .party-table,
        .goods {
            page-break-inside: avoid;
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
                    || $candidates->contains('CUSTOM_Invoice_'.$key)
                    || $candidates->contains('CUSTOM_ITEM_'.$key)
                    || $candidates->contains('CUSTOM_Item_'.$key)
                    || $candidates->contains('CUSTOM_CUSTOMER_'.$key)
                    || $candidates->contains('CUSTOM_Customer_'.$key)
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
                return $invoice->$camelKey;
            }
        }

        // Fallback to custom fields relationship
        return $fieldValue($invoice->fields, $keys);
    };

    $addressLines = function ($address) {
        if (! $address) {
            return [];
        }

        $cityState = collect([$address->city, $address->state])->filter()->implode(', ');
        $cityStateZip = collect([$cityState, $address->zip])->filter()->implode(' ');

        return collect([
            $address->name,
            $address->address_street_1,
            $address->address_street_2,
            $cityStateZip,
        ])->filter()->values()->all();
    };

    $partyDetails = function ($customer, $fallback = '') use ($addressLines) {
        if (! $customer) {
            return $fallback;
        }

        $name = $customer->name ?: $customer->display_name;
        $address = $customer->billingAddress ?: $customer->shippingAddress;
        $lines = collect([$name])
            ->merge($addressLines($address))
            ->filter()
            ->unique()
            ->take(4)
            ->values();

        return $lines->isNotEmpty() ? $lines->implode("\n") : $fallback;
    };

    $fitPartyText = function ($value) {
        return collect(preg_split('/\R/', (string) $value))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->take(4)
            ->implode("\n");
    };

    $item = $invoice->items->first();
    $itemField = function ($keys) use ($item, $fieldValue) {
        return $item ? $fieldValue($item->fields, $keys) : '';
    };

    $moneyText = function ($paise) use ($invoice) {
        if (! $paise) {
            return '';
        }

        $currency = $invoice->customer ? $invoice->customer->currency : null;

        return format_money_pdf((int) round($paise), $currency);
    };

    $companyName = $invoice->company?->name ?: '';
    $companyInitials = collect(preg_split('/\s+/', trim($companyName)))
        ->filter()
        ->map(fn ($word) => mb_substr($word, 0, 1))
        ->take(2)
        ->implode('');
    $companyTagline = $invoice->company?->tagline ?: '';
    $companyTopHeading = $invoice->company?->top_heading ?: 'Subject to Vapi Jurisdiction';
    $companyAddress = trim(strip_tags($company_address)) ? $company_address : '';
    $companyPhone = $invoice->company?->address?->phone;
    // Use address email first (configured in Company Info settings), fall back to notification_email setting
    $companyEmail = $invoice->company?->address?->email ?: ($invoice->company?->notification_email ?: \App\Models\CompanySetting::getSetting('notification_email', $invoice->company_id));
    $mobile = $companyPhone ?: ($invoiceField(['mobile', 'phone']) ?: '');
    $email = $invoiceField(['email']) ?: ($companyEmail ?: '');
    $displayCompanyAddress = preg_replace('/^\s*<h[1-6][^>]*>.*?<\/h[1-6]>\s*/is', '', (string) $companyAddress);
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
    $panNo = $invoiceField(['pan_no', 'pan']) ?: ($invoice->company?->pan_no ?: '');
    $companyGstin = $invoiceField(['gstin', 'gst_no']) ?: ($invoice->company?->gstin ?: '');
    $companyEnrollmentNo = $invoice->company?->enrollment_no ?: $invoiceField(['enrollment_no', 'enrollment']);
    $companyTaxIdentityLabel = $companyEnrollmentNo ? 'Enrollment No' : 'GSTIN';
    $companyTaxIdentityValue = $companyEnrollmentNo ?: $companyGstin;

    $basicFreight = $invoiceField(['basic_freight']);
    $localCollection = $invoiceField(['local_collection']);
    $doorDelivery = $invoiceField(['door_delivery']);
    $hamali = $invoiceField(['hamali']);
    $docketCharge = $invoiceField(['docket_charge']) ?: 100;
    $otherCharge = $invoiceField(['other_charge']);
    $fov = $invoiceField(['fov']);
    $netAmount = (
        $numericField($basicFreight)
        + $numericField($localCollection)
        + $numericField($doorDelivery)
        + $numericField($hamali)
        + $numericField($docketCharge)
        + $numericField($otherCharge)
        + $numericField($fov)
    ) * 100;

    $modeOfPayment = $invoiceField(['mode_of_payment']) ?: 'TO PAY';
    $selectedMode = $normalize($modeOfPayment);
    if ($selectedMode === 'TO_BE_BILLED') {
        $selectedMode = 'TO_BE_BILLED_AT';
    }
    $modeLabel = function (string $label) use ($normalize, $selectedMode) {
        if ($normalize($label) === $selectedMode) {
            return '<span class="mode-selected">'.e($label).'</span>';
        }

        return '<span class="mode-struck">'.e($label).'</span>';
    };
    $formatAddress = function ($customer) {
        if (!$customer) return '';
        $billing = $customer->billingAddress;
        if (!$billing) return '';
        $lines = [
            $billing->address_street_1,
            $billing->address_street_2,
            implode(', ', array_filter([$billing->city, $billing->state])) . ($billing->zip ? ' ' . $billing->zip : '')
        ];
        return implode("\n", array_filter(array_map('trim', $lines)));
    };

    $parseParty = function ($partyText) {
        $lines = collect(explode("\n", (string) $partyText))
            ->map(fn($line) => trim($line))
            ->filter()
            ->values();

        $name = $lines->first() ?: '';
        $addressLines = $lines->slice(1)->values()->all();

        return [
            'name' => $name,
            'address' => implode("\n", $addressLines),
        ];
    };

    $gstPayableBy = $invoiceField(['gst_tax_payable_by']) ?: 'Consignor / Consignee';

    $consignorName = $invoice->customer ? $invoice->customer->name : $parseParty($invoiceField(['consignor']))['name'];
    $consignorAddress = $invoice->customer ? $formatAddress($invoice->customer) : $parseParty($invoiceField(['consignor']))['address'];
    $consignorPhone = ($invoice->customer && $invoice->customer->phone) ? $invoice->customer->phone : $invoiceField(['consignor_phone_no']);
    $consignorGstin = ($invoice->customer && $invoice->customer->tax_id) ? $invoice->customer->tax_id : $invoiceField(['consignor_gst_no']);

    $consigneeName = $invoice->consigneeCustomer ? $invoice->consigneeCustomer->name : $parseParty($invoiceField(['consignee']))['name'];
    $consigneeAddress = $invoice->consigneeCustomer ? $formatAddress($invoice->consigneeCustomer) : $parseParty($invoiceField(['consignee']))['address'];
    $consigneePhone = ($invoice->consigneeCustomer && $invoice->consigneeCustomer->phone) ? $invoice->consigneeCustomer->phone : $invoiceField(['consignee_phone_no']);
    $consigneeGstin = ($invoice->consigneeCustomer && $invoice->consigneeCustomer->tax_id) ? $invoice->consigneeCustomer->tax_id : $invoiceField(['consignee_gst_no']);

    $docketNumber = $invoice->invoice_number;
    $descriptionOfGoods = trim((string) $invoiceField(['description_of_goods']));
    $noOfArticles = trim((string) $invoiceField(['no_of_articles']));

    if (preg_match('/^LR Receipt\s+\d+$/i', $descriptionOfGoods)) {
        $descriptionOfGoods = '';
    }

    if ($noOfArticles === '1' && $descriptionOfGoods === '') {
        $noOfArticles = '';
    }
    $signaturePath = base_path('resources/static/img/PDF/authorized_signature.jpeg');

    // Auto-fit font sizing: shrinks font size for text that would overflow
    // its container. Each block shrinks independently so other blocks are
    // not disturbed. Uses the same pattern as lorry_receipt.blade.php.
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
    // Landscape A4: 297mm ≈ 1123px. Party cell is 50% of party-table ≈ 540px.
    // Consignor/Consignee name: after "Consignor" label + padding ≈ 420px usable.
    $consignorNameStyle = $getFontForWidth($consignorName, 420, 11.5, 6.5);
    $consigneeNameStyle = $getFontForWidth($consigneeName, 420, 11.5, 6.5);
    // Company name: in brand-row company-cell (80% of 61% of page ≈ 548px, minus padding ≈ 500px usable).
    $companyNameStyle = $getFontForWidth($companyName, 500, 26, 10);
    // GSTIN fields: after "GST No.:" label ≈ 380px usable.
    $consignorGstinStyle = $getFontForWidth($consignorGstin, 380, 11, 6.5);
    $consigneeGstinStyle = $getFontForWidth($consigneeGstin, 380, 11, 6.5);
    // Description of goods: in goods table, full width ≈ 700px usable.
    $descriptionOfGoodsStyle = $getFontForWidth($descriptionOfGoods, 700, 11.8, 6.5);

    // Party address: party cell is ~540px, minus padding ≈ 500px usable.
    $consignorAddrStyle = $getFontForWidth($consignorAddress, 500, 12, 7);
    $consigneeAddrStyle = $getFontForWidth($consigneeAddress, 500, 12, 7);

    // Top-detail-table fields: right column ≈ 300px usable after label.
    $fromLocation = $invoiceField(['from']);
    $toLocation = $invoiceField(['to']);
    $truckNo = $invoiceField(['truck_no']);
    $fromStyle = $getFontForWidth($fromLocation, 250, 12, 7);
    $toStyle = $getFontForWidth($toLocation, 250, 12, 7);
    $truckNoStyle = $getFontForWidth($truckNo, 400, 12, 7);

    // Delivery At: in goods table left column ≈ 500px usable.
    $deliveryAt = $invoiceField(['delivery_at']);
    $deliveryAtStyle = $getFontForWidth($deliveryAt, 480, 12, 7);

    // PAN No and tax identity: in tax-line, full width ≈ 400px usable.
    $panNoStyle = $getFontForWidth($panNo, 200, 13.5, 7);
    $taxIdentityStyle = $getFontForWidth($companyTaxIdentityValue, 200, 13.5, 7);

    // Goods table secondary fields
    $hsnCode = $invoiceField(['hsn_code']);
    $hsnCodeStyle = $getFontForWidth($hsnCode, 480, 12, 7);
    $invoiceNo = $invoiceField(['invoice_no']);
    $invoiceNoStyle = $getFontForWidth($invoiceNo, 200, 12, 7);
    $goodsValue = $invoiceField(['goods_value']);
    $goodsValueStyle = $getFontForWidth($goodsValue, 200, 12, 7);
    $ewayBillNo = $invoiceField(['e_way_bill_no']);
    $ewayBillStyle = $getFontForWidth($ewayBillNo, 480, 12, 7);

    // Charges table: description column ≈ 180px, amount column ≈ 140px.
    $basicFreightStyle = $getFontForWidth($basicFreight, 140, 12, 7);
    $localCollectionStyle = $getFontForWidth($localCollection, 140, 12, 7);
    $doorDeliveryStyle = $getFontForWidth($doorDelivery, 140, 12, 7);
    $hamaliStyle = $getFontForWidth($hamali, 140, 12, 7);
    $otherChargeStyle = $getFontForWidth($otherCharge, 140, 12, 7);
    $fovStyle = $getFontForWidth($fov, 140, 12, 7);

    // GST payable by: right column ≈ 300px usable.
    $gstPayableByStyle = $getFontForWidth($gstPayableBy, 280, 13, 8);

    // Company address: use CSS font-size: 18px directly — no auto-fit override.
    $companyAddrStyle = '';

    // Docket number: right column ≈ 250px usable.
    $docketNoStyle = $getFontForWidth($docketNumber, 220, 12, 7);

    $consignorData = [
        'name' => $consignorName,
        'address' => $consignorAddress,
    ];
    $consigneeData = [
        'name' => $consigneeName,
        'address' => $consigneeAddress,
    ];

    $isMulti = request()->has('multi') || request()->query('copy') === 'multi';

    if ($isMulti) {
        $renderCopies = [
            [
                'key' => 'consignee',
                'label' => 'CONSIGNEE COPY',
                'bg' => '#ffffff',
            ],
            [
                'key' => 'driver',
                'label' => 'DRIVER COPY',
                'bg' => '#eafaf1',
            ],
            [
                'key' => 'consignor',
                'label' => 'CONSIGNOR COPY',
                'bg' => '#fdf2f8',
            ],
            [
                'key' => 'file',
                'label' => 'FILE COPY',
                'bg' => '#fefde7',
            ],
        ];
    } else {
        $currentCopy = request()->query('copy');
        $bg = '#ffffff';
        if ($currentCopy === 'driver') {
            $bg = '#eafaf1';
        } elseif ($currentCopy === 'consignor') {
            $bg = '#fdf2f8';
        } elseif ($currentCopy === 'ho') {
            $bg = '#fefde7';
        }

        $copyLabelMap = [
            'consignee' => 'CONSIGNEE COPY',
            'driver' => 'DRIVER COPY',
            'consignor' => 'CONSIGNOR COPY',
            'ho' => 'H. O COPY',
            'file' => 'FILE COPY',
        ];
        $copyLabel = $currentCopy ? ($copyLabelMap[$currentCopy] ?? '') : '';

        $renderCopies = [
            [
                'key' => $currentCopy ?: 'default',
                'label' => $copyLabel ?: '',
                'bg' => $bg,
            ]
        ];
    }
@endphp

@foreach ($renderCopies as $index => $copy)
    <div style="background-color: {{ $copy['bg'] }}; @if(!$loop->last) page-break-after: always; @endif">
    <div class="jurisdiction-top">{{ $companyTopHeading }}</div>
    <div class="wrapper" style="background-color: {{ $copy['bg'] }}; @if(!$loop->last) margin-bottom: 20px; @endif">
        <table>
            <tr>
                <td class="header-left">
                    <table class="brand-row">
                        <tr>
                            <td class="logo-cell">
                                @if ($logo)
                                    <img class="company-logo" src="{{ \App\Support\Pdf\ImageUtils::toBase64Src($logo) }}" alt="Company Logo">
                                @else
                                    <div class="brand-mark">
                                        {{ $companyInitials }}
                                    </div>
                                @endif
                            </td>
                            <td class="company-cell">
                                <div class="company-name" style="{{ $companyNameStyle }}">{{ $companyName }}</div>
                                <div class="company-tagline">{{ $companyTagline }}</div>
                                <div class="company-address" style="{{ $companyAddrStyle }}">{!! $displayCompanyAddress !!}</div>
                                <div class="company-contact">Mob. {{ $mobile }} &nbsp;|&nbsp; E-mail : {{ $email }}</div>
                            </td>
                        </tr>
                    </table>

                    <table class="party-table">
                        <tr>
                            <td class="party-cell">
                                <div style="margin-bottom: 4px;"><span class="label">Consignor</span></div>
                                <div style="font-size: 14px; font-weight: bold; line-height: 18px; {{ $consignorNameStyle }}">{{ $consignorData['name'] }}</div>
                                <div class="party-lines party-details" style="{{ $consignorAddrStyle }}">{!! nl2br(e($consignorData['address'])) !!}</div>
                                <div class="party-lines"><span class="label">Phone No.:</span> <span class="value">{{ $consignorPhone }}</span></div>
                                <div class="party-lines" style="{{ $consignorGstinStyle }}"><span class="label">GST No.:</span> <span class="value">{{ $consignorGstin }}</span></div>
                            </td>
                            <td class="party-cell">
                                <div style="margin-bottom: 4px;"><span class="label">Consignee</span></div>
                                <div style="font-size: 14px; font-weight: bold; line-height: 18px; {{ $consigneeNameStyle }}">{{ $consigneeData['name'] }}</div>
                                <div class="party-lines party-details" style="{{ $consigneeAddrStyle }}">{!! nl2br(e($consigneeData['address'])) !!}</div>
                                <div class="party-lines"><span class="label">Phone No.:</span> <span class="value">{{ $consigneePhone }}</span></div>
                                <div class="party-lines" style="{{ $consigneeGstinStyle }}"><span class="label">GST No.:</span> <span class="value">{{ $consigneeGstin }}</span></div>
                            </td>
                        </tr>
                    </table>

                    <table class="goods">
                        <tr>
                            <td width="50%" class="large"><span class="label">Description of Goods</span><br><span class="value" style="{{ $descriptionOfGoodsStyle }}">{{ $descriptionOfGoods }}</span></td>
                            <td width="24%"><span class="label">No. of Articles</span><br><span class="value">{{ $noOfArticles }}</span></td>
                            <td><span class="label">Packing</span><br><span class="value">{{ $invoiceField(['packing']) }}</span></td>
                        </tr>
                        <tr>
                            <td><span class="label">HSN CODE</span><br><span class="value" style="{{ $hsnCodeStyle }}">{{ $invoiceField(['hsn_code']) }}</span></td>
                            <td><span class="label">Actual Weight</span></td>
                            <td><span class="value">{{ $invoiceField(['actual_weight']) }}</span></td>
                        </tr>
                        <tr>
                            <td rowspan="3" class="delivery-cell">
                                <span class="label">Delivery At.:</span><br>
                                <span class="value" style="{{ $deliveryAtStyle }}">{{ $invoiceField(['delivery_at']) }}</span>
                                <div class="eway-inline">
                                    <span class="label">E-way Bill No.:</span><br>
                                    <span class="value" style="{{ $ewayBillStyle }}">{{ $invoiceField(['e_way_bill_no']) }}</span>
                                </div>
                            </td>
                            <td><span class="label">Charged Weight</span></td>
                            <td><span class="value">{{ $invoiceField(['charged_weight']) }}</span></td>
                        </tr>
                        <tr>
                            <td><span class="label">Goods Value</span></td>
                            <td><span class="value" style="{{ $goodsValueStyle }}">{{ $invoiceField(['goods_value']) }}</span></td>
                        </tr>
                        <tr>
                            <td class="goods-fill"><span class="label">POD Required</span></td>
                            <td><span class="value">{{ $invoiceField(['pod_required']) }}</span></td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                        </tr>
                    </table>

                    <table class="footer-left">
                        <tr>
                            <td width="50%" style="padding: 0;">
                                <div class="declaration">
                                    <span class="label">DECLARATION :</span> We Have Not Taken Gst Credit As Per The Provisions
                                    Of Convat Credit Rule 2004 Of Only Paid On Inputs Or Capital Goods
                                    Used For Providing Taxable's Service To You And Have Also Availed
                                    The Benefits Of Notification No. 11 & 13/2017 Dated 28th June 2017
                                </div>
                                <div class="agreement">It is taken in to consideration that agrees with<br>all the terms and condition overleaf</div>
                            </td>
                            <td width="50%" class="consignee-sign">
                                <span class="label">Rubber Stamp and Signature of Consignee</span><br><br><br><br>
                                <span class="label">Phone / Mobile</span><br>
                                &nbsp;
                            </td>
                        </tr>
                    </table>
                </td>

                <td class="header-right">
                    <div class="copy-label-box">
                        @php
                            $hasActiveCopy = in_array($copy['key'], ['consignee', 'driver', 'consignor', 'ho', 'file'], true);
                            $isCopy = fn($name) => $copy['key'] === $name;
                            $styleLine = function($name) use ($hasActiveCopy, $isCopy) {
                                if (! $hasActiveCopy) {
                                    return '';
                                }
                                return $isCopy($name) ? 'font-weight: bold; text-decoration: underline;' : 'color: #777; font-size: 11px;';
                            };
                        @endphp
                        <span style="{{ $styleLine('consignee') }}">ORIGINAL WHITE&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: CONSIGNEE COPY</span><br>
                        <span style="{{ $styleLine('driver') }}">DUPLICATE GREEN&nbsp;&nbsp;&nbsp;: DRIVER COPY</span><br>
                        <span style="{{ $styleLine('consignor') }}">TRIPLICATE PINK&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: CONSIGNOR COPY</span><br>
                        <span style="{{ $styleLine('ho') }}">YELLOW&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: H. O COPY</span><br>
                        <span style="{{ $styleLine('file') }}">DUPLICATE WHITE&nbsp;&nbsp;&nbsp;: FILE COPY</span>
                    </div>
                    <table class="top-detail-table">
                        <tr>
                            <td width="36%"><span class="label">Date :</span> <span class="value">{{ $invoice->formattedInvoiceDate }}</span></td>
                            <td><span class="label">Docket No.:</span> <span class="docket-no" style="{{ $docketNoStyle }}">{{ $docketNumber }}</span></td>
                        </tr>
                        <tr>
                            <td width="36%"><span class="label">Time :</span> <span class="value">{{ $invoiceField(['time']) }}</span></td>
                            <td><span class="label">From :</span> <span class="value" style="{{ $fromStyle }}">{{ $invoiceField(['from']) }}</span></td>
                        </tr>
                        <tr>
                            <td width="36%" class="owner-risk">OWNER'S RISK</td>
                            <td><span class="label">To :</span> <span class="value" style="{{ $toStyle }}">{{ $invoiceField(['to']) }}</span></td>
                        </tr>
                        <tr><td colspan="2"><span class="label">Truck No.:</span> <span class="value" style="{{ $truckNoStyle }}">{{ $invoiceField(['truck_no']) }}</span></td></tr>
                        <tr><td colspan="2" class="tax-line"><span class="label">PAN No.:</span> <span class="value" style="{{ $panNoStyle }}">{{ $panNo }}</span><br><span class="label">{{ $companyTaxIdentityLabel }} :</span> <span class="value" style="{{ $taxIdentityStyle }}">{{ $companyTaxIdentityValue }}</span></td></tr>
                    </table>

                    <table class="charges">
                        <tr>
                            <th width="42%">Description of<br>Freight</th>
                            <th width="34%">To Pay/Paid Rs.</th>
                            <th>Mode of<br>Payment</th>
                        </tr>
                        <tr class="alt-row">
                            <td><span class="label">Basic Freight</span></td>
                            <td class="text-right" style="{{ $basicFreightStyle }}">{{ $basicFreight }}</td>
                            <td class="mode">{!! $modeLabel('TO PAY') !!}</td>
                        </tr>
                        <tr>
                            <td><span class="label">Local Collection</span></td>
                            <td class="text-right" style="{{ $localCollectionStyle }}">{{ $localCollection }}</td>
                            <td class="mode"></td>
                        </tr>
                        <tr class="alt-row">
                            <td><span class="label">Door Delivery</span></td>
                            <td class="text-right" style="{{ $doorDeliveryStyle }}">{{ $doorDelivery }}</td>
                            <td rowspan="3" class="mode">{!! $modeLabel('PAID') !!}</td>
                        </tr>
                        <tr>
                            <td><span class="label">Hamali</span></td>
                            <td class="text-right" style="{{ $hamaliStyle }}">{{ $hamali }}</td>
                        </tr>
                        <tr class="alt-row">
                            <td><span class="label">Docket Charge</span></td>
                            <td class="text-right">{{ $docketCharge ? $docketCharge.'/-' : '' }}</td>
                        </tr>
                        <tr>
                            <td><span class="label">Other Charge</span></td>
                            <td class="text-right" style="{{ $otherChargeStyle }}">{{ $otherCharge }}</td>
                            <td rowspan="4" class="mode">{!! $modeLabel('TO BE BILLED AT') !!}</td>
                        </tr>
                        <tr class="alt-row">
                            <td><span class="label">F.O.V.</span></td>
                            <td class="text-right" style="{{ $fovStyle }}">{{ $fov }}</td>
                        </tr>
                        <tr>
                            <td><span class="label">Sub Total</span></td>
                            <td class="text-right">{!! $moneyText($netAmount) !!}</td>
                        </tr>
                        <tr class="net-row">
                            <td><span class="label">Net Amount</span></td>
                            <td class="text-right">{!! $moneyText($netAmount) !!}</td>
                        </tr>
                    </table>

                    <table>
                        <tr>
                            <td class="gst-payable" style="{{ $gstPayableByStyle }}">
                                GST Tax Payable By<br>{{ $gstPayableBy }}
                            </td>
                        </tr>
                        <tr>
                            <td class="for-company">
                                <div class="company-separator"></div>For {{ $companyName }}
                                @if (file_exists($signaturePath))
                                    <img class="signature-image" src="{{ \App\Support\Pdf\ImageUtils::toBase64Src($signaturePath) }}" alt="Signature">
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
    </div>
@endforeach
</body>

</html>
