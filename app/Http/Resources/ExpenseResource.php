<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'expense_date' => $this->expense_date,
            'expense_number' => $this->expense_number,
            'amount' => $this->amount,
            'notes' => $this->notes,
            'customer_id' => $this->customer_id,
            'attachment_receipt_url' => $this->receipt_url,
            'attachment_receipt' => $this->receipt,
            'attachment_receipt_meta' => $this->receipt_meta,
            'company_id' => $this->company_id,
            'expense_category_id' => $this->expense_category_id,
            'creator_id' => $this->creator_id,
            'formatted_expense_date' => $this->formattedExpenseDate,
            'formatted_created_at' => $this->formattedCreatedAt,
            'exchange_rate' => $this->exchange_rate,
            'currency_id' => $this->currency_id,
            'base_amount' => $this->base_amount,
            'payment_method_id' => $this->payment_method_id,
            'truck_id' => $this->truck_id,
            'load_trip_id' => $this->load_trip_id,
            'driver_profile_id' => $this->driver_profile_id,
            'odometer_reading_km' => $this->odometer_reading_km,
            'customer' => $this->whenLoaded('customer', fn () => new CustomerResource($this->customer)),
            'expense_category' => $this->whenLoaded('category', fn () => new ExpenseCategoryResource($this->category)),
            'creator' => $this->whenLoaded('creator', fn () => new UserResource($this->creator)),
            'fields' => $this->whenLoaded('fields', fn () => CustomFieldValueResource::collection($this->fields)),
            'company' => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
            'currency' => $this->whenLoaded('currency', fn () => new CurrencyResource($this->currency)),
            'payment_method' => $this->whenLoaded('paymentMethod', fn () => new PaymentMethodResource($this->paymentMethod)),
            'truck' => TruckResource::make($this->whenLoaded('truck')),
            'load_trip' => LoadTripResource::make($this->whenLoaded('loadTrip')),
            'driver_profile' => LorryPartyProfileResource::make($this->whenLoaded('driverProfile')),
        ];
    }
}
