<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        if (!$user || !$user->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant context required',
            ], 401);
        }

        // Set tenant context for the request
        $request->attributes->set('tenant_id', $user->tenant_id);
        
        return $next($request);
    }
}