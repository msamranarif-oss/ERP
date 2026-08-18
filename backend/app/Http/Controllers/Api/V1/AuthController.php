<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\LoginAuditLog;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\UpdateProfileRequest;
use App\Http\Requests\Api\V1\Auth\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)
            ->whereNotNull('tenant_id')
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            // Log failed attempt (only if user exists and has a tenant)
            if ($user) {
                LoginAuditLog::create([
                    'user_id'        => $user->id,
                    'tenant_id'      => $user->tenant_id,
                    'event'          => 'failed_login',
                    'ip_address'     => $request->ip(),
                    'user_agent'     => $request->userAgent(),
                    'success'        => false,
                    'failure_reason' => 'bad_password',
                    'occurred_at'    => now(),
                ]);
            }
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        if (!$user->tenant || !$user->tenant->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['Your organization account is inactive.'],
            ]);
        }

        // Update last login
        $user->update(['last_login_at' => now()]);

        // Audit log
        LoginAuditLog::create([
            'user_id'     => $user->id,
            'tenant_id'   => $user->tenant_id,
            'event'       => 'login',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'success'     => true,
            'occurred_at' => now(),
        ]);

        // Revoke old tokens (keep last 5 for concurrent request support)
        $latestTokens = $user->tokens()->orderBy('created_at', 'desc')->limit(5)->get();
        $latestTokenIds = $latestTokens->pluck('id')->toArray();
        
        $user->tokens()
            ->whereNotIn('id', $latestTokenIds)
            ->delete();

        // Create new token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'tenant' => [
                        'id' => $user->tenant->id,
                        'name' => $user->tenant->name,
                        'slug' => $user->tenant->slug,
                        'logo' => $user->tenant->logo,
                    ],
                    'branch' => $user->branch ? [
                        'id' => $user->branch->id,
                        'name' => $user->branch->name,
                    ] : null,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ],
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        LoginAuditLog::create([
            'user_id'     => $user->id,
            'tenant_id'   => $user->tenant_id,
            'event'       => 'logout',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'success'     => true,
            'occurred_at' => now(),
        ]);
        
        // Blacklist current token before deleting
        $token = $user->currentAccessToken();
        if ($token) {
            // Add to blacklist with TTL matching token expiration
            Cache::put('token_blacklist_' . $token->id, true, now()->addHours(12));
            $token->delete();
        }
        
        return response()->json(['success' => true, 'message' => 'Logged out successfully']);
    }

    /**
     * Refresh authentication token
     * Note: This endpoint is now public (no auth middleware)
     * It manually validates the token from the Authorization header
     */
    public function refresh(Request $request): JsonResponse
    {
        // Manually extract and validate token from Authorization header
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'No token provided',
            ], 401);
        }
        
        // Find the token in database - Sanctum handles expired tokens gracefully
        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
        
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token',
            ], 401);
        }
        
        // Get the user associated with the token
        $user = $accessToken->tokenable;
        
        // Verify user still exists and is active
        if (!$user || !$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'User not found or inactive',
            ], 401);
        }
        
        // Delete the old token (rotation)
        $accessToken->delete();
        
        // Create new token
        $newToken = $user->createToken('auth-token')->plainTextToken;
        
        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => [
                'token' => $newToken,
                'expires_in' => config('sanctum.expiration') ?? 720, // minutes
                'expires_at' => now()->addMinutes(config('sanctum.expiration') ?? 720)->toIso8601String(),
            ],
        ]);
    }

    // ── PIN Login (POS Cashiers) ──────────────────────────────────────

    public function pinLogin(Request $request): JsonResponse
    {
        $request->validate([
            'pin'       => 'required|string|min:4|max:6',
            'branch_id' => 'required|exists:branches,id',
        ]);

        $user = User::where('branch_id', $request->branch_id)
                    ->where('has_pos_access', true)
                    ->where('is_active', true)
                    ->whereNotNull('pin')
                    ->get()
                    ->first(fn($u) => Hash::check($request->pin, $u->pin));

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN.'], 401);
        }

        LoginAuditLog::create([
            'user_id'     => $user->id,
            'tenant_id'   => $user->tenant_id,
            'event'       => 'pin_login',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'success'     => true,
            'occurred_at' => now(),
        ]);

        $token = $user->createToken('pos-pin-token', ['pos'])->plainTextToken;
        return response()->json(['success' => true, 'data' => [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ]
        ]]);
    }

    public function setPin(Request $request): JsonResponse
    {
        $request->validate(['pin' => 'required|digits_between:4,6']);
        $request->user()->update(['pin' => Hash::make($request->pin), 'has_pos_access' => true]);
        return response()->json(['success' => true, 'message' => 'PIN set successfully.']);
    }

    public function changePin(Request $request): JsonResponse
    {
        $request->validate([
            'current_pin' => 'required',
            'new_pin'     => 'required|digits_between:4,6',
        ]);
        $user = $request->user();
        if (!$user->pin || !Hash::check($request->current_pin, $user->pin)) {
            return response()->json(['success' => false, 'message' => 'Current PIN is incorrect.'], 422);
        }
        $user->update(['pin' => Hash::make($request->new_pin)]);
        return response()->json(['success' => true, 'message' => 'PIN changed successfully.']);
    }

    // ── Audit Log Listing (admin) ────────────────────────────────────

    public function auditLogs(Request $request): JsonResponse
    {
        $logs = LoginAuditLog::where('tenant_id', auth()->user()->tenant_id)
                              ->with('user:id,name,email')
                              ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
                              ->when($request->event,   fn($q) => $q->where('event', $request->event))
                              ->orderByDesc('occurred_at')
                              ->paginate($request->per_page ?? 25);
        return response()->json(['success' => true, 'data' => $logs]);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['tenant', 'branch']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'tenant' => [
                    'id' => $user->tenant->id,
                    'name' => $user->tenant->name,
                    'slug' => $user->tenant->slug,
                    'logo' => $user->tenant->logo,
                    'settings' => $user->tenant->settings,
                ],
                'branch' => $user->branch ? [
                    'id' => $user->branch->id,
                    'name' => $user->branch->name,
                    'code' => $user->branch->code,
                ] : null,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'settings' => $user->settings,
            ],
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->only(['name', 'phone']);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = $path;
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'name' => $user->name,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
            ],
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Security: Revoke all tokens after password change
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully. Please login again.',
        ]);
    }
}