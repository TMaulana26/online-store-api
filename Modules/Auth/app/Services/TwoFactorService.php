<?php

namespace Modules\Auth\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\RecoveryCode;
use Modules\Acl\Models\User;

class TwoFactorService
{
    public function __construct(
        protected TwoFactorAuthenticationProvider $provider
    ) {}

    /**
     * Enable Two-Factor Authentication for the user.
     * Generates a new secret, recovery codes, and returns the QR code URL.
     */
    public function enable(User $user): array
    {
        $user->forceFill([
            'two_factor_secret' => encrypt($this->provider->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt(json_encode(Collection::times(8, function () {
                return RecoveryCode::generate();
            })->all())),
            // Ensure confirmed_at is null if they are just setting it up
            'two_factor_confirmed_at' => null,
        ])->save();

        return [
            'svg' => $user->twoFactorQrCodeSvg(),
            'url' => $user->twoFactorQrCodeUrl(),
            'secret' => decrypt($user->two_factor_secret),
            'recovery_codes' => $user->recoveryCodes(),
        ];
    }

    /**
     * Confirm the 2FA setup by validating the code provided by the user.
     */
    public function confirm(User $user, string $code): bool
    {
        if (empty($user->two_factor_secret) ||
            empty($code) ||
            ! $this->provider->verify(decrypt($user->two_factor_secret), $code)) {

            throw ValidationException::withMessages([
                'code' => ['The provided two factor authentication code was invalid.'],
            ]);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Disable Two-Factor Authentication.
     */
    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /**
     * Validate a 2FA challenge code or recovery code.
     */
    public function verifyChallenge(User $user, ?string $code, ?string $recoveryCode): bool
    {
        if ($code) {
            return $this->provider->verify(decrypt($user->two_factor_secret), $code);
        }

        if ($recoveryCode) {
            $recoveryCodes = $user->recoveryCodes();

            if (in_array($recoveryCode, $recoveryCodes)) {
                $user->replaceRecoveryCode($recoveryCode);

                return true;
            }
        }

        return false;
    }
}
