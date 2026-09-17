<?php

/**
 * Health Check Load Testing Script
 *
 * This script performs load testing on the /api/health endpoint with configurable
 * concurrency and duration, measuring response times and calculating latency percentiles.
 *
 * Usage:
 *   php tests/load/health_check_load_test.php --users=100 --duration=60
 *
 * Options:
 *   --users=N      Number of concurrent users (default: 50)
 *   --duration=N   Test duration in seconds (default: 30)
 *   --url=URL      Base URL of the application (default: from .env APP_URL)
 *   --help         Show this help message
 */
class HealthCheckLoadTest
{
    private string $baseUrl;

    private int $numUsers;

    private int $duration;

    private int $totalRequests = 0;

    private int $successfulRequests = 0;

    private int $failedRequests = 0;

    private array $responseTimes = [];

    private array $errorMessages = [];

    private float $startTime;

    private float $endTime;

    public function __construct(string $baseUrl, int $numUsers, int $duration)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->numUsers = max(1, $numUsers);
        $this->duration = max(1, $duration);
    }

    public function run(): void
    {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║          HEALTH CHECK LOAD TEST                               ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        echo "Configuration:\n";
        echo "  Base URL:        {$this->baseUrl}\n";
        echo "  Concurrent Users: {$this->numUsers}\n";
        echo "  Duration:         {$this->duration}s\n";
        echo "  Target Endpoint:  /api/health\n";
        echo "\n";
        echo "Starting test...\n\n";

        $this->startTime = microtime(true);
        $endTime = $this->startTime + $this->duration;

        $curlHandles = [];
        $multiHandle = curl_multi_init();

        // Phase 1: Initial request creation
        for ($i = 0; $i < $this->numUsers; $i++) {
            $handle = $this->createCurlHandle();
            curl_multi_add_handle($multiHandle, $handle);
            $curlHandles[$i] = [
                'handle' => $handle,
                'start_time' => microtime(true),
            ];
        }

        // Phase 2: Run requests with reuse
        $running = null;
        do {
            $currentTime = microtime(true);

            // Process running requests
            curl_multi_exec($multiHandle, $running);

            // Collect completed requests
            while ($info = curl_multi_info_read($multiHandle)) {
                $handle = $info['handle'];
                $this->processCompletedRequest($handle, $curlHandles);

                // If still within duration, reuse the handle for another request
                if ($currentTime < $endTime) {
                    $newHandle = $this->createCurlHandle();
                    curl_multi_add_handle($multiHandle, $newHandle);

                    // Find the slot for this reused handle
                    foreach ($curlHandles as $key => $data) {
                        if ($data['handle'] === $handle) {
                            $curlHandles[$key] = [
                                'handle' => $newHandle,
                                'start_time' => microtime(true),
                            ];
                            break;
                        }
                    }

                    curl_multi_remove_handle($multiHandle, $handle);
                    curl_close($handle);
                } else {
                    curl_multi_remove_handle($multiHandle, $handle);
                    curl_close($handle);
                    $running--;
                }
            }

            // Avoid busy waiting
            if ($running) {
                curl_multi_select($multiHandle, 0.1);
            }
        } while ($running > 0 && microtime(true) < $endTime);

        // Clean up remaining handles
        foreach ($curlHandles as $data) {
            if (is_resource($data['handle'])) {
                curl_multi_remove_handle($multiHandle, $data['handle']);
                curl_close($data['handle']);
            }
        }

        curl_multi_close($multiHandle);
        $this->endTime = microtime(true);

        $this->printResults();
    }

    private function createCurlHandle()
    {
        $handle = curl_init($this->baseUrl.'/api/health');

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        return $handle;
    }

    private function processCompletedRequest($handle, array &$curlHandles): void
    {
        $this->totalRequests++;

        // Find the request info
        $startTime = null;
        foreach ($curlHandles as &$data) {
            if ($data['handle'] === $handle) {
                $startTime = $data['start_time'];
                break;
            }
        }

        if ($startTime === null) {
            return;
        }

        $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);

        if ($httpCode >= 200 && $httpCode < 300 && empty($error)) {
            $this->successfulRequests++;
            $this->responseTimes[] = $responseTime;
        } else {
            $this->failedRequests++;
            $errorKey = "{$httpCode}";
            if (! empty($error)) {
                $errorKey .= " - {$error}";
            }
            $this->errorMessages[$errorKey] = ($this->errorMessages[$errorKey] ?? 0) + 1;
        }

        // Show progress every 10 requests
        if ($this->totalRequests % 10 === 0) {
            $elapsed = microtime(true) - $this->startTime;
            $rps = $this->totalRequests / $elapsed;
            printf("\rProgress: %d requests | %.2f req/s | Success: %d | Failed: %d",
                $this->totalRequests, $rps, $this->successfulRequests, $this->failedRequests);
        }
    }

    private function printResults(): void
    {
        echo "\n\n";
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║                    TEST RESULTS SUMMARY                        ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n";
        echo "\n";

        // Test Duration
        $elapsedTime = $this->endTime - $this->startTime;
        echo "Test Duration:\n";
        printf("  Elapsed Time:     %.2f seconds\n", $elapsedTime);
        echo "\n";

        // Request Statistics
        echo "Request Statistics:\n";
        printf("  Total Requests:   %d\n", $this->totalRequests);
        printf("  Successful:       %d (%.2f%%)\n", $this->successfulRequests,
            $this->totalRequests > 0 ? ($this->successfulRequests / $this->totalRequests) * 100 : 0);
        printf("  Failed:           %d (%.2f%%)\n", $this->failedRequests,
            $this->totalRequests > 0 ? ($this->failedRequests / $this->totalRequests) * 100 : 0);
        printf("  Requests/sec:     %.2f\n", $elapsedTime > 0 ? $this->totalRequests / $elapsedTime : 0);
        echo "\n";

        // Response Time Statistics
        if (! empty($this->responseTimes)) {
            echo "Response Time Statistics (in milliseconds):\n";
            sort($this->responseTimes);

            $min = min($this->responseTimes);
            $max = max($this->responseTimes);
            $avg = array_sum($this->responseTimes) / count($this->responseTimes);
            $p50 = $this->calculatePercentile($this->responseTimes, 50);
            $p95 = $this->calculatePercentile($this->responseTimes, 95);
            $p99 = $this->calculatePercentile($this->responseTimes, 99);

            printf("  Minimum:          %.2f ms\n", $min);
            printf("  Maximum:          %.2f ms\n", $max);
            printf("  Average:          %.2f ms\n", $avg);
            printf("  Median (p50):     %.2f ms\n", $p50);
            printf("  95th Percentile:  %.2f ms\n", $p95);
            printf("  99th Percentile:  %.2f ms\n", $p99);
            echo "\n";
        }

        // Error Summary
        if (! empty($this->errorMessages)) {
            echo "Error Summary:\n";
            foreach ($this->errorMessages as $error => $count) {
                printf("  %s: %d\n", $error, $count);
            }
            echo "\n";
        }

        // Performance Assessment
        echo "Performance Assessment:\n";
        if ($this->successfulRequests / max(1, $this->totalRequests) >= 0.99) {
            echo "  Status: ✓ PASSED - High success rate\n";
        } elseif ($this->successfulRequests / max(1, $this->totalRequests) >= 0.95) {
            echo "  Status: ⚠ WARNING - Acceptable success rate\n";
        } else {
            echo "  Status: ✗ FAILED - Low success rate\n";
        }

        if (! empty($this->responseTimes)) {
            $p95 = $this->calculatePercentile($this->responseTimes, 95);
            if ($p95 < 200) {
                echo "  Latency: ✓ EXCELLENT - p95 < 200ms\n";
            } elseif ($p95 < 500) {
                echo "  Latency: ✓ GOOD - p95 < 500ms\n";
            } elseif ($p95 < 1000) {
                echo "  Latency: ⚠ WARNING - p95 < 1000ms\n";
            } else {
                echo "  Latency: ✗ POOR - p95 >= 1000ms\n";
            }
        }

        echo "\n";
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║                    END OF REPORT                              ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n";
        echo "\n";
    }

    private function calculatePercentile(array $sortedData, float $percentile): float
    {
        $count = count($sortedData);
        if ($count === 0) {
            return 0;
        }

        $index = ($percentile / 100) * ($count - 1);
        $lower = floor($index);
        $upper = ceil($index);
        $weight = $index - $lower;

        if ($lower === $upper) {
            return $sortedData[$lower];
        }

        return $sortedData[$lower] * (1 - $weight) + $sortedData[$upper] * $weight;
    }

    public static function parseArguments(array $argv): array
    {
        $options = [
            'users' => 50,
            'duration' => 30,
            'url' => null,
            'help' => false,
        ];

        for ($i = 1; $i < count($argv); $i++) {
            $arg = $argv[$i];

            if ($arg === '--help' || $arg === '-h') {
                $options['help'] = true;
            } elseif (strpos($arg, '--') === 0) {
                [$key, $value] = explode('=', substr($arg, 2), 2) + [1 => ''];
                if (array_key_exists($key, $options)) {
                    $options[$key] = is_numeric($value) ? (int) $value : $value;
                }
            }
        }

        return $options;
    }

    public static function showHelp(): void
    {
        echo <<<'HELP'
Health Check Load Testing Script

Usage:
  php tests/load/health_check_load_test.php [options]

Options:
  --users=N      Number of concurrent users (default: 50)
  --duration=N   Test duration in seconds (default: 30)
  --url=URL      Base URL of the application (default: from .env APP_URL)
  --help         Show this help message

Examples:
  php tests/load/health_check_load_test.php --users=100 --duration=60
  php tests/load/health_check_load_test.php --users=200 --duration=120 --url=http://invoiceshelf.test
  php tests/load/health_check_load_test.php --help

HELP;
    }

    public static function loadAppUrl(): string
    {
        $envFile = __DIR__.'/../../.env';
        if (! file_exists($envFile)) {
            return 'http://localhost:8000';
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, 'APP_URL=') === 0) {
                return substr($line, 8);
            }
        }

        return 'http://localhost:8000';
    }
}

// Main execution
$options = HealthCheckLoadTest::parseArguments($GLOBALS['argv']);

if ($options['help']) {
    HealthCheckLoadTest::showHelp();
    exit(0);
}

$baseUrl = $options['url'] ?? HealthCheckLoadTest::loadAppUrl();
$numUsers = $options['users'];
$duration = $options['duration'];

$test = new HealthCheckLoadTest($baseUrl, $numUsers, $duration);
$test->run();
