# Security Headers Configuration

This document describes the strict Content Security Policy (CSP) and other security headers implemented in InvoiceShelf.

## Overview

InvoiceShelf implements a **strict Content Security Policy without `unsafe-inline`**, eliminating one of the most common XSS vectors. All inline scripts and styles must be explicitly nonce-protected.

## Architecture

The `SecurityHeaders` middleware in `app/Http/Middleware/SecurityHeaders.php`:

1. **Generates a unique cryptographic nonce** (16 random bytes, hex-encoded) per request
2. **Stores the nonce** in the request attributes container for access in views
3. **Adds multiple security headers** to every HTTP response

### Generated Headers

| Header | Purpose |
|--------|---------|
| `X-Frame-Options` | Prevents clickjacking (allow same-origin framing) |
| `X-Content-Type-Options` | Prevents MIME type sniffing |
| `X-XSS-Protection` | Legacy XSS protection (older browsers) |
| `Referrer-Policy` | Controls referrer information in cross-origin requests |
| `Content-Security-Policy` | Strict CSP with nonce-based inline script/style allowlisting |
| `Permissions-Policy` | Restricts browser features (geolocation, camera, microphone, etc.) |
| `Strict-Transport-Security` | Enforces HTTPS (production only) |
| `Cross-Origin-Opener-Policy` | Isolates window context (production) |
| `Cross-Origin-Embedder-Policy` | Requires CORP for cross-origin resources (production) |

## Content Security Policy (CSP)

### Policy Directives

```
default-src 'self'                    → All content defaults to same-origin
script-src 'self' 'nonce-<random>'   → Scripts: self + nonce-protected inline
style-src 'self' 'nonce-<random>'    → Styles: self + nonce-protected inline
img-src 'self' data: https:           → Images: self, data URIs, HTTPS
font-src 'self' data:                 → Fonts: self, data URIs
connect-src 'self'                    → API calls: same-origin only
media-src 'self'                      → Audio/video: same-origin
object-src 'none'                     → Plugins (Flash): disabled
base-uri 'self'                       → <base> tag: same-origin only
form-action 'self'                    → Form submissions: same-origin
frame-ancestors 'self'                → Embedding: same-origin iframes only
frame-src 'self'                      → Iframes: same-origin only
manifest-src 'self'                   → Web manifest: same-origin
worker-src 'self'                     → Web/Service Workers: same-origin
upgrade-insecure-requests             → Auto-upgrade HTTP → HTTPS (production)
block-all-mixed-content               → Block HTTP in HTTPS pages (production)
```

**Key Restrictions:**
- ✅ **No `unsafe-inline`** — all inline scripts/styles must use nonces
- ✅ **No `unsafe-eval`** — eval() and new Function() blocked
- ✅ **No external scripts** — only self-hosted + nonce-protected inline
- ✅ **No data URIs in scripts** — prevents data: protocol exploitation
- ✅ **No plugins** — `object-src 'none'` disables Flash, Java, etc.

## Using CSP Nonces in Templates

### In Blade Templates

Use the `@csp()` helper to include the nonce in inline scripts:

```blade
{{-- Inline script with nonce --}}
<script nonce="{{ @csp() }}">
  console.log('This is safe!');
  window.apiUrl = '{{ route('api.endpoint') }}';
</script>

{{-- Inline style with nonce --}}
<style nonce="{{ @csp() }}">
  .custom-class {
    color: blue;
  }
</style>

{{-- Or use the request helper --}}
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
  // inline JavaScript
</script>
```

### Event Handler Patterns

**CSP blocks inline event handlers** (`onclick="..."`, `onload="..."`). Instead, use:

```blade
{{-- ❌ BLOCKED by CSP --}}
<button onclick="handleClick()">Click me</button>

{{-- ✅ ALLOWED: data attribute + addEventListener --}}
<button type="button" data-action="click-handler">Click me</button>
<script nonce="{{ @csp() }}">
  document.querySelector('[data-action="click-handler"]').addEventListener('click', function() {
    // handle click
  });
</script>

{{-- ✅ ALLOWED: v-on directive in Vue (no inline handlers) --}}
<button @click="handleClick">Click me</button>
```

### External Scripts

Link external scripts normally — they must be same-origin:

```blade
{{-- Same-origin external script: ✅ ALLOWED --}}
<script src="{{ asset('js/app.js') }}"></script>

{{-- Cross-origin external script: ❌ BLOCKED --}}
<script src="https://cdn.example.com/lib.js"></script>
```

**To use third-party libraries:**
1. Host them locally in `public/` directory, or
2. Vendor them into your build process (recommended), or
3. Add `'self'` + specific `https:` domains to `script-src` in the middleware (not recommended)

### Vue 3 / TypeScript

Vue components don't need nonces — they're compiled and bundled. Only use nonces for:
- Inline `<script>` blocks in `.blade.php` files
- Server-rendered JavaScript
- Configuration passed from server to client

```vue
{{-- ❌ Don't do this in Vue components --}}
<script>
  // This is already compiled, no nonce needed
  export default { ... }
</script>
```

```blade
{{-- ✅ Do this in Blade to pass config to Vue --}}
<script nonce="{{ @csp() }}">
  window.__CONFIG__ = {
    apiUrl: '{{ route('api.base') }}',
    userId: {{ auth()->id() }},
  };
</script>
```

## Monitoring CSP Violations

### Enable Report-Only Mode (Development)

To test CSP without blocking violations, use report-only mode in development:

```php
// In SecurityHeaders.php handle() method, uncomment:
if (config('app.debug')) {
    $response->header('Content-Security-Policy-Report-Only', $csp);
}
```

This header reports violations without blocking them, useful for testing.

### Parse CSP Violation Reports

When violations occur, the browser can report them to a logging endpoint:

```php
// Add to SecurityHeaders CSP directives:
$directives[] = "report-uri https://yourapp.com/api/csp-violations";
```

Implement an endpoint to log violations:

```php
// routes/api.php
Route::post('/csp-violations', function (Request $request) {
    Log::warning('CSP Violation', $request->all());
    return response()->noContent();
})->withoutMiddleware('auth:sanctum');
```

## Troubleshooting

### "Refused to execute inline script" Error

**Cause:** An inline script is missing a nonce.

**Fix:** Add `nonce="{{ @csp() }}"` to the `<script>` tag:

```blade
{{-- Before (blocked) --}}
<script>
  console.log('Hello');
</script>

{{-- After (allowed) --}}
<script nonce="{{ @csp() }}">
  console.log('Hello');
</script>
```

### "Refused to apply inline style"

**Cause:** An inline style is missing a nonce.

**Fix:** Add `nonce="{{ @csp() }}"` to the `<style>` tag:

```blade
<style nonce="{{ @csp() }}">
  body { color: blue; }
</style>
```

### "Refused to load the script from..." (External Script)

**Cause:** External script is from a different origin.

**Fix:** Host the script locally or update CSP. Example:

```php
// In buildContentSecurityPolicy(), add:
// "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net",

// Then use:
// <script src="https://cdn.jsdelivr.net/npm/lib@1.0.0/dist/lib.min.js"></script>
```

### Form Submission Blocked

**Cause:** Form action targets a different origin.

**Fix:** `form-action 'self'` restricts form submissions. Update in middleware:

```php
// In buildContentSecurityPolicy(), replace:
// "form-action 'self'",
// with:
// "form-action 'self' https://external-endpoint.com",
```

## Best Practices

1. **Never use `unsafe-inline`** — defeats the purpose of CSP
2. **Never use `unsafe-eval`** — is already blocked
3. **Always nonce inline scripts/styles** — keep the helper at hand
4. **Host third-party assets locally** — reduces external dependencies
5. **Test in development** — use `Content-Security-Policy-Report-Only` before enforcing
6. **Monitor production** — enable violation reporting and logging
7. **Use semantic CSP directives** — `script-src` not just `default-src`
8. **Regenerate nonce per request** — don't cache it
9. **Use `data:` only for images/fonts** — never for scripts
10. **Keep directives minimal** — only allow what's needed

## Further Reading

- [MDN: Content Security Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP)
- [OWASP: CSP Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html)
- [CSP Validator](https://csp-evaluator.withgoogle.com)
- [Content Security Policy Reference](https://content-security-policy.com/)
