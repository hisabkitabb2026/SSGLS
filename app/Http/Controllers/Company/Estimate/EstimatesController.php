<?php

namespace App\Http\Controllers\Company\Estimate;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteEstimatesRequest;
use App\Http\Requests\EstimatesRequest;
use App\Http\Requests\SendEstimatesRequest;
use App\Http\Resources\EstimateResource;
use App\Http\Resources\InvoiceResource;
use App\Jobs\GenerateDocumentPdfJob;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Services\Document\EstimateService;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;

class EstimatesController extends Controller
{
    public function __construct(
        private readonly EstimateService $estimateService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Estimate::class);

        $limit = $request->has('limit') ? $request->limit : 10;

        $query = Estimate::whereCompany()
            ->with(['customer', 'currency'])
            ->join('customers', 'customers.id', '=', 'estimates.customer_id')
            ->applyFilters($request->all());

        // Filter by estimate_type if provided (quotation vs estimate)
        if ($request->has('estimate_type')) {
            $query->where('estimates.estimate_type', $request->estimate_type);
        }

        $estimates = $query
            ->distinct()
            ->select('estimates.*', 'customers.name')
            ->latest()
            ->paginateData($limit);

        // Count only estimates matching the same filter
        $countQuery = Estimate::whereCompany();
        if ($request->has('estimate_type')) {
            $countQuery->where('estimate_type', $request->estimate_type);
        }

        return EstimateResource::collection($estimates)
            ->additional(['meta' => [
                'estimate_total_count' => $countQuery->count(),
            ]]);
    }

    public function store(EstimatesRequest $request)
    {
        $this->authorize('create', Estimate::class);

        $estimate = $this->estimateService->create($request);

        if ($request->has('estimateSend')) {
            $this->estimateService->send($estimate, $request->only(['title', 'body']));
        }

        GenerateDocumentPdfJob::dispatch($estimate, 'estimate', 'estimate_number');

        $estimate->load(['customer', 'currency', 'items.fields.customField', 'taxes', 'fields.customField', 'company']);

        return new EstimateResource($estimate);
    }

    public function show(Request $request, Estimate $estimate)
    {
        $this->authorize('view', $estimate);

        $relations = ['customer', 'currency', 'items.fields.customField', 'taxes', 'fields.customField', 'company'];

        // For quotations, also load quotationStations with their rates
        if ($estimate->estimate_type === 'quotation') {
            $relations[] = 'quotationStations.rates';
        }

        $estimate->load($relations);

        return new EstimateResource($estimate);
    }

    public function update(EstimatesRequest $request, Estimate $estimate)
    {
        $this->authorize('update', $estimate);

        $estimate = $this->estimateService->update($estimate, $request);

        GenerateDocumentPdfJob::dispatch($estimate, 'estimate', 'estimate_number', true);

        $estimate->load(['customer', 'currency', 'items.fields.customField', 'taxes', 'fields.customField', 'company']);

        return new EstimateResource($estimate);
    }

    public function delete(DeleteEstimatesRequest $request)
    {
        $this->authorize('delete multiple estimates');

        return $this->deleteBulk(Estimate::class, $request->ids);
    }

    public function send(SendEstimatesRequest $request, Estimate $estimate)
    {
        $this->authorize('send estimate', $estimate);

        $response = $this->estimateService->send($estimate, $request->all());

        return $this->successResponse(null, 200, $response);
    }

    public function sendPreview(SendEstimatesRequest $request, Estimate $estimate)
    {
        $this->authorize('send estimate', $estimate);

        $markdown = new Markdown(view(), config('mail.markdown'));

        $data = $this->estimateService->getSendData($estimate, $request->all());
        $data['url'] = $estimate->estimatePdfUrl;

        return $markdown->render('emails.send.estimate', ['data' => $data]);
    }

    public function clone(Request $request, Estimate $estimate)
    {
        $this->authorize('view', $estimate);
        $this->authorize('create', Estimate::class);

        $newEstimate = $this->estimateService->clone($estimate);

        return new EstimateResource($newEstimate);
    }

    public function convertToInvoice(Request $request, Estimate $estimate)
    {
        // Authorize access to the source estimate (tenant isolation) in addition
        // to the ability to create an invoice.
        $this->authorize('view', $estimate);
        $this->authorize('create', Invoice::class);

        $invoice = $this->estimateService->convertToInvoice($estimate);

        return new InvoiceResource($invoice);
    }

    public function changeStatus(Request $request, Estimate $estimate)
    {
        $this->authorize('send estimate', $estimate);

        $this->estimateService->changeStatus($estimate, $request->status);

        return $this->successResponse();
    }

    public function getStationRates(Request $request)
    {
        $this->authorize('viewAny', Estimate::class);

        $stationName = $request->query('station_name', '');

        if (! $stationName) {
            return response()->json(['rates' => []]);
        }

        // Fetch all rates for this station across all company estimates (case-insensitive)
        $rates = Estimate::whereCompany()
            ->with(['quotationStations' => function ($q) use ($stationName) {
                $q->whereRaw('UPPER(name) = ?', [strtoupper($stationName)])
                    ->with('quotationRates');
            }])
            ->get()
            ->flatMap(function ($estimate) {
                return $estimate->quotationStations->flatMap->quotationRates;
            })
            ->unique(fn ($rate) => $rate->capacity)
            ->map(fn ($rate) => [
                'capacity' => $rate->capacity,
                'rate' => $rate->rate,
            ])
            ->values();

        return response()->json(['rates' => $rates]);
    }
}
