<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Modules\Acl\Models\User;

test('guest can register a new user', function () {
    Notification::fake();

    $payload = [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $this->postJson('/api/v1/auth/register', $payload)
        ->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => ['id', 'name', 'email'],
                'access_token',
                'token_type',
                'expires_at',
            ],
        ]);

    $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
});

test('user registration fails on validation errors', function () {
    $payload = [
        'name' => '',
        'email' => 'not-an-email',
        'password' => '123',
        'password_confirmation' => 'abc',
    ];

    $this->postJson('/api/v1/auth/register', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

test('user can login successfully', function () {
    $user = User::factory()->create([
        'email' => 'login@example.com',
        'password' => Hash::make('password123'),
        'is_active' => true,
    ]);

    $payload = [
        'email' => 'login@example.com',
        'password' => 'password123',
    ];

    $this->postJson('/api/v1/auth/login', $payload)
        ->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => ['id', 'name', 'email'],
                'access_token',
                'token_type',
                'expires_at',
            ],
        ]);
});

test('user cannot login with invalid credentials', function () {
    User::factory()->create([
        'email' => 'wrong@example.com',
        'password' => Hash::make('password123'),
    ]);

    $payload = [
        'email' => 'wrong@example.com',
        'password' => 'wrongpassword',
    ];

    $this->postJson('/api/v1/auth/login', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('inactive user cannot login', function () {
    User::factory()->create([
        'email' => 'inactive@example.com',
        'password' => Hash::make('password123'),
        'is_active' => false,
    ]);

    $payload = [
        'email' => 'inactive@example.com',
        'password' => 'password123',
    ];

    $this->postJson('/api/v1/auth/login', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('authenticated user can retrieve profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/auth/user')
        ->assertStatus(200)
        ->assertJsonPath('data.user.id', $user->id);
});

test('guest cannot retrieve profile', function () {
    $this->getJson('/api/v1/auth/user')
        ->assertStatus(401);
});

test('authenticated user can refresh token', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/refresh')
        ->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user',
                'access_token',
                'token_type',
                'expires_at',
            ],
        ]);
});

test('guest cannot refresh token', function () {
    $this->postJson('/api/v1/auth/refresh')
        ->assertStatus(401);
});

test('authenticated user can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/logout')
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('guest cannot logout', function () {
    $this->postJson('/api/v1/auth/logout')
        ->assertStatus(401);
});

test('guest can request password reset link', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'reset@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset@example.com'])
        ->assertStatus(200)
        ->assertJsonPath('success', true);
});

test('guest can reset password using token', function () {
    $user = User::factory()->create([
        'email' => 'resetme@example.com',
        'password' => Hash::make('oldpassword'),
    ]);

    $token = Password::createToken($user);

    $payload = [
        'email' => 'resetme@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
        'token' => $token,
    ];

    $this->postJson('/api/v1/auth/reset-password', $payload)
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
});
