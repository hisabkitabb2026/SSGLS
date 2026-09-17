<?php

namespace App\Services\Document;

use App\Mail\FleetComplianceReminderMail;
use App\Models\Company;
use App\Models\FleetComplianceReminder;
use App\Models\LorryPartyProfile;
use App\Models\Truck;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class FleetComplianceReminderService
{
    private const REMINDER_DAYS = [30, 15, 7, 0];

    public function sendDueReminders(): int
    {
        $sentCount = 0;

        Company::query()->with('owner')->whereNotNull('owner_id')->each(function (Company $company) use (&$sentCount) {
            if (! $company->owner?->email) {
                return;
            }

            $items = $this->getCompanyReminderItems($company->id);
            if ($items === []) {
                return;
            }

            Mail::to($company->owner->email)->send(new FleetComplianceReminderMail($company, $items));

            foreach ($items as $item) {
                FleetComplianceReminder::create([
                    'company_id' => $company->id,
                    'truck_id' => $item['truck_id'],
                    'driver_profile_id' => $item['driver_profile_id'],
                    'document_type' => $item['document_type'],
                    'expires_on' => $item['expires_on'],
                    'reminder_days' => $item['days_remaining'],
                    'sent_on' => today(),
                ]);
            }

            $sentCount++;
        });

        return $sentCount;
    }

    private function getCompanyReminderItems(int $companyId): array
    {
        $items = [];

        foreach (Truck::forCompany($companyId)->get() as $truck) {
            foreach (['rc_expiry_date' => 'RC', 'insurance_expiry_date' => 'Insurance', 'fitness_expiry_date' => 'Fitness certificate', 'permit_expiry_date' => 'Permit'] as $field => $label) {
                if ($truck->{$field}) {
                    $this->addReminderItem($items, $companyId, $truck->id, null, $label, Carbon::parse($truck->{$field}), $truck->truck_number);
                }
            }
        }

        LorryPartyProfile::query()->where('company_id', $companyId)->where('type', LorryPartyProfile::TYPE_DRIVER)->whereNotNull('valid_up_to')->each(function (LorryPartyProfile $driver) use (&$items, $companyId) {
            $this->addReminderItem($items, $companyId, null, $driver->id, 'Driver licence', Carbon::parse($driver->valid_up_to), $driver->name);
        });

        return $items;
    }

    private function addReminderItem(array &$items, int $companyId, ?int $truckId, ?int $driverProfileId, string $documentType, Carbon $expiresOn, string $subject): void
    {
        $daysRemaining = intdiv($expiresOn->copy()->startOfDay()->getTimestamp() - Carbon::today()->getTimestamp(), 86400);

        if (! in_array($daysRemaining, self::REMINDER_DAYS, true) || $this->alreadySent($companyId, $truckId, $driverProfileId, $documentType, $expiresOn, $daysRemaining)) {
            return;
        }

        $items[] = ['truck_id' => $truckId, 'driver_profile_id' => $driverProfileId, 'document_type' => $documentType, 'expires_on' => $expiresOn->toDateString(), 'days_remaining' => $daysRemaining, 'subject' => $subject];
    }

    private function alreadySent(int $companyId, ?int $truckId, ?int $driverProfileId, string $documentType, Carbon $expiresOn, int $daysRemaining): bool
    {
        return FleetComplianceReminder::query()->where('company_id', $companyId)->where('truck_id', $truckId)->where('driver_profile_id', $driverProfileId)->where('document_type', $documentType)->whereDate('expires_on', $expiresOn)->where('reminder_days', $daysRemaining)->exists();
    }
}
