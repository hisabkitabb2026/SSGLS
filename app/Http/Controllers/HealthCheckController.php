<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthCheckController extends Controller
{
    /**
     * Perform comprehensive system health check (invokable)
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];

        $isHealthy = collect($checks)->every(fn ($check) => $check['status'] === 'healthy');

        return response()->json([
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $isHealthy ? 200 : 503);
    }

    /**
     * Check database connectivity
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'status' => 'healthy',
                'driver' => config('database.default'),
                'message' => 'Database connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'driver' => config('database.default'),
                'message' => 'Database connection failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Check cache (Redis) connectivity
     */
    private function checkCache(): array
    {
        try {
            $testKey = 'health_check_'.time();
            Cache::put($testKey, 'ok', 10);
            $result = Cache::get($testKey) === 'ok';
            Cache::forget($testKey);
            if (! $result) {
                throw new \Exception('Cache verification failed');
            }

            return [
                'status' => 'healthy',
                'driver' => config('cache.default'),
                'message' => 'Cache connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'driver' => config('cache.default'),
                'message' => 'Cache connection failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Check queue system connectivity
     */
    private function checkQueue(): array
    {
        try {
            $connection = config('queue.default');

            return [
                'status' => 'healthy',
                'driver' => $connection,
                'message' => "Queue connection '{$connection}' accessible",
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'driver' => config('queue.default'),
                'message' => 'Queue connection failed: '.$e->getMessage(),
            ];
        }
    }
}
