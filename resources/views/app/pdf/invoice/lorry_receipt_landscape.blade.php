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
$company = $invoice->company ?? ($lorryReceipt->company ?? null);
$companyName = $company?->name ?: '';
$companyTagline = $company?->tagline ?: '';
$companyPhone = $company?->address?->phone ?: '';
$companyId = $invoice->company_id ?? ($company->id ?? null);
$companyEmail = $companyId ? \App\Models\CompanySetting::getSetting('notification_email', $companyId) : '';
$logo = $logo ?? ($company->logo_path ?? null);
@endphp + + +



 + +


  @if($logo)
      <img src="{{ $logo }}" style="max-height: 40pt; margin-bottom: 8pt;">
  @endif
  <div class="title">{{ $companyName }}</div>
  <div style="font-size: 9pt; color: #666;">{{ $companyTagline }}</div>




  LORRY RECEIPT




  <div class="field">
      <div class="field-label">Receipt No.</div>
      <div class="field-value">{{ $invoice->invoice_number ?? '' }}</div>
  </div>
  <div class="field">
      <div class="field-label">Date</div>
      <div class="field-value">{{ $invoice->invoice_date ? $invoice->invoice_date->format('d/m/Y') : '' }}</div>
  </div>
  <div class="field">
      <div class="field-label">Mode of Payment</div>
      <div class="field-value">{{ $v(['Mode of Payment']) }}</div>
  </div>



SHIPMENT DETAILS

  <div class="field">
      <div class="field-label">From</div>
      <div class="field-value">{{ $v(['From Code', 'From Name']) }}</div>
  </div>
  <div class="field">
      <div class="field-label">To</div>
      <div class="field-value">{{ $v(['To Code', 'To Name']) }}</div>
  </div>
  <div class="field">
      <div class="field-label">Description of Goods</div>
      <div class="field-value">{{ $v(['Description of Goods']) }}</div>
  </div>



VEHICLE DETAILS

  <div class="field">
      <div class="field-label">Vehicle Number</div>
      <div class="field-value">{{ $v(['Vehicle Number']) }}</div>
  </div>
  <div class="field">
      <div class="field-label">Weight (Actual / Charged)</div>
      <div class="field-value">{{ $v(['Actual Weight']) }} / {{ $v(['Charged Weight']) }} kg</div>
  </div>
  <div class="field">
      <div class="field-label">No. of Articles</div>
      <div class="field-value">{{ $v(['No of Articles']) }}</div>
  </div>



CHARGES SUMMARY

  <tr class="total-row">
      <td style="width: 40%;">Description</td>
      <td style="width: 30%; text-align: right;">Amount (Rs.)</td>
  </tr>
  <tr>
      <td>Lorry Hire</td>
      <td class="amount-right">{{ $lorryHireAmount }}</td>
  </tr>
  <tr>
      <td>Other Charges</td>
      <td class="amount-right">{{ $otherChargesAmount }}</td>
  </tr>
  <tr>
      <td>Gross Hire</td>
      <td class="amount-right"><strong>{{ $grossHireRupees }}</strong></td>
  </tr>
  <tr>
      <td>Less: Advance</td>
      <td class="amount-right">({{ $advanceAmount }})</td>
  </tr>
  <tr class="total-row">
      <td>Balance Payable</td>
      <td class="amount-right"><strong>{{ $balanceAmount }}</strong></td>
  </tr>




  <div>
      <div class="signature-box"></div>
      <div style="font-size: 8pt; margin-top: 5pt;">Prepared By</div>
  </div>
  <div>
      <div class="signature-box"></div>
      <div style="font-size: 8pt; margin-top: 5pt;">Verified By</div>
  </div>
  <div>
      <div class="signature-box"></div>
      <div style="font-size: 8pt; margin-top: 5pt;">Authorized By</div>
  </div>




  <div>{{ $companyEmail }} | {{ $companyPhone }}</div>
  <div style="margin-top: 5pt; font-size: 7pt;">Generated on {{ now()->format('d/m/Y H:i') }}</div>

 + +