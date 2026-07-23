<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ConfirmTwoFactorRequest;
use App\Http\Requests\Auth\DisableTwoFactorRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\VerifyTwoFactorRequest;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['E-posta veya şifre hatalı.'],
            ]);
        }

        if (config('features.two_factor_enabled') && $user->hasTwoFactorEnabled()) {
            $challengeToken = Str::random(64);

            Cache::put(
                $this->challengeCacheKey($challengeToken),
                $user->id,
                now()->addMinutes(5)
            );

            return response()->json([
                'requires_2fa' => true,
                'challenge_token' => $challengeToken,
            ]);
        }

        return $this->issueTokenResponse($user);
    }

    public function verifyTwoFactor(VerifyTwoFactorRequest $request): JsonResponse
    {
        if (! config('features.two_factor_enabled')) {
            abort(404);
        }
        $data = $request->validated();
        $cacheKey = $this->challengeCacheKey($data['challenge_token']);
        $userId = Cache::get($cacheKey);

        if (! $userId) {
            throw ValidationException::withMessages([
                'code' => ['Doğrulama süresi doldu. Lütfen tekrar giriş yapın.'],
            ]);
        }

        $user = User::query()->find($userId);

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            Cache::forget($cacheKey);
            throw ValidationException::withMessages([
                'code' => ['Doğrulama geçersiz. Lütfen tekrar giriş yapın.'],
            ]);
        }

        $secret = $this->twoFactor->decryptSecret($user->two_factor_secret);
        $code = trim($data['code']);
        $valid = false;

        if ($secret && preg_match('/^\d{6}$/', $code)) {
            $valid = $this->twoFactor->verify($secret, $code);
        }

        if (! $valid) {
            $remaining = $this->twoFactor->useRecoveryCode($user, $code);

            if ($remaining === null) {
                throw ValidationException::withMessages([
                    'code' => ['Kod hatalı.'],
                ]);
            }

            $user->forceFill([
                'two_factor_recovery_codes' => $this->twoFactor->encryptRecoveryCodes($remaining),
            ])->save();
        }

        Cache::forget($cacheKey);

        return $this->issueTokenResponse($user);
    }

    public function twoFactorStatus(Request $request): JsonResponse
    {
        if (! config('features.two_factor_enabled')) {
            abort(404);
        }

        $user = $request->user();

        return response()->json([
            'enabled' => $user->hasTwoFactorEnabled(),
            'confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
        ]);
    }

    public function enableTwoFactor(Request $request): JsonResponse
    {
        if (! config('features.two_factor_enabled')) {
            abort(404);
        }

        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => 'İki adımlı doğrulama zaten açık.',
            ], 422);
        }

        $secret = $this->twoFactor->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $this->twoFactor->encryptSecret($secret),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json([
            'secret' => $secret,
            'qr_svg' => $this->twoFactor->qrCodeSvg($user, $secret),
        ]);
    }

    public function confirmTwoFactor(ConfirmTwoFactorRequest $request): JsonResponse
    {
        if (! config('features.two_factor_enabled')) {
            abort(404);
        }

        $user = $request->user();
        $secret = $this->twoFactor->decryptSecret($user->two_factor_secret);

        if (! $secret || $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'code' => ['Önce iki adımlı doğrulamayı başlatın.'],
            ]);
        }

        if (! $this->twoFactor->verify($secret, $request->validated('code'))) {
            throw ValidationException::withMessages([
                'code' => ['Kod hatalı. Authenticator uygulamanızdaki kodu kontrol edin.'],
            ]);
        }

        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $this->twoFactor->encryptRecoveryCodes($recoveryCodes),
        ])->save();

        return response()->json([
            'message' => 'İki adımlı doğrulama etkinleştirildi.',
            'recovery_codes' => $recoveryCodes,
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function disableTwoFactor(DisableTwoFactorRequest $request): JsonResponse
    {
        if (! config('features.two_factor_enabled')) {
            abort(404);
        }

        $user = $request->user();

        if (! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Şifre hatalı.'],
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json([
            'message' => 'İki adımlı doğrulama kapatıldı.',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Çıkış yapıldı.',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->validated('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mevcut şifre hatalı.'],
            ]);
        }

        $user->update([
            'password' => $request->validated('password'),
        ]);

        $currentTokenId = $user->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->json([
            'message' => 'Şifre başarıyla güncellendi.',
        ]);
    }

    private function issueTokenResponse(User $user): JsonResponse
    {
        $user->tokens()->delete();

        $expiresAt = now()->addMinutes((int) config('sanctum.expiration', 480));
        $token = $user->createToken('crm-token', ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'requires_2fa' => false,
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => $this->userPayload($user),
        ]);
    }

    private function challengeCacheKey(string $token): string
    {
        return '2fa_challenge:'.$token;
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'customer_id' => $user->customer_id,
            'two_factor_enabled' => config('features.two_factor_enabled')
                && $user->hasTwoFactorEnabled(),
            'preferences' => $user->reminderPreferences(),
            'permissions' => $user->permissionSlugs(),
        ];
    }
}
