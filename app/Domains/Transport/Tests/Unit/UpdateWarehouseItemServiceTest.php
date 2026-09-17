<?php

namespace Tests\Unit;

use App\Domains\Transport\Application\UpdateWarehouseItemService;
use App\Domains\Transport\Data\UpdateWarehouseItemData;
use App\Domains\Transport\Models\WarehouseItem;
use App\Models\Company;
use Tests\TestCase;

class UpdateWarehouseItemServiceTest extends TestCase
{
    private UpdateWarehouseItemService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UpdateWarehouseItemService::class);
    }

    public function test_can_update_warehouse_item(): void
    {
        $company = Company::factory()->create();
        $item = WarehouseItem::factory()->create(['company_id' => $company->id]);

        $data = new UpdateWarehouseItemData(
            status: 'shipped',
            destination: 'New Destination',
            quantity: 10,
            weight: 100.5,
        );

        $updated = $this->service->execute($item->id, $data);

        $this->assertEquals('shipped', $updated->status);
        $this->assertEquals('New Destination', $updated->destination);
    }
}
