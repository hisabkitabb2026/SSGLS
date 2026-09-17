<?php

declare(strict_types=1);

namespace App\Services\Api;

use Illuminate\Http\Client\Client;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**

Centralized API client with error handling, retries, and timeouts
*/
class ApiClient +{
private const DEFAULT_TIMEOUT = 30;

private const RETRY_ATTEMPTS = 3;

private const RETRY_DELAY = 1000; // ms

private PendingRequest $client;

public function __construct(
  private string $baseUrl = '',
  private int $timeout = self::DEFAULT_TIMEOUT,
  private int $retries = self::RETRY_ATTEMPTS,
) {
  $this->initializeClient();
}

/**
* Initialize HTTP client with sensible defaults
*/
private function initializeClient(): void
{
  $this->client = Http::timeout($this->timeout)
      ->retry($this->retries, $this->retries * self::RETRY_DELAY)
      ->withHeaders([
          'Accept' => 'application/json',
          'Content-Type' => 'application/json',
      ]);
}

/**
* Set authentication token
*/
public function withToken(string $token): self
{
  $this->client = $this->client->withToken($token);

  return $this;
}

/**
* Set base URL
*/
public function withBaseUrl(string $url): self
{
  $this->baseUrl = $url;

  return $this;
}

/**
* GET request
*/
public function get(string $path, array $query = [])
{
  try {
      $url = $this->buildUrl($path);
      $response = $this->client->get($url, $query);

      if ($response->failed()) {
          Log::error('API GET request failed', [
              'url' => $url,
              'status' => $response->status(),
              'body' => $response->body(),
          ]);
          throw new ApiException($response->json()['message'] ?? 'Request failed', $response->status());
      }

      return $response->json();
  } catch (\Exception $e) {
      Log::error('API GET error', ['path' => $path, 'error' => $e->getMessage()]);
      throw $e;
  }
}

/**
* POST request
*/
public function post(string $path, array $data = [])
{
  try {
      $url = $this->buildUrl($path);
      $response = $this->client->post($url, $data);

      if ($response->failed()) {
          Log::error('API POST request failed', [
              'url' => $url,
              'status' => $response->status(),
              'body' => $response->body(),
          ]);
          throw new ApiException($response->json()['message'] ?? 'Request failed', $response->status());
      }

      return $response->json();
  } catch (\Exception $e) {
      Log::error('API POST error', ['path' => $path, 'error' => $e->getMessage()]);
      throw $e;
  }
}

/**
* PATCH request
*/
public function patch(string $path, array $data = [])
{
  try {
      $url = $this->buildUrl($path);
      $response = $this->client->patch($url, $data);

      if ($response->failed()) {
          Log::error('API PATCH request failed', [
              'url' => $url,
              'status' => $response->status(),
              'body' => $response->body(),
          ]);
          throw new ApiException($response->json()['message'] ?? 'Request failed', $response->status());
      }

      return $response->json();
  } catch (\Exception $e) {
      Log::error('API PATCH error', ['path' => $path, 'error' => $e->getMessage()]);
      throw $e;
  }
}

/**
* DELETE request
*/
public function delete(string $path)
{
  try {
      $url = $this->buildUrl($path);
      $response = $this->client->delete($url);

      if ($response->failed()) {
          Log::error('API DELETE request failed', [
              'url' => $url,
              'status' => $response->status(),
              'body' => $response->body(),
          ]);
          throw new ApiException($response->json()['message'] ?? 'Request failed', $response->status());
      }

      return $response->json();
  } catch (\Exception $e) {
      Log::error('API DELETE error', ['path' => $path, 'error' => $e->getMessage()]);
      throw $e;
  }
}

/**
* Build full URL
*/
private function buildUrl(string $path): string
{
  if (empty($this->baseUrl)) {
      return $path;
  }

  return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
}
}