<?php

namespace App\Http\Controllers\CustomerPortal\Estimate;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\EstimateResource;
use App\Models\Company;
use App\Models\Estimate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class EstimatesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $limit = $request->has('limit') ? $request->limit : 10;

        $estimates = Estimate::with([
            'items',
            'customer',
            'currency',
            'taxes',
            'creator',
            'quotationStations.rates',
        ])
            ->where('status', '<>', 'DRAFT')
            ->whereCustomer(Auth::guard('customer')->id())
            ->when($request->has('estimate_type'), function ($query) use ($request) {
                $query->where('estimate_type', $request->estimate_type);
            })
            ->applyFilters($request->only([
                'status',
                'estimate_number',
                'from_date',
                'to_date',
                'orderByField',
                'orderBy',
            ]))
            ->latest()
            ->paginateData($limit);

        $countQuery = Estimate::where('status', '<>', 'DRAFT')
            ->whereCustomer(Auth::guard('customer')->id());
        if ($request->has('estimate_type')) {
            $countQuery->where('estimate_type', $request->estimate_type);
        }

        return EstimateResource::collection($estimates)
            ->additional(['meta' => [
                'estimateTotalCount' => $countQuery->count(),
            ]]);

    }

    /**
     * Display the specified resource.
     *
     * @param  Estimate  $estimate
     * @return Response
     */
    public function show(Company $company, $id, Request $request)
    {
        $estimate = $company->estimates()
            ->with([
                'items',
                'customer',
                'currency',
                'taxes',
                'creator',
                'quotationStations.rates',
            ])
            ->whereCustomer(Auth::guard('customer')->id())

            ->where('id', $id)
            ->when($request->has('estimate_type'), function ($query) use ($request) {
                $query->where('estimate_type', $request->estimate_type);
            })
            ->first();

        if (! $estimate) {
            return response()->json(['error' => 'estimate_not_found'], 404);
        }

        return new EstimateResource($estimate);
    }
}
