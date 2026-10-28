<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_staff_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.role', 'staff')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
    }

    public function test_register_ignores_role_in_input(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Eve',
            'email' => 'eve@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'role' => 'admin',
        ])->assertCreated()->assertJsonPath('user.role', 'staff');

        $this->assertSame('staff', User::firstWhere('email', 'eve@example.com')->role->value);
    }

    public function test_register_validates_input(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/register', [
            'name' => '',
            'email' => 'taken@example.com',
            'password' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_login_returns_token_usable_on_api_user(): void
    {
        $user = User::factory()->create(['email' => 'ben@example.com']);

        $token = $this->postJson('/api/login', ['email' => 'ben@example.com', 'password' => 'password'])
            ->assertOk()
            ->json('token');

        $this->withToken($token)->getJson('/api/user')
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => 'ben@example.com',
                'role' => 'staff',
                'created_at' => $user->created_at->toJSON(),
            ]]);
    }

    public function test_login_rejects_bad_password(): void
    {
        User::factory()->create(['email' => 'ben@example.com']);

        $this->postJson('/api/login', ['email' => 'ben@example.com', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_api_user_requires_token(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
        $this->withToken('garbage')->getJson('/api/user')->assertUnauthorized();
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'ben@example.com']);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/login', ['email' => 'ben@example.com', 'password' => 'wrong']);
        }

        $this->postJson('/api/login', ['email' => 'ben@example.com', 'password' => 'password'])
            ->assertTooManyRequests();
    }
}
