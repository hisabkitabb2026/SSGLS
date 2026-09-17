<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class CompanyMiddleware
{
    /**
     * Cache key for the Schema::hasTable check.
     *
     * The table existence check is expensive (runs a SHOW TABLES query on every
     * request) and the result never changes during a request lifecycle.  We
     * cache it for 1 hour to eliminate the repeated database round-trip.
     */
    private const TABLE_CACHE_KEY = 'middleware:has_user_company_table';

    private const TABLE_CACHE_TTL = 3600; // 1 hour in seconds

    public function handle(Request $request, Closure $next): Response
    {
        $hasUserCompanyTable = Cache::remember(
            self::TABLE_CACHE_KEY,
            self::TABLE_CACHE_TTL,
            fn () => Schema::hasTable('user_company')
        );

        if ($hasUserCompanyTable) {
            $user = $request->user();

            if (! $user) {
                return $next($request);
            }

            $firstCompany = $user->companies()->first();

            // User has no companies — allow request through without company header
            if (! $firstCompany) {
                return $next($request);
            }

            // Super admin without company header — allow pass-through (admin mode)
            if ($user->isSuperAdmin() && ! $request->header('company')) {
                return $next($request);
            }

            if (! $request->header('company') || ! $user->hasCompany($request->header('company'))) {
                $request->headers->set('company', $firstCompany->id);
            }
        }

        return $next($request);
    }
}
