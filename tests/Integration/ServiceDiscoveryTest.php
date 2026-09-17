<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\ServiceDiscovery\ServiceDiscoveryClient;
use Tests\TestCase;

/**
 * Service Discovery Verification Tests
 *
 * Tests that all microservices can be discovered and are healthy
 */
class ServiceDiscoveryTest extends TestCase
{
    /**
     * Services configuration for testing
     */
    private const SERVICES = [
        'invoice' => ['port' => 8001, 'name' => 'invoice-service'],
        'expense' => ['port' => 8002, 'name' => 'expense-service'],
        'product' => ['port' => 8003, 'name' => 'product-service'],
        'customer' => ['port' => 8004, 'name' => 'customer-service'],
        'settings' => ['port' => 8005, 'name' => 'settings-service'],
        'transport' => ['port' => 8006, 'name' => 'transport-service'],
    ];

    public function test_service_discovery_registry_available(): void
    {
        $this->assertTrue(
            config('services.discovery.enabled', false),
            'Service discovery should be enabled'
        );
    }

    public function test_all_services_registered_in_discovery(): void
    {
        $registry = config('services.registry', []);

        foreach (self::SERVICES as $key => $service) {
            $this->assertArrayHasKey($key, $registry, "Service {$key} should be registered");
            $this->assertArrayHasKey('url', $registry[$key], "Service {$key} should have URL");
            $this->assertArrayHasKey('health_check', $registry[$key], "Service {$key} should have health check endpoint");
        }
    }

    public function test_service_health_endpoint_format(): void
    {
        $registry = config('services.registry', []);

        foreach ($registry as $serviceName => $serviceConfig) {
            $this->assertStringContainsString(
                '/health',
                $serviceConfig['health_check'] ?? '',
                "Service {$serviceName} health check endpoint should contain /health"
            );
        }
    }

    public function test_discover_service_by_name(): void
    {
        $discoveryService = app(ServiceDiscoveryClient::class);

        foreach (self::SERVICES as $key => $service) {
            $serviceInfo = $discoveryService->discover($key);

            $this->assertNotNull($serviceInfo, "Should discover service: {$key}");
            $this->assertEquals($key, $serviceInfo['name']);
            $this->assertArrayHasKey('url', $serviceInfo);
            $this->assertArrayHasKey('health_check', $serviceInfo);
        }
    }

    public function test_service_registration_includes_metadata(): void
    {
        $registry = config('services.registry', []);

        foreach ($registry as $serviceName => $serviceConfig) {
            $this->assertArrayHasKey('version', $serviceConfig, "{$serviceName} should have version");
            $this->assertArrayHasKey('timeout', $serviceConfig, "{$serviceName} should have timeout");
            $this->assertArrayHasKey('retries', $serviceConfig, "{$serviceName} should have retries");
        }
    }

    public function test_service_discovery_cache(): void
    {
        $discoveryService = app(ServiceDiscoveryClient::class);

        // First call should fetch from registry
        $first = $discoveryService->discover('invoice');

        // Second call should use cache
        $second = $discoveryService->discover('invoice');

        $this->assertEquals($first, $second);
    }

    public function test_discover_service_with_invalid_name(): void
    {
        $discoveryService = app(ServiceDiscoveryClient::class);

        $this->assertNull(
            $discoveryService->discover('non-existent-service'),
            'Should return null for non-existent service'
        );
    }

    public function test_service_discovery_returns_healthy_services(): void
    {
        $discoveryService = app(ServiceDiscoveryClient::class);

        $healthyServices = $discoveryService->getHealthyServices();

        $this->assertIsArray($healthyServices);
        $this->assertGreaterThan(0, count($healthyServices), 'Should have at least one healthy service');

        foreach ($healthyServices as $service) {
            $this->assertEquals('healthy', $service['status'] ?? null);
        }
    }

    public function test_service_discovery_invalidates_cache_on_failure(): void
    {
        $discoveryService = app(ServiceDiscoveryClient::class);

        // Get initial service info
        $initial = $discoveryService->discover('invoice');
        $this->assertNotNull($initial);

        // Simulate failure - cache should be cleared
        $discoveryService->invalidateCache('invoice');

        // Next call should re-fetch
        $refreshed = $discoveryService->discover('invoice');
        $this->assertNotNull($refreshed);
    }

    public function test_service_registry_contains_required_fields(): void
    {
        $registry = config('services.registry', []);

        $requiredFields = ['name', 'url', 'health_check', 'version', 'timeout', 'retries'];

        foreach ($registry as $serviceName => $serviceConfig) {
            foreach ($requiredFields as $field) {
                $this->assertArrayHasKey(
                    $field,
                    $serviceConfig,
                    "Service {$serviceName} should have {$field}"
                );
            }
        }
    }

    public function test_service_registry_urls_are_valid(): void
    {
        $registry = config('services.registry', []);

        foreach ($registry as $serviceName => $serviceConfig) {
            $url = $serviceConfig['url'] ?? '';

            $this->assertNotEmpty($url, "{$serviceName} URL should not be empty");
            $this->assertTrue(
                filter_var($url, FILTER_VALIDATE_URL) !== false,
                "{$serviceName} URL should be valid: {$url}"
            );
        }
    }

    public function test_service_discovery_returns_all_services(): void
    {
        $discoveryService = app(ServiceDiscoveryClient::class);

        $allServices = $discoveryService->getAllServices();

        $this->assertIsArray($allServices);
        $this->assertGreaterThanOrEqual(
            count(self::SERVICES),
            count($allServices),
            'Should return all registered services'
        );
    }
}
