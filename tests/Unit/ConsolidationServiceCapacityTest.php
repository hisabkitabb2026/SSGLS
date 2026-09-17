<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Company;
use App\Models\ConsolidationGroup;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\Document\ConsolidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConsolidationServiceCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_add_an_lr_that_exceeds_the_truck_capacity(): void
    {
        $company = $this->createCompany();
        $group = $this->createGroup($company, 9000, 8500);
        $item = $this->createStoredItem($company, 1000);

        try {
            app(ConsolidationService::class)->addItemToGroup($company->id, $group->id, $item->id);
            $this->fail('Expected an over-capacity LR to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Cannot add this LR: it weighs 1000.00 kg, but only 500.00 kg capacity remains. Truck capacity is 9000.00 kg.',
                $exception->errors()['item_id'][0]
            );
        }

        $this->assertDatabaseHas('warehouse_items', [
            'id' => $item->id,
            'consolidation_id' => null,
            'status' => WarehouseItem::STATUS_STORED,
        ]);
        $this->assertDatabaseHas('consolidation_groups', [
            'id' => $group->id,
            'total_weight_kg' => 8500,
        ]);
    }

    public function test_it_allows_an_lr_that_exactly_fills_the_truck_capacity(): void
    {
        $company = $this->createCompany();
        $group = $this->createGroup($company, 9000, 8500);
        $item = $this->createStoredItem($company, 500);

        app(ConsolidationService::class)->addItemToGroup($company->id, $group->id, $item->id);

        $this->assertDatabaseHas('warehouse_items', [
            'id' => $item->id,
            'consolidation_id' => $group->id,
            'status' => WarehouseItem::STATUS_PICKED,
        ]);
        $this->assertDatabaseHas('consolidation_groups', [
            'id' => $group->id,
            'total_weight_kg' => 9000,
            'total_items' => 2,
        ]);
    }

    public function test_it_does_not_allow_capacity_to_be_reduced_below_the_current_load(): void
    {
        $company = $this->createCompany();
        $group = $this->createGroup($company, 9000, 8500);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Truck capacity cannot be less than the current load of 8500.00 kg.');

        app(ConsolidationService::class)->updateGroup($group, ['truck_capacity_kg' => 8000]);
    }

    public function test_it_splits_an_lr_load_and_keeps_the_balance_in_the_warehouse(): void
    {
        $company = $this->createCompany();
        $group = $this->createGroup($company, 9000, 8500);
        $item = $this->createStoredItem($company, 1000, 10);

        app(ConsolidationService::class)->splitItemToGroup($company->id, $group->id, $item->id, 500, 5);

        $this->assertDatabaseHas('warehouse_items', [
            'id' => $item->id,
            'weight_kg' => 500,
            'no_of_packages' => 5,
            'consolidation_id' => $group->id,
            'status' => WarehouseItem::STATUS_PICKED,
        ]);
        $this->assertDatabaseHas('warehouse_items', [
            'company_id' => $company->id,
            'lr_id' => $item->lr_id,
            'weight_kg' => 500,
            'no_of_packages' => 5,
            'split_from_warehouse_item_id' => $item->id,
            'consolidation_id' => null,
            'status' => WarehouseItem::STATUS_STORED,
        ]);
        $this->assertDatabaseHas('consolidation_groups', [
            'id' => $group->id,
            'total_weight_kg' => 9000,
            'total_packages' => 5,
        ]);
    }

    private function createGroup(Company $company, int $capacityKg, int $loadedWeightKg): ConsolidationGroup
    {
        $group = ConsolidationGroup::create([
            'company_id' => $company->id,
            'group_number' => 'CONS-2026-0001',
            'destination_city' => 'Mumbai',
            'truck_capacity_kg' => $capacityKg,
            'total_weight_kg' => $loadedWeightKg,
        ]);

        if ($loadedWeightKg > 0) {
            WarehouseItem::create([
                'company_id' => $company->id,
                'lr_id' => 2,
                'consolidation_id' => $group->id,
                'destination_city' => 'Mumbai',
                'status' => WarehouseItem::STATUS_PICKED,
                'weight_kg' => $loadedWeightKg,
            ]);
        }

        return $group;
    }

    private function createCompany(): Company
    {
        User::factory()->create(['role' => 'super admin']);

        return Company::factory()->create();
    }

    private function createStoredItem(Company $company, int $weightKg, int $packages = 0): WarehouseItem
    {
        return WarehouseItem::create([
            'company_id' => $company->id,
            'lr_id' => 1,
            'destination_city' => 'Mumbai',
            'status' => WarehouseItem::STATUS_STORED,
            'weight_kg' => $weightKg,
            'no_of_packages' => $packages,
        ]);
    }
}
