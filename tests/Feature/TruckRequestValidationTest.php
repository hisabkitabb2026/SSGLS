<?php

use App\Models\Truck;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::findOrFail(1);
    $this->companyId = $user->companies()->firstOrFail()->id;
    $this->withHeader('company', $this->companyId);

    Sanctum::actingAs($user, ['*']);
});

test('a truck number can only be used once in a company', function () {
    Truck::query()->create([
        'company_id' => $this->companyId,
        'truck_number' => 'GJ-05-AB-1234',
        'capacity_kg' => 5000,
    ]);

    $this->postJson('/api/v1/trucks', [
        'truck_number' => 'GJ-05-AB-1234',
        'capacity_kg' => 5000,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('truck_number');
});
