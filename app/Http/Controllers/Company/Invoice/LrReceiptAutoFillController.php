<?php

namespace App\Http\Controllers\Company\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LrReceiptAutoFillController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        $apiKey = config('services.gemini.key');
        if (! $apiKey) {
            return response()->json([
                'error' => 'Gemini API Key is not configured. Please add GEMINI_API_KEY to your .env file.',
            ], 422);
        }

        $file = $request->file('file');

        // Extract extension to map mime type manually to bypass the missing fileinfo extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (empty($extension)) {
            $extension = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
        }
        $extension = strtolower($extension);

        $mimeMap = [
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
        ];

        if (! isset($mimeMap[$extension])) {
            return response()->json([
                'error' => 'Unsupported file type. Please upload a PDF or image (JPEG/PNG).',
            ], 422);
        }

        $mimeType = $mimeMap[$extension];
        $base64Data = base64_encode(file_get_contents($file->getRealPath()));

        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'consignor_name' => ['type' => 'STRING', 'description' => 'Name of the consignor (sender) company or person'],
                'consignor_gstin' => ['type' => 'STRING', 'description' => 'GSTIN of the consignor, e.g. 24ABCDE1234F1Z5'],
                'consignor_phone' => ['type' => 'STRING', 'description' => 'Phone number of the consignor'],
                'consignor_address' => ['type' => 'STRING', 'description' => 'Full address of the consignor'],
                'consignor_city' => ['type' => 'STRING', 'description' => 'City of the consignor, e.g. Vapi, Umbergaon'],
                'consignee_name' => ['type' => 'STRING', 'description' => 'Name of the consignee (receiver) company or person'],
                'consignee_gstin' => ['type' => 'STRING', 'description' => 'GSTIN of the consignee, e.g. 24ABCDE1234F1Z5'],
                'consignee_phone' => ['type' => 'STRING', 'description' => 'Phone number of the consignee'],
                'consignee_address' => ['type' => 'STRING', 'description' => 'Full address of the consignee'],
                'consignee_city' => ['type' => 'STRING', 'description' => 'City of the consignee, e.g. Surat, Mumbai'],
                'from' => ['type' => 'STRING', 'description' => 'Origin/dispatch city or place'],
                'to' => ['type' => 'STRING', 'description' => 'Destination city or place'],
                'truck_no' => ['type' => 'STRING', 'description' => 'Truck / Vehicle registration number'],
                'mode_of_payment' => ['type' => 'STRING', 'enum' => ['TO PAY', 'PAID', 'TO BE BILLED AT'], 'description' => 'Mode of payment for freight charges'],
                'gst_payable_by' => ['type' => 'STRING', 'enum' => ['Consignor', 'Consignee'], 'description' => 'Party responsible for GST tax payment'],
                'docket_no' => ['type' => 'STRING', 'description' => 'Docket number or Lorry Receipt (LR) number'],
                'date' => ['type' => 'STRING', 'description' => 'Date of the invoice/E-way bill in YYYY-MM-DD format'],
                'due_date' => ['type' => 'STRING', 'description' => 'Due date in YYYY-MM-DD format (if any)'],
                'description_of_goods' => ['type' => 'STRING', 'description' => 'Description of the cargo/goods'],
                'hsn_code' => ['type' => 'STRING', 'description' => 'HSN code of the goods'],
                'delivery_at' => ['type' => 'STRING', 'description' => 'Delivery location or address'],
                'eway_bill_no' => ['type' => 'STRING', 'description' => 'E-way bill number'],
                'no_of_articles' => ['type' => 'INTEGER', 'description' => 'Number of articles / packages (quantity)'],
                'packing' => ['type' => 'STRING', 'description' => 'Type of packing (e.g. box, bag, cartoon, rolls)'],
                'actual_weight' => ['type' => 'STRING', 'description' => 'Actual weight (e.g. 5000 kgs, 5 ton)'],
                'charged_weight' => ['type' => 'STRING', 'description' => 'Charged weight (e.g. 6000 kgs)'],
                'invoice_no' => ['type' => 'STRING', 'description' => 'Original invoice number'],
                'goods_value' => ['type' => 'STRING', 'description' => 'Value of the goods in Rs.'],
                'pod_required' => ['type' => 'STRING', 'enum' => ['YES', 'NO'], 'description' => 'Whether proof of delivery (POD) is required'],
                'basic_freight' => ['type' => 'NUMBER', 'description' => 'Basic freight charges'],
                'local_collection' => ['type' => 'NUMBER', 'description' => 'Local collection charges'],
                'door_delivery' => ['type' => 'NUMBER', 'description' => 'Door delivery charges'],
                'hamali' => ['type' => 'NUMBER', 'description' => 'Hamali / loading charges'],
                'docket_charge' => ['type' => 'NUMBER', 'description' => 'Docket charges'],
                'other_charge' => ['type' => 'NUMBER', 'description' => 'Any other charges'],
                'fov' => ['type' => 'NUMBER', 'description' => 'FOV charges'],
            ],
            'required' => [],
        ];

        $models = [
            'gemini-2.5-flash',
            'gemini-1.5-flash',
            'gemini-1.5-pro',
        ];

        $response = null;

        try {
            foreach ($models as $model) {
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent?key='.$apiKey;

                $response = Http::retry(2, 1000, function ($response, $attempt) {
                    return $response->status() === 429 || $response->status() >= 500;
                }, false)->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => 'You are an expert document parsing assistant. Extract all data from this invoice or E-way bill to populate a Lorry Receipt (LR) record. Return the values in the exact JSON format specified by the response schema. If a value is not found, leave it as null or empty. Do not invent any data.',
                                ],
                                [
                                    'inlineData' => [
                                        'mimeType' => $mimeType,
                                        'data' => $base64Data,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseSchema' => $schema,
                    ],
                ]);

                if ($response->successful()) {
                    break;
                }

                Log::warning("Gemini model {$model} failed with status {$response->status()}. Trying next model...");
            }

            if ($response === null || $response->failed()) {
                $body = $response ? $response->body() : 'No response';
                Log::error('Gemini API auto-fill failed after trying all models: '.$body);

                $status = $response ? $response->status() : 500;
                $errorMessage = 'Failed to parse document with Gemini API.';

                if ($status === 503) {
                    $errorMessage = 'The AI service is currently experiencing high demand. Please try again in a few seconds.';
                } elseif ($status === 429) {
                    $errorMessage = 'Rate limit exceeded. Please try again in a moment.';
                } elseif ($response) {
                    $errorMessage .= ' '.($response->json('error.message') ?? $response->body());
                }

                return response()->json([
                    'error' => $errorMessage,
                ], 500);
            }

            $result = $response->json();
            $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
            $data = json_decode($text, true) ?? [];
            Log::info('Gemini parsed text: '.$text);

            // Perform lookups for Consignor and Consignee
            $consignorData = null;
            if (! empty($data['consignor_name'])) {
                $consignorCity = $this->resolveCity($data['consignor_city'] ?? null, $data['from'] ?? null, $data['consignor_address'] ?? null);
                $consignor = $this->findCustomer($data['consignor_name'], $data['consignor_gstin'] ?? null, Customer::TYPE_CUSTOMER, $consignorCity);
                if (! $consignor) {
                    $abbrev = $this->generateAbbreviation($consignorCity);
                    $count = Customer::whereCompany()->where('type', Customer::TYPE_CUSTOMER)->count();
                    $prefix = $abbrev !== '' ? ($count + 101).$abbrev : null;

                    $consignor = Customer::create([
                        'name' => $data['consignor_name'],
                        'phone' => $data['consignor_phone'] ?? null,
                        'tax_id' => $data['consignor_gstin'] ?? null,
                        'type' => Customer::TYPE_CUSTOMER,
                        'prefix' => $prefix,
                        'company_id' => request()->header('company'),
                    ]);

                    [$street1, $street2] = $this->splitAddress($data['consignor_address'] ?? '');
                    $consignor->billingAddress()->create([
                        'name' => $data['consignor_name'],
                        'address_street_1' => $street1,
                        'address_street_2' => $street2,
                        'city' => $consignorCity,
                        'country_id' => 1,
                        'type' => 'billing',
                    ]);
                }
                $consignorData = (new CustomerResource($consignor->load(['billingAddress', 'shippingAddress'])))->toArray(request());
            }

            $consigneeData = null;
            if (! empty($data['consignee_name'])) {
                $consigneeCity = $this->resolveCity($data['consignee_city'] ?? null, $data['to'] ?? null, $data['consignee_address'] ?? null);
                $consignee = $this->findCustomer($data['consignee_name'], $data['consignee_gstin'] ?? null, Customer::TYPE_CONSIGNEE, $consigneeCity);
                if (! $consignee) {
                    $abbrev = $this->generateAbbreviation($consigneeCity);
                    $count = Customer::whereCompany()->where('type', Customer::TYPE_CONSIGNEE)->count();
                    $prefix = $abbrev !== '' ? ($count + 101).$abbrev : null;

                    $consignee = Customer::create([
                        'name' => $data['consignee_name'],
                        'phone' => $data['consignee_phone'] ?? null,
                        'tax_id' => $data['consignee_gstin'] ?? null,
                        'type' => Customer::TYPE_CONSIGNEE,
                        'prefix' => $prefix,
                        'company_id' => request()->header('company'),
                    ]);

                    [$street1, $street2] = $this->splitAddress($data['consignee_address'] ?? '');
                    $consignee->billingAddress()->create([
                        'name' => $data['consignee_name'],
                        'address_street_1' => $street1,
                        'address_street_2' => $street2,
                        'city' => $consigneeCity,
                        'country_id' => 1,
                        'type' => 'billing',
                    ]);
                }
                $consigneeData = (new CustomerResource($consignee->load(['billingAddress', 'shippingAddress'])))->toArray(request());
            }

            // Map payment mode to uppercase matched option
            $modeOfPayment = null;
            if (! empty($data['mode_of_payment'])) {
                $m = strtoupper($data['mode_of_payment']);
                if (in_array($m, ['TO PAY', 'PAID', 'TO BE BILLED AT'])) {
                    $modeOfPayment = $m;
                }
            }

            // Map GST Tax Payable By
            $gstPayableBy = null;
            if (! empty($data['gst_payable_by'])) {
                $g = ucfirst(strtolower($data['gst_payable_by']));
                if (in_array($g, ['Consignor', 'Consignee'])) {
                    $gstPayableBy = $g;
                }
            }

            // Map POD Required
            $podRequired = null;
            if (! empty($data['pod_required'])) {
                $p = strtoupper($data['pod_required']);
                if (in_array($p, ['YES', 'NO'])) {
                    $podRequired = $p;
                }
            }

            $formattedResponse = [
                'consignor' => $consignorData,
                'consignee' => $consigneeData,
                'date' => ! empty($data['date']) ? Carbon::parse($data['date'])->format('Y-m-d') : null,
                'due_date' => ! empty($data['due_date']) ? Carbon::parse($data['due_date'])->format('Y-m-d') : null,
                'docket_no' => ! empty($data['docket_no']) ? (string) $data['docket_no'] : null,
                'fields' => [
                    'From' => $data['from'] ?? null,
                    'To' => $data['to'] ?? null,
                    'Truck No' => $data['truck_no'] ?? null,
                    'Consignor Phone No' => $data['consignor_phone'] ?? null,
                    'Consignor GST No' => $data['consignor_gstin'] ?? null,
                    'Consignee Phone No' => $data['consignee_phone'] ?? null,
                    'Consignee GST No' => $data['consignee_gstin'] ?? null,
                    'Mode of Payment' => $modeOfPayment,
                    'GST Tax Payable By' => $gstPayableBy,
                ],
                'item_fields' => [
                    'Description of Goods' => $data['description_of_goods'] ?? null,
                    'HSN Code' => $data['hsn_code'] ?? null,
                    'Delivery At' => $data['delivery_at'] ?? null,
                    'E-way Bill No' => $data['eway_bill_no'] ?? null,
                    'No of Articles' => $data['no_of_articles'] ?? null,
                    'Packing' => $data['packing'] ?? null,
                    'Actual Weight' => $data['actual_weight'] ?? null,
                    'Charged Weight' => $data['charged_weight'] ?? null,
                    'Invoice No' => $data['invoice_no'] ?? null,
                    'Goods Value' => $data['goods_value'] ?? null,
                    'POD Required' => $podRequired,
                    'Basic Freight' => $data['basic_freight'] ?? null,
                    'Local Collection' => $data['local_collection'] ?? null,
                    'Door Delivery' => $data['door_delivery'] ?? null,
                    'Hamali' => $data['hamali'] ?? null,
                    'Docket Charge' => $data['docket_charge'] ?? 100,
                    'Other Charge' => $data['other_charge'] ?? null,
                    'FOV' => $data['fov'] ?? null,
                ],
            ];

            return response()->json($formattedResponse);

        } catch (\Exception $e) {
            Log::error('LrReceiptAutoFillController exception: '.$e->getMessage());

            return response()->json([
                'error' => 'An error occurred while processing the document: '.$e->getMessage(),
            ], 500);
        }
    }

    private function findCustomer(string $name, ?string $gstin, string $type = 'CUSTOMER', ?string $city = null): ?Customer
    {
        $gstin = trim((string) $gstin);
        if ($gstin !== '') {
            $customer = Customer::whereCompany()
                ->where('type', $type)
                ->where('tax_id', $gstin)
                ->with(['billingAddress', 'shippingAddress'])
                ->first();
            if ($customer) {
                return $customer;
            }
        }

        $normalizedName = $this->normalizeName($name);
        if ($normalizedName === '') {
            return null;
        }

        $query = Customer::whereCompany()
            ->where('type', $type)
            ->where(function ($q) use ($name) {
                $q->where('name', $name)
                    ->orWhereRaw('LOWER(name) = ?', [strtolower($name)]);
            });

        if (! empty($city)) {
            $customer = (clone $query)->whereHas('billingAddress', function ($q) use ($city) {
                $q->whereRaw('LOWER(city) = ?', [strtolower(trim($city))]);
            })->first();

            if ($customer) {
                return $customer->load(['billingAddress', 'shippingAddress']);
            }
        } else {
            $customer = $query->first();
            if ($customer) {
                return $customer->load(['billingAddress', 'shippingAddress']);
            }
        }

        return null;
    }

    private function normalizeName(?string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $name));
    }

    private function splitAddress(string $addressBlock, int $maxLineLength = 45): array
    {
        $addressBlock = trim($addressBlock);
        if ($addressBlock === '') {
            return ['', ''];
        }

        $lines = preg_split('/\r\n|\r|\n/', $addressBlock);
        $wrappedLines = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (mb_strlen($line) > $maxLineLength) {
                $wrapped = wordwrap($line, $maxLineLength, "\n", false);
                $parts = explode("\n", $wrapped);
                foreach ($parts as $part) {
                    $wrappedLines[] = trim($part);
                }
            } else {
                $wrappedLines[] = $line;
            }
        }

        $street1 = isset($wrappedLines[0]) ? $wrappedLines[0] : '';
        $street2 = implode("\n", array_slice($wrappedLines, 1));

        return [$street1, $street2];
    }

    private function generateAbbreviation(?string $city): string
    {
        if (empty($city)) {
            return '';
        }
        $cityName = trim(strtoupper($city));

        $dictionary = [
            'UMBERGAON' => 'UMB',
            'UMBARGAON' => 'UMB',
            'VAPI' => 'VAPI',
            'SURAT' => 'SURAT',
            'MUMBAI' => 'MUM',
            'DAMAN' => 'DAM',
            'SILVASSA' => 'SIL',
            'AHMEDABAD' => 'AMD',
        ];

        if (isset($dictionary[$cityName])) {
            return $dictionary[$cityName];
        } elseif (strlen($cityName) <= 4) {
            return $cityName;
        } else {
            return substr($cityName, 0, 3);
        }
    }

    private function resolveCity(?string $specifiedCity, ?string $fallbackPlace, ?string $address): ?string
    {
        $city = trim((string) $specifiedCity);
        if ($city !== '') {
            return $city;
        }

        $fallbackPlace = trim((string) $fallbackPlace);
        if ($fallbackPlace !== '') {
            return $fallbackPlace;
        }

        return null;
    }
}
