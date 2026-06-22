<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserService;
use App\Support\AuthHelper;
use App\Support\InstallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallAndUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_install_redirects_to_setup(): void
    {
        $this->assertFalse(InstallService::isInstalled());

        $this->get('/')->assertRedirect(route('setup.show'));
        $this->get('/login')->assertRedirect(route('setup.show'));
        $this->get('/setup')->assertOk()->assertSee('Create the first administrator');
    }

    public function test_setup_creates_admin_and_redirects_to_users(): void
    {
        $response = $this->post('/setup', [
            'name' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'securepass123',
            'password_confirmation' => 'securepass123',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertAuthenticated();
        $this->assertTrue(InstallService::isInstalled());

        $admin = User::first();
        $this->assertSame('admin', $admin->name);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->isActive());
    }

    public function test_setup_blocked_after_install(): void
    {
        User::factory()->create(['role' => UserRole::Admin]);

        $this->get('/setup')->assertRedirect(route('login'));
        $this->post('/setup', [
            'name' => 'other',
            'email' => 'other@example.com',
            'password' => 'securepass123',
            'password_confirmation' => 'securepass123',
        ])->assertRedirect(route('login'));

        $this->assertSame(1, User::count());
    }

    public function test_admin_can_create_user_and_user_can_login(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'admin', 'email' => 'admin@test.com']);

        $this->actingAs($admin)
            ->post('/admin/users', [
                'name' => 'jane',
                'email' => 'jane@test.com',
                'password' => 'userpass123',
                'password_confirmation' => 'userpass123',
                'role' => 'user',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'name' => 'jane',
            'email' => 'jane@test.com',
            'role' => 'user',
            'is_active' => true,
        ]);

        $this->post('/logout');

        $this->assertTrue(AuthHelper::attempt('jane', 'userpass123'));
    }

    public function test_non_admin_cannot_access_user_management(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);

        $this->actingAs($user)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_deactivated_user_cannot_login(): void
    {
        User::factory()->create([
            'name' => 'inactive',
            'email' => 'inactive@test.com',
            'password' => 'password123',
            'role' => UserRole::User,
            'is_active' => false,
        ]);

        $this->post('/login', [
            'login' => 'inactive',
            'password' => 'password123',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_duplicate_username_rejected(): void
    {
        User::factory()->create(['name' => 'taken', 'email' => 'a@test.com', 'role' => UserRole::Admin]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(UserService::class)->create('taken', 'b@test.com', 'password123');
    }

    public function test_api_returns_503_before_install(): void
    {
        $this->getJson('/api/v1/workbooks')
            ->assertStatus(503)
            ->assertJsonPath('message', 'System not installed. Complete setup at /setup first.');
    }
}
