<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Modules\Acl\Transformers\UserResource;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        $user = $request->user();

        $expiresAt = now()->addMinutes(config('sanctum.expiration', 60));
        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User logged in successfully.',
            'data' => [
                'user' => new UserResource($user->load('roles', 'permissions')),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toDateTimeString(),
            ],
            'errors' => null,
        ]);
    }
}
