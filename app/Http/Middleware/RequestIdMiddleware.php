<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Get existing trace ID from header or generate new one
        $traceId = $request->header('X-Trace-ID') ?: str()->uuid();

        // Store in request for access throughout lifecycle
        $request->attributes->set('trace_id', $traceId);

        // Add to request headers so it's available everywhere
        $request->headers->set('X-Trace-ID', $traceId);

        // Process request
        $response = $next($request);

        // Add trace ID to response headers
        $response->headers->set('X-Trace-ID', $traceId);

        return $response;
    }
}
