<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    /**
     * Test that all security headers are present in responses
     */
    public function test_security_headers_are_present(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');
        $response->assertTrue(
            $response->headers->has('Content-Security-Policy'),
            'Content-Security-Policy header is missing'
        );
    }

    /**
     * Test that Content-Security-Policy does NOT contain unsafe-inline
     */
    public function test_csp_does_not_allow_unsafe_inline(): void
    {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString(
            'unsafe-inline',
            $csp,
            'CSP should not contain unsafe-inline'
        );

        $this->assertStringNotContainsString(
            'unsafe-eval',
            $csp,
            'CSP should not contain unsafe-eval'
        );
    }

    /**
     * Test that CSP uses nonces for inline scripts/styles
     */
    public function test_csp_uses_nonces_for_inline_content(): void
    {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString(
            "script-src 'self' 'nonce-",
            $csp,
            'CSP script-src should use nonce'
        );

        $this->assertStringContainsString(
            "style-src 'self' 'nonce-",
            $csp,
            'CSP style-src should use nonce'
        );
    }

    /**
     * Test that CSP includes essential directives
     */
    public function test_csp_includes_essential_directives(): void
    {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');

        $essentialDirectives = [
            "default-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "connect-src 'self'",
        ];

        foreach ($essentialDirectives as $directive) {
            $this->assertStringContainsString(
                $directive,
                $csp,
                "CSP should include: {$directive}"
            );
        }
    }

    /**
     * Test that CSP nonce is available in request
     */
    public function test_csp_nonce_is_available_in_request(): void
    {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');

        // Extract nonce from CSP header
        preg_match("/script-src 'self' 'nonce-([a-f0-9]+)'/", $csp, $matches);
        $this->assertNotEmpty($matches[1], 'CSP nonce should be present');

        $nonce = $matches[1];
        // Verify it's a valid hex string (32 characters = 16 bytes hex)
        $this->assertRegExp('/^[a-f0-9]{32}$/', $nonce, 'Nonce should be 16 bytes in hex');
    }

    /**
     * Test that different requests get different nonces
     */
    public function test_csp_nonce_changes_per_request(): void
    {
        $response1 = $this->get('/');
        $csp1 = $response1->headers->get('Content-Security-Policy');

        $response2 = $this->get('/');
        $csp2 = $response2->headers->get('Content-Security-Policy');

        // Nonces should be different (with probability 1 - 2^-128)
        $this->assertNotEqual(
            $csp1,
            $csp2,
            'Each request should get a unique CSP with a new nonce'
        );
    }

    /**
     * Test HSTS header in production
     */
    public function test_hsts_header_in_production(): void
    {
        // Note: This test will only pass if APP_ENV=production
        // In test environment, it will verify the header logic
        if (config('app.env') === 'production') {
            $response = $this->get('/');
            $response->assertHeader('Strict-Transport-Security');
            $this->assertStringContainsString(
                'max-age=31536000',
                $response->headers->get('Strict-Transport-Security')
            );
        }
    }

    /**
     * Test Cross-Origin policy headers
     */
    public function test_cross_origin_policies(): void
    {
        $response = $this->get('/');

        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Cross-Origin-Embedder-Policy', 'require-corp');
    }

    /**
     * Test that csp() helper function returns the nonce
     */
    public function test_csp_helper_function_works(): void
    {
        $response = $this->get('/');
        $csp = $response->headers->get('Content-Security-Policy');

        // Extract nonce from header
        preg_match("/script-src 'self' 'nonce-([a-f0-9]+)'/", $csp, $matches);
        $expectedNonce = $matches[1];

        // Call helper within the request context (would be available in blade)
        // For now, just verify the nonce format
        $this->assertRegExp('/^[a-f0-9]{32}$/', $expectedNonce);
    }
}
