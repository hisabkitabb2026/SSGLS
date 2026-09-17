<!DOCTYPE html>
<html lang="en">

<head>
    <title>Sales Report by Customer Name</title>
@include("app.pdf.partials.fonts")

    <style type="text/css">
        body {
        }

        table {
            border-collapse: collapse;
        }

        .sub-container {
            padding: 0px 20px;
        }

        .report-header {
            width: 100%;
        }

        .heading-text {
            font-weight: bold;
            font-size: 24px;
            color: #5851D8;
            width: 100%;
            text-align: left;
            padding: 0px;
            margin: 0px;
        }

        .heading-date-range {
            font-weight: normal;
            font-size: 15px;
            color: #A5ACC1;
            width: 100%;
            text-align: right;
            padding: 0px;
            margin: 0px;
        }

        .sub-heading-text {
            font-weight: bold;
            font-size: 16px;
            line-height: 21px;
            color: #595959;
            padding: 0px;
            margin: 0px;
            margin-top: 30px;
        }

        .search-info {
            font-size: 12px;
            color: #A5ACC1;
            margin-top: 4px;
        }

        .sales-customer-name {
            margin-top: 20px;
            padding-left: 3px;
            font-size: 16px;
            line-height: 21px;
            color: #040405;
        }

        .sales-table-container {
            padding-left: 10px;
        }

        .sales-table {
            width: 100%;
            padding-bottom: 10px;
        }

        .sales-information-text {
            padding: 0px;
            margin: 0px;
            font-size: 14px;
            line-height: 21px;
            color: #595959;
        }

        .sales-amount {
            padding: 0px;
            margin: 0px;
            font-size: 14px;
            line-height: 21px;
            text-align: right;
            color: #595959;
        }

        .sales-total-indicator-table {
            border-top: 1px solid #EAF1FB;
            width: 100%;
        }

        .sales-total-cell {
            padding-top: 10px;
        }

        .sales-total-amount {
            padding-top: 10px;
            padding-right: 30px;
            padding: 0px;
            margin: 0px;
            text-align: right;
            font-weight: bold;
            font-size: 16px;
            line-height: 21px;
            text-align: right;
            color: #040405;
        }

        .report-footer {
            width: 100%;
            margin-top: 40px;
            padding: 15px 20px;
            background: #F9FBFF;
            box-sizing: border-box;
        }

        .report-footer-label {
            padding: 0px;
            margin: 0px;
            text-align: left;
            font-weight: bold;
            font-size: 16px;
            line-height: 21px;
            color: #595959;
        }

        .report-footer-value {
            padding: 0px;
            margin: 0px;
            text-align: right;
            font-weight: bold;
            font-size: 20px;
            line-height: 21px;
            color: #5851D8;
        }

        .text-center {
            text-align: center;
        }

        .no-records {
            padding: 15px;
            text-align: center;
            font-size: 11px;
            color: #a5acc1;
        }
    </style>

</head>

<body>
    <div class="sub-container">
        <table class="report-header">
            <tr>
                <td>
                    <p class="heading-text">{{ $company->name }}</p>
                </td>
                <td>
                    <p class="heading-date-range">{{ $from_date }} - {{ $to_date }}</p>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <p class="sub-heading-text text-center">Sales Report by Customer Name</p>
                    @if(!empty($searchedName))
                    <p class="search-info text-center">Showing results for: "{{ $searchedName }}"</p>
                    @endif
                </td>
            </tr>
        </table>

        @if($customers->count() > 0 || $consigneeCustomers->count() > 0)
            {{-- Standard customers (customer_id match) --}}
            @foreach ($customers as $customer)
            <p class="sales-customer-name">{{ $customer->name }}</p>
            <div class="sales-table-container">
                <table class="sales-table">
                    @foreach ($customer->invoices as $invoice)
                    <tr>
                        <td>
                            <p class="sales-information-text">
                                {{ $invoice->formattedInvoiceDate }} ({{ $invoice->invoice_number }})
                            </p>
                        </td>
                        <td>
                            <p class="sales-amount">
                                @php
                                    $amount = in_array($invoice->template_name, ['lr_receipt', 'lorry_receipt', 'office_invoice'])
                                        ? ($invoice->amount_credit ?? 0)
                                        : $invoice->base_total;
                                @endphp
                                {!! format_money_pdf($amount, $currency) !!}
                            </p>
                        </td>
                    </tr>
                    @endforeach
                </table>
            </div>
            <table class="sales-total-indicator-table">
                <tr>
                    <td class="sales-total-cell">
                        <p class="sales-total-amount">
                            {!! format_money_pdf($customer->totalAmount, $currency) !!}
                        </p>
                    </td>
                </tr>
            </table>
            @endforeach

            {{-- Consignee customers (consignee_customer_id match — transport receipts) --}}
            @foreach ($consigneeCustomers as $consigneeCustomer)
            <p class="sales-customer-name">{{ $consigneeCustomer->name }} (Consignee)</p>
            <div class="sales-table-container">
                <table class="sales-table">
                    @foreach ($consigneeCustomer->invoices as $invoice)
                    <tr>
                        <td>
                            <p class="sales-information-text">
                                {{ $invoice->formattedInvoiceDate }} ({{ $invoice->invoice_number }})
                            </p>
                        </td>
                        <td>
                            <p class="sales-amount">
                                @php
                                    $amount = in_array($invoice->template_name, ['lr_receipt', 'lorry_receipt', 'office_invoice'])
                                        ? ($invoice->amount_credit ?? 0)
                                        : $invoice->base_total;
                                @endphp
                                {!! format_money_pdf($amount, $currency) !!}
                            </p>
                        </td>
                    </tr>
                    @endforeach
                </table>
            </div>
            <table class="sales-total-indicator-table">
                <tr>
                    <td class="sales-total-cell">
                        <p class="sales-total-amount">
                            {!! format_money_pdf($consigneeCustomer->totalAmount, $currency) !!}
                        </p>
                    </td>
                </tr>
            </table>
            @endforeach
        @else
            <table style="width: 100%; border-collapse: collapse; margin-top: 20px; margin-bottom: 20px;">
                <tr>
                    <td class="no-records">
                        No records found for "{{ $searchedName }}"
                    </td>
                </tr>
            </table>
        @endif
    </div>

    <table class="report-footer">
        <tr>
            <td>
                <p class="report-footer-label">Total Sales</p>
            </td>
            <td>
                <p class="report-footer-value">
                    {!! format_money_pdf($totalAmount, $currency) !!}
                </p>
            </td>
        </tr>
    </table>
</body>

</html>
