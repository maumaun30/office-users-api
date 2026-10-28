<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ServiceBootstrapTest extends TestCase
{
    use RefreshDatabase;

    private string $tokenFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenFile = tempnam(sys_get_temp_dir(), 'svc-token');
        unlink($this->tokenFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->tokenFile);
        parent::tearDown();
    }

    public function test_creates_service_user_and_writes_working_token(): void
    {
        $this->artisan('service:bootstrap', ['email' => 'svc@example.com', '--token-file' => $this->tokenFile])
            ->assertSuccessful();

        $this->assertSame(Role::Service, User::firstWhere('email', 'svc@example.com')->role);

        $token = trim(file_get_contents($this->tokenFile));
        $this->withToken($token)->getJson('/api/users')->assertOk();
    }

    public function test_is_idempotent(): void
    {
        $args = ['email' => 'svc@example.com', '--token-file' => $this->tokenFile];
        $this->artisan('service:bootstrap', $args)->assertSuccessful();
        $first = file_get_contents($this->tokenFile);

        $this->artisan('service:bootstrap', $args)->assertSuccessful();

        $this->assertSame($first, file_get_contents($this->tokenFile));
        $this->assertSame(1, User::count());
        $this->assertSame(1, PersonalAccessToken::count());
    }

    public function test_refuses_non_service_user(): void
    {
        User::factory()->admin()->create(['email' => 'boss@example.com']);

        $this->artisan('service:bootstrap', ['email' => 'boss@example.com'])->assertFailed();
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_seed_option_creates_demo_accounts_once(): void
    {
        $args = ['email' => 'inventory-service@example.com', '--seed' => true];
        $this->artisan('service:bootstrap', $args)->assertSuccessful();
        $this->artisan('service:bootstrap', $args)->assertSuccessful();

        $this->assertSame(3, User::count());
        $this->assertTrue(User::firstWhere('email', 'admin@example.com')->isAdmin());
        $this->assertSame(Role::Service, User::firstWhere('email', 'inventory-service@example.com')->role);
    }
}
