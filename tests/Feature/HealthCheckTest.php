<?php

use function Pest\Laravel\getJson;

test('health endpoint returns 200 response', function () {
    $response = getJson('/api/health');
    $response->assertStatus(200);
});

test('health endpoint returns healthy status', function () {
    $response = getJson('/api/health');
    $response->assertJsonPath('status', 'healthy');
});

test('health endpoint returns checks array', function () {
    $response = getJson('/api/health');
    $response->assertJsonStructure(['status', 'timestamp', 'checks']);
});

test('health endpoint includes database check', function () {
    $response = getJson('/api/health');
    $response->assertJsonStructure(['checks' => ['database' => ['status', 'driver', 'message']]]);
});

test('health endpoint includes cache check', function () {
    $response = getJson('/api/health');
    $response->assertJsonStructure(['checks' => ['cache' => ['status', 'driver', 'message']]]);
});

test('health endpoint includes queue check', function () {
    $response = getJson('/api/health');
    $response->assertJsonStructure(['checks' => ['queue' => ['status', 'driver', 'message']]]);
});

test('database check returns healthy status', function () {
    $response = getJson('/api/health');
    $databaseCheck = $response->json('checks.database');
    expect($databaseCheck['status'])->toBe('healthy');
});

test('cache check returns healthy status', function () {
    $response = getJson('/api/health');
    $cacheCheck = $response->json('checks.cache');
    expect($cacheCheck['status'])->toBe('healthy');
});

test('queue check returns healthy status', function () {
    $response = getJson('/api/health');
    $queueCheck = $response->json('checks.queue');
    expect($queueCheck['status'])->toBe('healthy');
});

test('all health checks have valid driver information', function () {
    $response = getJson('/api/health');
    $checks = $response->json('checks');

    foreach ($checks as $checkName => $checkData) {
        expect($checkData)->toHaveKey('driver');
        expect($checkData['driver'])->toBeString();
    }
});

test('all health checks have descriptive messages', function () {
    $response = getJson('/api/health');
    $checks = $response->json('checks');

    foreach ($checks as $checkName => $checkData) {
        expect($checkData)->toHaveKey('message');
        expect(strlen((string) $checkData['message']))->toBeGreaterThan(0);
    }
});

test('response includes valid timestamp', function () {
    $response = getJson('/api/health');
    $timestamp = $response->json('timestamp');

    expect($timestamp)->toBeTruthy();
    expect(strtotime($timestamp))->toBeInt();
});

test('overall status reflects aggregated checks', function () {
    $response = getJson('/api/health');
    $status = $response->json('status');
    $checks = $response->json('checks');

    $allHealthy = collect($checks)->every(fn ($check) => $check['status'] === 'healthy');

    if ($allHealthy) {
        expect($status)->toBe('healthy');
    } else {
        expect($status)->toBe('unhealthy');
    }
});

test('returns appropriate HTTP status code for health status', function () {
    $response = getJson('/api/health');

    $status = $response->json('status');
    if ($status === 'healthy') {
        expect($response->status())->toBe(200);
    } else {
        expect($response->status())->toBe(503);
    }
});

test('response is valid JSON', function () {
    $response = getJson('/api/health');

    $decoded = json_decode($response->content(), true);
    expect($decoded)->toBeTruthy();
    expect($response->json())->toBe($decoded);
});

test('endpoint is accessible without authentication', function () {
    $response = getJson('/api/health');
    expect($response->status())->toBe(200);
});

test('response maintains consistent structure across requests', function () {
    $response1 = getJson('/api/health');
    $response2 = getJson('/api/health');

    expect($response1->json('status'))->toBe($response2->json('status'));
    expect(array_keys($response1->json('checks')))->toBe(array_keys($response2->json('checks')));
});
