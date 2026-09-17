<?php

use App\Models\Company;
use App\Models\Truck;
use App\Models\User;
use App\Policies\TruckPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the active company owner can access fleet records without an explicit role ability', function () {
    $owner = User::factory()->create();
    $company = Company::factory()->create(['owner_id' => $owner->id]);
    $truck = Truck::query()->create([
        'company_id' => $company->id,
        'truck_number' => 'MH-01-AB-1234',
        'capacity_kg' => 1000,
    ]);

    request()->headers->set('company', (string) $company->id);

    $policy = app(TruckPolicy::class);

    expect($policy->viewAny($owner))->toBeTrue()
        ->and($policy->create($owner))->toBeTrue()
        ->and($policy->view($owner, $truck))->toBeTrue()
        ->and($policy->update($owner, $truck))->toBeTrue();
});
