# CSP Migration Guide - From unsafe-inline to Nonces

This guide helps developers migrate inline scripts and styles to comply with the new strict Content Security Policy (CSP).

## The Change

**Before:** CSP allowed `unsafe-inline` for scripts and styles
```
script-src 'self' 'unsafe-inline';
style-src 'self' 'unsafe-inline';
```

**After:** CSP uses cryptographic nonces, no `unsafe-inline`
```
script-src 'self' 'nonce-a1b2c3d4e5f6...';
style-src 'self' 'nonce-a1b2c3d4e5f6...';
```

## Why This Change?

1. **Blocks XSS attacks** — `unsafe-inline` allows attackers to inject scripts via DOM manipulation
2. **Industry standard** — Nonce-based CSP is recommended by OWASP and NIST
3. **Zero performance impact** — Nonces are transparent to users
4. **Backwards compatible** — Old browsers fall back to `'self'`, new ones enforce nonces

## Migration Steps

### 1. Update Inline Scripts

**Pattern 1: Simple inline script**

```blade
<!-- Before -->
<script>
  console.log('Hello world');
</script>

<!-- After -->
<script nonce="{{ @csp() }}">
  console.log('Hello world');
</script>
```

**Pattern 2: Script with blade variables**

```blade
<!-- Before -->
<script>
  window.userId = {{ auth()->id() }};
  window.apiUrl = '{{ route('api.base') }}';
</script>

<!-- After -->
<script nonce="{{ @csp() }}">
  window.userId = {{ auth()->id() }};
  window.apiUrl = '{{ route('api.base') }}';
</script>
```

**Pattern 3: IIFE for scope isolation**

```blade
<!-- Before -->
<script>
  (function() {
    const privateVar = 'secret';
    window.publicVar = 'public';
  })();
</script>

<!-- After -->
<script nonce="{{ @csp() }}">
  (function() {
    const privateVar = 'secret';
    window.publicVar = 'public';
  })();
</script>
```

### 2. Update Inline Styles

```blade
<!-- Before -->
<style>
  .custom {
    color: blue;
  }
</style>

<!-- After -->
<style nonce="{{ @csp() }}">
  .custom {
    color: blue;
  }
</style>
```

### 3. Update Event Handlers

CSP **blocks inline event handlers**. Use `addEventListener` instead:

```blade
<!-- ❌ BLOCKED: inline onclick -->
<button onclick="handleClick()">Click me</button>

<!-- ✅ ALLOWED: data attribute + addEventListener -->
<button type="button" data-action="my-button">Click me</button>
<script nonce="{{ @csp() }}">
  document.querySelector('[data-action="my-button"]').addEventListener('click', function() {
    handleClick();
  });
</script>
```

**For Vue components, use v-on:**

```vue
<!-- Vue: use v-on directive -->
<button @click="handleClick">Click me</button>

<script setup>
const handleClick = () => {
  // handler
};
</script>
```

### 4. Update External Scripts

External scripts from same origin: ✅ **allowed**
```blade
<script src="{{ asset('js/app.js') }}"></script>
```

External scripts from different origin: ❌ **blocked** (requires CSP update)
```blade
<!-- ❌ BLOCKED: cross-origin script -->
<script src="https://cdn.example.com/lib.js"></script>
```

**Solution:** Host third-party assets locally

```bash
# Copy library to public/js/vendor/
cp node_modules/moment/moment.min.js public/js/vendor/
```

```blade
<!-- ✅ ALLOWED: same-origin -->
<script src="{{ asset('js/vendor/moment.min.js') }}"></script>
```

Or update CSP in middleware (not recommended):

```php
// app/Http/Middleware/SecurityHeaders.php - only if necessary
"script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net",
```

### 5. Update Form Handlers

**Pattern: Form with custom submission**

```blade
<!-- Before -->
<form onsubmit="return validateForm()">
  <input type="text" name="email" />
  <button>Submit</button>
</form>
<script>
  function validateForm() {
    // validation logic
  }
</script>

<!-- After -->
<form id="my-form" novalidate>
  <input type="text" name="email" />
  <button>Submit</button>
</form>
<script nonce="{{ @csp() }}">
  document.getElementById('my-form').addEventListener('submit', function(e) {
    if (!validateForm()) {
      e.preventDefault();
    }
  });

  function validateForm() {
    // validation logic
  }
</script>
```

## Common Patterns & Solutions

### Pattern: Configuration Object

```blade
<!-- Before -->
<script>
  window.Config = {
    apiUrl: '{{ config('app.api_url') }}',
    debug: {{ config('app.debug') ? 'true' : 'false' }},
  };
</script>

<!-- After -->
<script nonce="{{ @csp() }}">
  window.Config = {
    apiUrl: '{{ config('app.api_url') }}',
    debug: {{ config('app.debug') ? 'true' : 'false' }},
  };
</script>
```

### Pattern: Data Attributes for Configuration

```blade
<!-- HTML with data attributes -->
<div id="app" data-config="{{ json_encode($config) }}"></div>

<!-- JavaScript reads the data -->
<script nonce="{{ @csp() }}">
  const appElement = document.getElementById('app');
  const config = JSON.parse(appElement.dataset.config);
</script>
```

### Pattern: Multiple Event Listeners

```blade
<button data-action="save">Save</button>
<button data-action="delete">Delete</button>
<button data-action="cancel">Cancel</button>

<script nonce="{{ @csp() }}">
  const actions = {
    save: () => { /* save logic */ },
    delete: () => { /* delete logic */ },
    cancel: () => { /* cancel logic */ },
  };

  document.querySelectorAll('[data-action]').forEach(button => {
    const action = button.dataset.action;
    button.addEventListener('click', actions[action]);
  });
</script>
```

### Pattern: Alpine.js Integration

Alpine.js works great with CSP (no unsafe-inline needed):

```blade
<div x-data="{ open: false }">
  <button @click="open = !open">Toggle</button>
  <div x-show="open">Content</div>
</div>

<!-- Alpine loaded externally or bundled -->
<script src="{{ asset('js/alpine.min.js') }}"></script>
```

### Pattern: Vue 3 Integration

Vue 3 with `<script setup>` doesn't need nonces (compiled before shipping):

```vue
<template>
  <button @click="handleClick">Click me</button>
</template>

<script setup lang="ts">
const handleClick = () => {
  console.log('Clicked');
};
</script>

<style scoped>
button {
  padding: 10px 20px;
}
</style>
```

## Debugging CSP Violations

### Check Browser Console

Look for messages like:
```
Refused to execute inline script because it violates the following Content Security Policy directive: "script-src 'self' 'nonce-a1b2c3...'"
```

### Enable CSP Report-Only (Development)

In `SecurityHeaders.php`, uncomment for testing:

```php
if (config('app.debug')) {
    $response->header('Content-Security-Policy-Report-Only', $csp);
}
```

This reports violations without blocking them.

### Use Browser DevTools

1. Open DevTools → Console
2. Look for CSP violation messages
3. Each message tells you which directive was violated and why
4. Update the relevant `<script>` or `<style>` tag accordingly

### Add report-uri for Production Logging

```php
// In buildContentSecurityPolicy()
$directives[] = "report-uri /api/csp-violations";

// Endpoint in routes/api.php
Route::post('/csp-violations', function (Request $request) {
    Log::warning('CSP Violation', $request->json());
    return response()->noContent();
})->withoutMiddleware('auth:sanctum');
```

## Checklist for CSP Migration

- [ ] All `<script>` blocks have `nonce="{{ @csp() }}"`
- [ ] All `<style>` blocks have `nonce="{{ @csp() }}"`
- [ ] No inline event handlers (`onclick`, `onload`, etc.)
- [ ] External scripts are same-origin or vendor-bundled
- [ ] Form handlers use `addEventListener`
- [ ] Vue components use `@click` not `onclick`
- [ ] No `eval()` or `new Function()` calls
- [ ] No data URIs for scripts
- [ ] Browser console has no CSP violations
- [ ] Tests pass (run with `php artisan test`)

## Need to Allow Cross-Origin Resources?

Update `SecurityHeaders.php`:

```php
// In buildContentSecurityPolicy(), for external scripts:
"script-src 'self' 'nonce-{$nonce}' https://cdn.example.com",

// For external styles:
"style-src 'self' 'nonce-{$nonce}' https://cdn.example.com",

// For external fonts:
"font-src 'self' data: https://fonts.googleapis.com",

// For external images:
"img-src 'self' data: https: https://cdn.example.com",
```

**But first, try hosting assets locally!**

## Support

- **Questions?** Check `/docs/SECURITY_HEADERS.md`
- **CSP Validator:** https://csp-evaluator.withgoogle.com
- **MDN CSP Docs:** https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP
- **OWASP CSP Cheat Sheet:** https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html
