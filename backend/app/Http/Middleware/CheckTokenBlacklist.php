<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenBlacklist
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the token from the request
        $token = $request->user()?->currentAccessToken();

        if ($token && Cache::has('token_blacklist_' . $token->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Token has been revoked. Please login again.',
                'error_code' => 'TOKEN_REVOKED',
            ], 401);
        }

        return $next($request);
    }
}
