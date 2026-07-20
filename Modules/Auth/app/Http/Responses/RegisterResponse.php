<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Modules\Acl\Transformers\UserResource;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
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

        $expiresAt = now()->addMinutes(config('sanctum.expiration') ?? 60);
        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully.',
            'data' => [
                'user' => new UserResource($user->load('roles', 'permissions')),
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toDateTimeString(),
            ],
            'errors' => null,
        ], 201);
    }
}
