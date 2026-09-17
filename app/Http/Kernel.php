<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CompanyMiddleware;
use App\Http\Middleware\CustomerGuest;
use App\Http\Middleware\CustomerPortalMiddleware;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\InstallationMiddleware;
use App\Http\Middleware\PdfMiddleware;
use App\Http\Middleware\PreventRequestForgery;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RedirectIfInstalled;
use App\Http\Middleware\RequestIdMiddleware;
use App\Http\Middleware\ScopeBouncer;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SuperAdminMiddleware;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\ValidateSignature;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        CheckForMaintenanceMode::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
        TrustProxies::class,
        RequestIdMiddleware::class, // Generate and track request IDs
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
            SubstituteBindings::class,
            SecurityHeaders::class,
        ],

        'api' => [
            EnsureFrontendRequestsAreStateful::class,
            ThrottleRequests::class.':api',
            SubstituteBindings::class,
            SecurityHeaders::class,
        ],
    ];

    /**
     * The application's middleware aliases.
     *
     * Aliases may be used instead of class names to reference middleware in routes and controller methods.
     *
     * @var array<string, class-string|string>
     */
    protected $routeMiddleware = [
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'auth.session' => AuthenticateSession::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'guest' => RedirectIfAuthenticated::class,
        'password.confirm' => RequirePassword::class,
        'signed' => ValidateSignature::class,
        'throttle' => ThrottleRequests::class,
        'verified' => EnsureEmailIsVerified::class,
        'company' => CompanyMiddleware::class,
        'bouncer' => ScopeBouncer::class,
        'super-admin' => SuperAdminMiddleware::class,
        'redirect-if-installed' => RedirectIfInstalled::class,
        'installation' => InstallationMiddleware::class,
        'customer-portal' => CustomerPortalMiddleware::class,
        'customer-guest' => CustomerGuest::class,
        'pdf' => PdfMiddleware::class,
        'request-id' => RequestIdMiddleware::class,
    ];
}
