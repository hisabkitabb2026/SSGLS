<?php

namespace App\Domains\Transport\Tests\Unit;

use App\Domains\Transport\Repositories\EloquentWarehouseItemRepository;
use Tests\TestCase;

class WarehouseItemRepositoryTest extends TestCase
{
    public function test_create_warehouse_item()
    {
        $repo = app(EloquentWarehouseItemRepository::class);
        $item = $repo->create([
            'company_id' => 1,
            'lr_id' => 1,
            'warehouse_location' => 'Zone A',
            'destination' => 'City B',
            'quantity' => 10,
            'weight' => 100.5,
        ]);
        $this->assertNotNull($item->id);
    }
}
