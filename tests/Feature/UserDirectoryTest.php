<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users_paginated(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        User::factory(3)->create();

        $this->getJson('/api/users?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'role']], 'links', 'meta']);
    }

    public function test_service_account_can_list_and_show_users(): void
    {
        Sanctum::actingAs(User::factory()->service()->create());
        $other = User::factory()->create();

        $this->getJson('/api/users')->assertOk();
        $this->getJson("/api/users/{$other->id}")->assertOk()->assertJsonPath('data.id', $other->id);
    }

    public function test_staff_cannot_list_users(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_staff_can_view_self_but_not_others(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($me);

        $this->getJson("/api/users/{$me->id}")->assertOk();
        $this->getJson("/api/users/{$other->id}")->assertForbidden();
    }

    public function test_unknown_user_is_404(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/users/9999')->assertNotFound();
    }

    public function test_directory_requires_auth(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
    }

    public function test_user_create_and_token_commands(): void
    {
        $this->artisan('user:create', ['email' => 'boss@example.com', '--role' => 'admin', '--password' => 'secret-password'])
            ->expectsOutputToContain('Created admin user')
            ->assertSuccessful();

        $this->assertTrue(User::firstWhere('email', 'boss@example.com')->isAdmin());

        $this->artisan('user:create', ['email' => 'x@example.com', '--role' => 'wizard'])->assertFailed();
        $this->artisan('user:create', ['email' => 'boss@example.com'])->assertFailed();
        $this->artisan('user:token', ['email' => 'boss@example.com'])->assertSuccessful();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
