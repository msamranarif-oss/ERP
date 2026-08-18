<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user = auth()->user();

        if (!$user->tenant_id) {
            return response()->json(['message' => 'No tenant associated with this user'], 403);
        }

        if (!$user->tenant || !$user->tenant->isActive()) {
            return response()->json(['message' => 'Tenant is inactive or not found'], 403);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'User account is inactive'], 403);
        }

        // Set tenant context for the request
        $request->attributes->set('tenant_id', $user->tenant_id);

        return $next($request);
    }
}
