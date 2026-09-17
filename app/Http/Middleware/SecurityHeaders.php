<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds strict security headers to all responses with CSP using nonces
 *
 * CSP Configuration:
 * - Uses cryptographic nonces for inline scripts/styles (no unsafe-inline)
 * - Nonce is generated once per request and stored in the request container
 * - Available in Blade templates via: @csp() helper or request()->attributes->get('csp_nonce')
 *
 * Usage in Blade templates:
 *   - script tag with nonce attribute
 *   - Inline JavaScript
 *   - end script tag
 *
 *   - style tag with nonce attribute
 *   - Inline CSS
 *   - end style tag
 *
 * For inline event handlers, use data attributes or addEventListener instead:
 *   - button tag with data-action attribute
 *   - script tag with nonce attribute
 *   - document.querySelector code and addEventListener call
 *   - end script tag
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generate a cryptographically secure nonce for this request
        $nonce = bin2hex(random_bytes(16));

        // Store nonce in request for access in views/services
        $request->attributes->set('csp_nonce', $nonce);

        // Tell Laravel Vite to use the CSP nonce on all generated tags
        \Illuminate\Support\Facades\Vite::useCspNonce($nonce);

        $response = $next($request);

        // Prevent clickjacking - restrict framing to same origin
        $response->header('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME type sniffing
        $response->header('X-Content-Type-Options', 'nosniff');

        // XSS protection (legacy, browser default, kept for older browsers)
        $response->header('X-XSS-Protection', '1; mode=block');

        // Referrer policy - send referrer only when navigating to same origin
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Build strict Content Security Policy with nonces
        $csp = $this->buildContentSecurityPolicy($nonce, $request);
        $response->header('Content-Security-Policy', $csp);

        // Report-Only header for development/testing (optional)
        if (config('app.debug')) {
            // Uncomment to enable CSP violations reporting to a logger/service
            // $response->header('Content-Security-Policy-Report-Only', $csp);
        }

        // Restrict feature access (geolocation, microphone, camera, etc.)
        $response->header('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=(), usb=()');

        // Strict Transport Security - enforce HTTPS in production when on HTTPS
        if (config('app.env') === 'production' && ($request->isSecure() || str_starts_with((string) config('app.url'), 'https://'))) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Cross-Origin policies
        $isLocalHost = in_array($request->getHost(), ['127.0.0.1', 'localhost', '::1'], true);
        $response->header('Cross-Origin-Opener-Policy', 'same-origin');
        $response->header('Cross-Origin-Embedder-Policy', ($isLocalHost || config('app.env') === 'local') ? 'credentialless' : 'require-corp');

        return $response;
    }

    /**
     * Build a strict CSP that disallows unsafe-inline and uses nonces.
     *
     * Directives configured:
     * - default-src set to self (all content defaults to same-origin only)
     * - script-src set to self with nonce (only self and nonce-protected inline scripts)
     * - style-src set to self with nonce (only self and nonce-protected inline styles)
     * - img-src allows self, data URIs, and HTTPS
     * - font-src allows self and data URIs (for @font-face)
     * - connect-src set to self (API connections to same origin only)
     * - media-src set to self (audio/video from same origin)
     * - object-src disabled (no plugins)
     * - base-uri set to self (restricted to same origin)
     * - form-action set to self (form submissions to same origin only)
     * - frame-ancestors set to self (allow embedding in same-origin frames only)
     * - upgrade-insecure-requests enabled (auto-upgrade HTTP to HTTPS in production)
     * - block-all-mixed-content enabled (prevent mixed HTTP and HTTPS content in production)
     *
     * In local development, the CSP is relaxed to allow the Vite dev server
     * (running on a separate port, e.g. http://localhost:5173) and inline scripts
     * that Vite injects for HMR. Production keeps the strict policy.
     */
    private function buildContentSecurityPolicy(string $nonce, Request $request): string
    {
        // In local development, allow Vite dev server origins and unsafe-inline.
        // NOTE: Per CSP spec, if a nonce is present alongside 'unsafe-inline', the browser
        // ignores 'unsafe-inline'. So in local dev we omit the nonce entirely so that
        // 'unsafe-inline' takes effect (Vite injects inline scripts/styles for HMR).
        if (config('app.env') === 'local') {
            $viteOrigins = ['http://localhost:5173', 'http://0.0.0.0:5173', 'http://127.0.0.1:5173'];

            $directives = [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' ".implode(' ', $viteOrigins),
                "style-src 'self' 'unsafe-inline' ".implode(' ', $viteOrigins),
                "img-src 'self' data: https: ".implode(' ', $viteOrigins).' http://localhost:8000 http://127.0.0.1:8000',
                "font-src 'self' data: ".implode(' ', $viteOrigins),
                "connect-src 'self' ".implode(' ', $viteOrigins).' ws://localhost:5173 ws://0.0.0.0:5173 ws://127.0.0.1:5173',
                "media-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
                "frame-src 'self'",
                "manifest-src 'self'",
                "worker-src 'self' blob:",
            ];

            return implode('; ', $directives).';';
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        $localOrigins = ['http://localhost:8000', 'http://127.0.0.1:8000', 'http://localhost', 'http://127.0.0.1'];
        if ($appUrl && ! in_array($appUrl, $localOrigins, true)) {
            $localOrigins[] = $appUrl;
        }
        $localOriginsStr = implode(' ', array_unique(array_filter($localOrigins)));

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline' 'nonce-{$nonce}'",
            "img-src 'self' data: https: blob: {$localOriginsStr}",
            "font-src 'self' data:",
            "connect-src 'self' {$localOriginsStr}",
            "media-src 'self' {$localOriginsStr}",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "frame-src 'self'",
            "manifest-src 'self'",
            "worker-src 'self' blob:",
        ];

        // Only in production over HTTPS: enforce HTTPS and block mixed content
        if (config('app.env') === 'production' && ($request->isSecure() || str_starts_with((string) config('app.url'), 'https://'))) {
            $directives[] = 'upgrade-insecure-requests';
            $directives[] = 'block-all-mixed-content';
        }

        return implode('; ', $directives).';';
    }
}
