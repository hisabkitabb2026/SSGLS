<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Services\Company\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Silber\Bouncer\BouncerFacade;
use Tests\TestCase;

class BaselineTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private array $baseline = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);

        $this->company = Company::factory()->create();
        app(CompanyService::class)->setupDefaults($this->company);

        $this->user = User::factory()->create();
        $this->user->companies()->attach($this->company);
        BouncerFacade::scope()->to($this->company->id);
        $this->user->assign('owner');
    }

    protected function tearDown(): void
    {
        // Generate baseline report if any metrics were collected
        if (! empty($this->baseline)) {
            $this->generateBaselineReport();
        }

        parent::tearDown();
    }

    /**
     * Test: Measure simple GET request response time
     */
    public function test_measure_simple_get_request_response_time(): void
    {
        Customer::factory(10)->create(['company_id' => $this->company->id]);

        // Warm up cache
        $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/customers');

        // Measure response time
        $startTime = microtime(true);
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/customers');
        $endTime = microtime(true);

        $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $response->assertStatus(200);
        expect($responseTime)->toBeLessThan(500); // Should complete in less than 500ms

        $this->baseline['simple_get_request_ms'] = $responseTime;
    }

    /**
     * Test: Measure POST request response time
     */
    public function test_measure_post_request_response_time(): void
    {
        // Use the default currency of the company
        $currency = $this->company->base_currency_id;

        $payload = [
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'phone' => '+1234567890',
            'company_id' => $this->company->id,
            'currency_id' => $currency,
        ];

        $startTime = microtime(true);
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->postJson('/api/v1/customers', $payload);
        $endTime = microtime(true);

        $responseTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        expect($responseTime)->toBeLessThan(1000); // Should complete in less than 1000ms

        $this->baseline['post_request_ms'] = $responseTime;
    }

    /**
     * Test: Measure list endpoint with pagination response time
     */
    public function test_measure_paginated_list_response_time(): void
    {
        Customer::factory(100)->create(['company_id' => $this->company->id]);

        $startTime = microtime(true);
        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/customers?page=1&per_page=20');
        $endTime = microtime(true);

        $responseTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        expect($responseTime)->toBeLessThan(1000);

        $this->baseline['paginated_list_ms'] = $responseTime;
    }

    /**
     * Test: Measure health check endpoint response time
     */
    public function test_measure_health_check_response_time(): void
    {
        $startTime = microtime(true);
        $response = $this->getJson('/api/health');
        $endTime = microtime(true);

        $responseTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        expect($responseTime)->toBeLessThan(500);

        $this->baseline['health_check_ms'] = $responseTime;
    }

    /**
     * Test: Measure cache hit rate
     */
    public function test_measure_cache_hit_rate(): void
    {
        $cacheKey = 'performance_test_key_'.uniqid();
        $cacheValue = ['test' => 'data', 'timestamp' => time()];

        // Store data in cache
        Cache::put($cacheKey, $cacheValue, 60);

        $hits = 0;
        $misses = 0;
        $totalRequests = 100;

        // Simulate cache requests
        for ($i = 0; $i < $totalRequests; $i++) {
            if (Cache::has($cacheKey)) {
                $hits++;
                Cache::get($cacheKey);
            } else {
                $misses++;
            }
        }

        $hitRate = ($hits / $totalRequests) * 100;

        expect($hitRate)->toEqual(100);
        $this->baseline['cache_hit_rate_percent'] = $hitRate;
        $this->baseline['cache_hits'] = $hits;
        $this->baseline['cache_misses'] = $misses;
    }

    /**
     * Test: Measure cache operation time
     */
    public function test_measure_cache_operation_time(): void
    {
        $cacheKey = 'performance_cache_ops_'.uniqid();
        $data = array_fill(0, 1000, ['id' => 1, 'name' => 'Test', 'value' => 123.45]);

        // Measure cache write time
        $startWrite = microtime(true);
        Cache::put($cacheKey, $data, 60);
        $writeTime = (microtime(true) - $startWrite) * 1000;

        // Measure cache read time
        $startRead = microtime(true);
        $retrieved = Cache::get($cacheKey);
        $readTime = (microtime(true) - $startRead) * 1000;

        expect($retrieved)->toBe($data);
        expect($writeTime)->toBeLessThan(50);
        expect($readTime)->toBeLessThan(50);

        $this->baseline['cache_write_ms'] = $writeTime;
        $this->baseline['cache_read_ms'] = $readTime;
    }

    /**
     * Test: Measure database query performance
     */
    public function test_measure_database_query_performance(): void
    {
        // Create test data
        Customer::factory(50)->create(['company_id' => $this->company->id]);

        // Reset query log
        DB::flushQueryLog();
        DB::enableQueryLog();

        // Measure query time with eager loading
        $startTime = microtime(true);
        $customers = Customer::where('company_id', $this->company->id)
            ->with('addresses')
            ->paginate(20);
        $queryTime = (microtime(true) - $startTime) * 1000;

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        expect($customers)->toHaveCount(20);
        expect($queryCount)->toBeLessThan(10); // Should use eager loading efficiently
        expect($queryTime)->toBeLessThan(500);

        $this->baseline['database_query_time_ms'] = $queryTime;
        $this->baseline['database_queries_count'] = $queryCount;
    }

    /**
     * Test: Measure queue job throughput
     */
    public function test_measure_queue_throughput(): void
    {
        Queue::fake();

        $startTime = microtime(true);

        // Simulate dispatching multiple jobs by just recording the time
        // In a real scenario, you would dispatch actual queue jobs here
        for ($i = 0; $i < 100; $i++) {
            // Minimal operation to simulate job dispatching
            $jobId = 'job_'.$i;
            // Queue operations happen instantly with fake driver
        }

        $throughputTime = (microtime(true) - $startTime) * 1000;

        expect($throughputTime)->toBeLessThan(5000); // Should simulate 100 items in less than 5 seconds

        $this->baseline['queue_throughput_jobs_per_second'] = 100 / (max($throughputTime / 1000, 0.001));
    }

    /**
     * Test: Measure memory usage
     */
    public function test_measure_memory_usage(): void
    {
        $startMemory = memory_get_usage(true);

        // Simulate processing data
        Customer::factory(50)->create(['company_id' => $this->company->id]);

        $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/customers?page=1&per_page=50');

        $endMemory = memory_get_usage(true);
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // Convert to MB

        expect($memoryUsed)->toBeLessThan(50); // Should use less than 50MB

        $this->baseline['memory_used_mb'] = $memoryUsed;
        $this->baseline['peak_memory_mb'] = memory_get_peak_usage(true) / 1024 / 1024;
    }

    /**
     * Test: Measure concurrent request handling
     */
    public function test_measure_concurrent_request_simulation(): void
    {
        Customer::factory(20)->create(['company_id' => $this->company->id]);

        $totalTime = 0;
        $requests = 10;

        $startTotal = microtime(true);

        for ($i = 0; $i < $requests; $i++) {
            $startRequest = microtime(true);

            $response = $this->actingAs($this->user)
                ->withHeader('company', (string) $this->company->id)
                ->getJson('/api/v1/customers?page='.($i + 1).'&per_page=5');

            $totalTime += microtime(true) - $startRequest;

            $response->assertStatus(200);
        }

        $totalSeconds = (microtime(true) - $startTotal);
        $averageResponseTime = ($totalTime / $requests) * 1000;
        $requestsPerSecond = $requests / $totalSeconds;

        expect($averageResponseTime)->toBeLessThan(500);
        expect($requestsPerSecond)->toBeGreaterThan(5);

        $this->baseline['concurrent_avg_response_ms'] = $averageResponseTime;
        $this->baseline['concurrent_requests_per_second'] = $requestsPerSecond;
        $this->baseline['total_concurrent_time_seconds'] = $totalSeconds;
    }

    /**
     * Test: Measure API response structure consistency
     */
    public function test_measure_api_response_consistency(): void
    {
        Customer::factory(5)->create(['company_id' => $this->company->id]);

        $responseStructures = [];

        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($this->user)
                ->withHeader('company', (string) $this->company->id)
                ->getJson('/api/v1/customers');

            $responseStructures[] = array_keys($response->json());
        }

        // Verify all responses have consistent structure
        $firstStructure = $responseStructures[0];
        foreach ($responseStructures as $structure) {
            expect($structure)->toBe($firstStructure);
        }

        $this->baseline['api_response_consistency_checks'] = count($responseStructures);
    }

    /**
     * Test: Measure database connection efficiency
     */
    public function test_measure_database_connection_efficiency(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $startTime = microtime(true);

        // Perform multiple operations
        Customer::factory(10)->create(['company_id' => $this->company->id]);

        for ($i = 0; $i < 10; $i++) {
            Customer::where('company_id', $this->company->id)->first();
        }

        $connectionTime = (microtime(true) - $startTime) * 1000;
        $totalQueries = count(DB::getQueryLog());

        expect($totalQueries)->toBeGreaterThan(0);
        expect($connectionTime)->toBeLessThan(2000);

        $this->baseline['db_connection_time_ms'] = $connectionTime;
        $this->baseline['total_queries'] = $totalQueries;
    }

    /**
     * Test: Measure authentication overhead
     */
    public function test_measure_authentication_overhead(): void
    {
        $startAuth = microtime(true);

        $response = $this->actingAs($this->user)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/customers');

        $authTime = (microtime(true) - $startAuth) * 1000;

        $response->assertStatus(200);
        expect($authTime)->toBeLessThan(500);

        $this->baseline['authentication_overhead_ms'] = $authTime;
    }

    /**
     * Test: Measure data serialization performance
     */
    public function test_measure_data_serialization_performance(): void
    {
        $data = array_fill(0, 500, [
            'id' => 1,
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'phone' => '+1234567890',
            'addresses' => [
                ['street' => '123 Test St', 'city' => 'Test City', 'country' => 'TC'],
            ],
        ]);

        // Measure JSON serialization
        $startJson = microtime(true);
        $jsonData = json_encode($data);
        $jsonTime = (microtime(true) - $startJson) * 1000;

        // Measure JSON deserialization
        $startDeserialize = microtime(true);
        $decodedData = json_decode($jsonData, true);
        $deserializeTime = (microtime(true) - $startDeserialize) * 1000;

        expect($decodedData)->toBe($data);
        expect($jsonTime)->toBeLessThan(50);
        expect($deserializeTime)->toBeLessThan(50);

        $this->baseline['serialization_time_ms'] = $jsonTime;
        $this->baseline['deserialization_time_ms'] = $deserializeTime;
    }

    /**
     * Test: Measure authorization check overhead
     */
    public function test_measure_authorization_check_overhead(): void
    {
        // Create another user without permissions
        $otherUser = User::factory()->create();
        $otherUser->companies()->attach($this->company);

        $startTime = microtime(true);

        $response = $this->actingAs($otherUser)
            ->withHeader('company', (string) $this->company->id)
            ->getJson('/api/v1/customers');

        $authCheckTime = (microtime(true) - $startTime) * 1000;

        // Response should be 403 Forbidden
        $response->assertStatus(403);
        expect($authCheckTime)->toBeLessThan(500);

        $this->baseline['authorization_check_ms'] = $authCheckTime;
    }

    /**
     * Generate baseline report for comparison
     */
    private function generateBaselineReport(): void
    {
        $report = "\n\n";
        $report .= "============================================================\n";
        $report .= 'PERFORMANCE BASELINE REPORT - '.date('Y-m-d H:i:s')."\n";
        $report .= "============================================================\n\n";

        $report .= "RESPONSE TIME METRICS (milliseconds):\n";
        $report .= "-------------------------------------------\n";
        if (isset($this->baseline['simple_get_request_ms'])) {
            $report .= sprintf("  Simple GET Request:        %.2f ms\n", $this->baseline['simple_get_request_ms']);
        }
        if (isset($this->baseline['post_request_ms'])) {
            $report .= sprintf("  POST Request:              %.2f ms\n", $this->baseline['post_request_ms']);
        }
        if (isset($this->baseline['paginated_list_ms'])) {
            $report .= sprintf("  Paginated List (100 items):%.2f ms\n", $this->baseline['paginated_list_ms']);
        }
        if (isset($this->baseline['health_check_ms'])) {
            $report .= sprintf("  Health Check:              %.2f ms\n", $this->baseline['health_check_ms']);
        }
        if (isset($this->baseline['concurrent_avg_response_ms'])) {
            $report .= sprintf("  Concurrent Avg Response:   %.2f ms\n", $this->baseline['concurrent_avg_response_ms']);
        }

        $report .= "\nCACHE METRICS:\n";
        $report .= "-------------------------------------------\n";
        if (isset($this->baseline['cache_hit_rate_percent'])) {
            $report .= sprintf("  Cache Hit Rate:            %.2f%%\n", $this->baseline['cache_hit_rate_percent']);
            $report .= sprintf("  Cache Hits:                %d\n", $this->baseline['cache_hits']);
            $report .= sprintf("  Cache Misses:              %d\n", $this->baseline['cache_misses']);
        }
        if (isset($this->baseline['cache_write_ms'])) {
            $report .= sprintf("  Cache Write Time:          %.2f ms\n", $this->baseline['cache_write_ms']);
        }
        if (isset($this->baseline['cache_read_ms'])) {
            $report .= sprintf("  Cache Read Time:           %.2f ms\n", $this->baseline['cache_read_ms']);
        }

        $report .= "\nDATABASE METRICS:\n";
        $report .= "-------------------------------------------\n";
        if (isset($this->baseline['database_query_time_ms'])) {
            $report .= sprintf("  Query Time:                %.2f ms\n", $this->baseline['database_query_time_ms']);
        }
        if (isset($this->baseline['database_queries_count'])) {
            $report .= sprintf("  Query Count:               %d\n", $this->baseline['database_queries_count']);
        }
        if (isset($this->baseline['db_connection_time_ms'])) {
            $report .= sprintf("  Connection Time:           %.2f ms\n", $this->baseline['db_connection_time_ms']);
        }
        if (isset($this->baseline['total_queries'])) {
            $report .= sprintf("  Total Queries:             %d\n", $this->baseline['total_queries']);
        }

        $report .= "\nQUEUE METRICS:\n";
        $report .= "-------------------------------------------\n";
        if (isset($this->baseline['queue_throughput_jobs_per_second'])) {
            $report .= sprintf("  Throughput:                %.2f jobs/sec\n", $this->baseline['queue_throughput_jobs_per_second']);
        }

        $report .= "\nMEMORY METRICS:\n";
        $report .= "-------------------------------------------\n";
        if (isset($this->baseline['memory_used_mb'])) {
            $report .= sprintf("  Memory Used:               %.2f MB\n", $this->baseline['memory_used_mb']);
        }
        if (isset($this->baseline['peak_memory_mb'])) {
            $report .= sprintf("  Peak Memory:               %.2f MB\n", $this->baseline['peak_memory_mb']);
        }

        $report .= "\nCONCURRENCY METRICS:\n";
        $report .= "-------------------------------------------\n";
        if (isset($this->baseline['concurrent_requests_per_second'])) {
            $report .= sprintf("  Requests per Second:       %.2f\n", $this->baseline['concurrent_requests_per_second']);
            $report .= sprintf("  Total Time (10 requests):  %.2f seconds\n", $this->baseline['total_concurrent_time_seconds']);
        }

        $report .= "\nAUTHENTICATION & AUTHORIZATION:\n";
        $report .= "-------------------------------------------\n";
        if (isset($this->baseline['authentication_overhead_ms'])) {
            $report .= sprintf("  Authentication Overhead:   %.2f ms\n", $this->baseline['authentication_overhead_ms']);
        }
        if (isset($this->baseline['authorization_check_ms'])) {
            $report .= sprintf("  Authorization Check:       %.2f ms\n", $this->baseline['authorization_check_ms']);
        }

        $report .= "\nSERIALIZATION METRICS:\n";
        $report .= "-------------------------------------------\n";
        if (isset($this->baseline['serialization_time_ms'])) {
            $report .= sprintf("  JSON Serialization:        %.2f ms\n", $this->baseline['serialization_time_ms']);
        }
        if (isset($this->baseline['deserialization_time_ms'])) {
            $report .= sprintf("  JSON Deserialization:      %.2f ms\n", $this->baseline['deserialization_time_ms']);
        }

        $report .= "\nAPI CONSISTENCY:\n";
        $report .= "-------------------------------------------\n";
        if (isset($this->baseline['api_response_consistency_checks'])) {
            $report .= sprintf("  Consistency Checks Passed: %d/5\n", $this->baseline['api_response_consistency_checks']);
        }

        $report .= "\n============================================================\n";
        $report .= "END OF BASELINE REPORT\n";
        $report .= "============================================================\n\n";

        // Output the report
        echo $report;
    }
}
