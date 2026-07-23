<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(
        private readonly Google2FA $google2fa = new Google2FA,
    ) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $company = config('app.name', 'NeuEmlakCRM');
        $otpauth = $this->google2fa->getQRCodeUrl($company, $user->email, $secret);

        $renderer = new ImageRenderer(
            new RendererStyle(192),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($otpauth);
    }

    public function encryptSecret(string $secret): string
    {
        return Crypt::encryptString($secret);
    }

    public function decryptSecret(?string $encrypted): ?string
    {
        if (! $encrypted) {
            return null;
        }

        return Crypt::decryptString($encrypted);
    }

    public function verify(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code, 1);
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return Collection::times($count, fn () => Str::lower(Str::random(10)))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $codes
     */
    public function encryptRecoveryCodes(array $codes): string
    {
        return Crypt::encryptString(json_encode(array_values($codes)));
    }

    /**
     * @return list<string>
     */
    public function decryptRecoveryCodes(?string $encrypted): array
    {
        if (! $encrypted) {
            return [];
        }

        $decoded = json_decode(Crypt::decryptString($encrypted), true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * @return list<string>|null  Remaining codes after use, or null if code invalid
     */
    public function useRecoveryCode(User $user, string $code): ?array
    {
        $codes = $this->decryptRecoveryCodes($user->two_factor_recovery_codes);
        $normalized = Str::lower(trim($code));

        if (! in_array($normalized, $codes, true)) {
            return null;
        }

        return array_values(array_filter(
            $codes,
            fn (string $item) => $item !== $normalized
        ));
    }
}
