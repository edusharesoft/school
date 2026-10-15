<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Audit\AuditLogger;
use App\Support\ApiResponse;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * POST /api/v1/auth/login  { login, password, device_name? }
     * Issues a Sanctum token that is only valid on the host (tenant) it was issued on.
     */
    public function login(LoginRequest $request, TenantContext $tenant, AuditLogger $audit): JsonResponse
    {
        $user = $request->authenticate();

        if ($user->hasTwoFactor()) {
            $otp = (string) $request->input('otp', '');
            if ($otp === '') {
                return $this->fail(__('A two-factor code is required. Send it as "otp".'), 422, ['otp' => [__('The otp field is required.')]]);
            }
            if (! app(\App\Services\Security\TotpService::class)->check($user, $otp)) {
                return $this->fail(__('That code is not correct.'), 422, ['otp' => [__('That code is not correct.')]]);
            }
        }

        $device = substr((string) $request->input('device_name', 'api'), 0, 100) ?: 'api';
        $token = $user->createToken($device, ['tenant:'.$tenant->key()], now()->addDays(30));

        $audit->log('auth.login', $user, [], ['channel' => 'api', 'device' => $device], userId: $user->id);

        return $this->ok([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'tenant' => $tenant->key(),
            'user' => (new UserResource($user->load('roles')))->resolve($request),
        ], __('Signed in.'));
    }

    public function me(Request $request): JsonResponse
    {
        return $this->ok(new UserResource($request->user()->load('roles')));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->ok(null, __('Signed out.'));
    }
}
