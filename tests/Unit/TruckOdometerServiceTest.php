<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Truck;
use App\Models\User;
use App\Services\Document\TruckOdometerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    User::factory()->create(['role' => 'super admin']);
});

it('records a photographed odometer reading and updates the truck current reading', function (): void {
    $company = Company::factory()->create();
    $truck = Truck::create(['company_id' => $company->id, 'truck_number' => 'KA01AB1234', 'capacity_kg' => 5000]);

    $reading = app(TruckOdometerService::class)->record($company->id, ['truck_id' => $truck->id, 'reading_km' => 12500], UploadedFile::fake()->image('odometer.jpg'));

    expect($reading->reading_km)->toBe(12500)
        ->and($reading->getFirstMedia('odometer_reading_image'))->not->toBeNull()
        ->and($truck->fresh()->current_odometer_km)->toBe(12500);
});

it('rejects a manual odometer reading below the confirmed reading', function (): void {
    $company = Company::factory()->create();
    $truck = Truck::create(['company_id' => $company->id, 'truck_number' => 'KA01AB1234', 'capacity_kg' => 5000, 'current_odometer_km' => 12500]);

    app(TruckOdometerService::class)->record($company->id, ['truck_id' => $truck->id, 'reading_km' => 12499], UploadedFile::fake()->image('odometer.jpg'));
})->throws(ValidationException::class, 'cannot be lower');
