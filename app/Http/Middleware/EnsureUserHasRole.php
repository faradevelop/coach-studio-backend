<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level role gate: ->middleware('role:coach') or ('role:admin').
 * Responds 403 through the existing HttpExceptionInterface renderer
 * (same ApiResponse envelope as every other API error).
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles, true)) {
            abort(403, 'This action is unauthorized.');
        }

        return $next($request);
    }
}
