<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Modules\Acl\Models\User;
use Modules\Acl\Transformers\UserResource;
use Modules\Auth\Http\Requests\Auth\ForgotPasswordRequest;
use Modules\Auth\Http\Requests\Auth\LoginRequest;
use Modules\Auth\Http\Requests\Auth\RegisterRequest;
use Modules\Auth\Http\Requests\Auth\ResetPasswordRequest;
use Modules\Auth\Http\Requests\Auth\VerifyEmailRequest;
use Modules\Auth\Services\AuthService;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());
        $result['user'] = new UserResource($result['user']);

        return $this->successResponse($result, 'User registered successfully.', 201);
    }

    /**
     * Log in a user and return a token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        // Wrap user in resource if it's a full login (not a 2fa challenge request)
        if (isset($result['user'])) {
            $result['user'] = new UserResource($result['user']);
        }

        return $this->successResponse($result, 'User logged in successfully.');
    }

    /**
     * Log out the current user by revoking the token.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->successResponse(null, 'User logged out successfully.');
    }

    /**
     * Get the authenticated user's details.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        return $this->successResponse([
            'user' => new UserResource($user->load('roles', 'permissions')),
            'expires_at' => ($token && $token->expires_at) ? $token->expires_at->toDateTimeString() : null,
        ], 'Authenticated user details.');
    }

    /**
     * Refresh the current authenticated user's token.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        // Revoke current token
        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        // Issue new token
        $expiresAt = now()->addMinutes(config('sanctum.expiration') ?? 60);
        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

        return $this->successResponse([
            'user' => new UserResource($user->load('roles', 'permissions')),
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toDateTimeString(),
        ], 'Token refreshed successfully.');
    }

    /**
     * Verify the user's email address.
     *
     * @queryParam expires integer required The expiration timestamp of the verification link.
     * @queryParam signature string required The cryptographic signature validating the link.
     */
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $user = User::withoutGlobalScopes()->findOrFail($request->id);

        if ($request->expires < now()->getTimestamp()) {
            return $this->errorResponse('Verification link has expired.', 403);
        }

        if (! hash_equals((string) $request->hash, sha1($user->getEmailForVerification()))) {
            return $this->errorResponse('Invalid verification link.', 403);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->errorResponse('Email is already verified.', 400);
        }

        if ($this->authService->verifyEmail($user, $request->hash)) {
            return $this->successResponse(null, 'Email verified successfully.');
        }

        return $this->errorResponse('Email could not be verified.', 500);
    }

    /**
     * Resend the email verification notification.
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($this->authService->resendVerificationEmail($user)) {
            return $this->successResponse(null, 'Verification email sent.');
        }

        return $this->errorResponse('Email is already verified.', 400);
    }

    /**
     * Send a reset link to the given user.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = $this->authService->forgotPassword($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? $this->successResponse(null, __($status))
            : $this->errorResponse(__($status), 400);
    }

    /**
     * Reset the user's password.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->authService->resetPassword($request->only(
            'email',
            'password',
            'password_confirmation',
            'token'
        ));

        return $status === Password::PASSWORD_RESET
            ? $this->successResponse(null, __($status))
            : $this->errorResponse(__($status), 400);
    }
}
