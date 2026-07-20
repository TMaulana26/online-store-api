<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Acl\Transformers\UserResource;
use Modules\Auth\Services\TwoFactorService;

class TwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorService $twoFactorService
    ) {}

    /**
     * Enable 2FA for the authenticated user and return setup details.
     */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if verified first
        if (! $user->hasVerifiedEmail()) {
            return $this->errorResponse('You must verify your email before enabling Two-Factor Authentication.', 403);
        }

        // Output the setup info
        $setupData = $this->twoFactorService->enable($user);

        return $this->successResponse($setupData, 'Two-factor authentication enabled. Please scan the QR code and confirm to activate.');
    }

    /**
     * Confirm the 2FA setup by providing a valid code from the authenticator app.
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $this->twoFactorService->confirm($request->user(), $request->code);

        return $this->successResponse(null, 'Two-factor authentication has been confirmed and is now active.');
    }

    /**
     * Disable 2FA for the authenticated user.
     */
    public function disable(Request $request): JsonResponse
    {
        $this->twoFactorService->disable($request->user());

        return $this->successResponse(null, 'Two-factor authentication has been disabled.');
    }

    /**
     * Complete the login process by verifying the 2FA challenge code.
     */
    public function challenge(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['string', 'nullable'],
            'recovery_code' => ['string', 'nullable'],
            'remember' => ['boolean', 'nullable'],
        ]);

        if (empty($request->code) && empty($request->recovery_code)) {
            return $this->errorResponse('Please provide a 2FA code or a recovery code.', 400);
        }

        $user = $request->user();

        // Verify the code
        if (! $this->twoFactorService->verifyChallenge($user, $request->code, $request->recovery_code)) {
            return $this->errorResponse('The provided two factor authentication code was invalid.', 403);
        }

        // Delete the temporary 2FA token used for this request
        $user->currentAccessToken()->delete();

        // Issue new full access token
        $remember = $request->remember ?? false;

        if ($remember) {
            $expiresAt = now()->addYear();
        } else {
            $expiresAt = now()->addMinutes(config('sanctum.expiration') ?? 60);
        }

        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

        return $this->successResponse([
            'user' => new UserResource($user->load('roles', 'permissions')),
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toDateTimeString(),
        ], 'User logged in successfully.');
    }
}
