<?php

namespace App\Services\Document;

use App\Models\CompanySetting;
use App\Models\ExchangeRateLog;
use App\Models\Expense;
use App\Models\LoadTrip;
use App\Models\LorryPartyProfile;
use App\Models\Truck;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function create(Request $request): Expense
    {
        $payload = $request->getExpensePayload();
        $this->validateFleetLinks($payload, (int) $request->header('company'));
        $expense = Expense::create($payload);

        $companyCurrency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $expense['currency_id'] !== $companyCurrency) {
            ExchangeRateLog::addExchangeRateLog($expense);
        }

        if ($request->hasFile('attachment_receipt')) {
            $expense->addMediaFromRequest('attachment_receipt')->toMediaCollection('receipts');
        }

        if ($request->customFields) {
            $expense->addCustomFields(json_decode($request->customFields));
        }

        return $expense;
    }

    public function update(Expense $expense, Request $request): bool
    {
        $data = $request->getExpensePayload();
        $this->validateFleetLinks($data, (int) $request->header('company'));

        $expense->update($data);

        $companyCurrency = CompanySetting::getSetting('currency', $request->header('company'));

        if ((string) $data['currency_id'] !== $companyCurrency) {
            ExchangeRateLog::addExchangeRateLog($expense);
        }

        if (isset($request->is_attachment_receipt_removed) && (bool) $request->is_attachment_receipt_removed) {
            $expense->clearMediaCollection('receipts');
        }
        if ($request->hasFile('attachment_receipt')) {
            $expense->clearMediaCollection('receipts');
            $expense->addMediaFromRequest('attachment_receipt')->toMediaCollection('receipts');
        }

        if ($request->customFields) {
            $expense->updateCustomFields(json_decode($request->customFields));
        }

        return true;
    }

    public function validateFleetLinks(array $data, int $companyId): void
    {
        $truck = ! empty($data['truck_id']) ? Truck::forCompany($companyId)->findOrFail($data['truck_id']) : null;
        $trip = ! empty($data['load_trip_id']) ? LoadTrip::forCompany($companyId)->findOrFail($data['load_trip_id']) : null;
        $driver = ! empty($data['driver_profile_id'])
            ? LorryPartyProfile::query()->where('company_id', $companyId)->where('type', LorryPartyProfile::TYPE_DRIVER)->findOrFail($data['driver_profile_id'])
            : null;

        if ($truck && $trip && $trip->truck_id && $trip->truck_id !== $truck->id) {
            throw ValidationException::withMessages(['truck_id' => ['The selected truck does not match the selected load trip.']]);
        }

        if ($driver && $trip && $trip->driver_profile_id && $trip->driver_profile_id !== $driver->id) {
            throw ValidationException::withMessages(['driver_profile_id' => ['The selected driver does not match the selected load trip.']]);
        }
    }
}
