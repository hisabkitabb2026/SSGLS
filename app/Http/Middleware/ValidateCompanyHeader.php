<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**

Validates that required company header is present and valid
*/
class ValidateCompanyHeader +{
public function handle(Request $request, Closure $next): Response
{
  $companyId = $request->header('company');

  if (! $companyId) {
      return response()->json([
          'success' => false,
          'message' => 'Company header is required',
      ], 400);
  }

  if (! is_numeric($companyId)) {
      return response()->json([
          'success' => false,
          'message' => 'Company header must be numeric',
      ], 400);
  }

  return $next($request);
}
}