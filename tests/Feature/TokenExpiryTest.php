<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class TokenExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_token_expires_after_configured_lifetime(): void
    {
        config(['sanctum.user_token_expiration' => 60]);
        User::factory()->create(['email' => 'ben@example.com']);

        $token = $this->postJson('/api/login', ['email' => 'ben@example.com', 'password' => 'password'])
            ->json('token');

        $this->assertEqualsWithDelta(
            now()->addMinutes(60)->timestamp,
            PersonalAccessToken::first()->expires_at->timestamp,
            5,
        );

        $this->withToken($token)->getJson('/api/user')->assertOk();

        $this->travel(61)->minutes();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_cli_token_does_not_expire_by_default(): void
    {
        User::factory()->service()->create(['email' => 'svc@example.com']);

        $this->artisan('user:token', ['email' => 'svc@example.com'])->assertSuccessful();

        $this->assertNull(PersonalAccessToken::first()->expires_at);
    }

    public function test_cli_token_expires_option(): void
    {
        User::factory()->create(['email' => 'a@example.com']);

        $this->artisan('user:token', ['email' => 'a@example.com', '--expires' => '120'])->assertSuccessful();
        $this->assertEqualsWithDelta(
            now()->addMinutes(120)->timestamp,
            PersonalAccessToken::first()->expires_at->timestamp,
            5,
        );

        $this->artisan('user:token', ['email' => 'a@example.com', '--expires' => 'soon'])->assertFailed();
        $this->artisan('user:token', ['email' => 'a@example.com', '--expires' => '0'])->assertFailed();
    }
}
