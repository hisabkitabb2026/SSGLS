<?php

namespace Tests\Unit;

use App\Domains\Transport\Application\ConsolidateItemsService;
use Tests\TestCase;

class ConsolidateItemsServiceTest extends TestCase
{
    private ConsolidateItemsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ConsolidateItemsService::class);
    }

    public function test_consolidation_service_exists(): void
    {
        $this->assertNotNull($this->service);
    }
}
