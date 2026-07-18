<?php

namespace Tests\Feature\Auth;

use App\Models\Courier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'username' => 'Username atau password salah, atau akun tidak terdaftar.',
        ]);
    }

    public function test_unregistered_username_receives_the_same_safe_login_error(): void
    {
        $response = $this->post('/login', [
            'username' => 'tidak_terdaftar',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'username' => 'Username atau password salah, atau akun tidak terdaftar.',
        ]);
    }

    public function test_users_can_not_authenticate_with_email_as_username(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'username' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_courier_stays_online_until_logout(): void
    {
        $user = User::factory()->create();
        $courier = Courier::create([
            'user_id' => $user->id,
            'courier_code' => 'KUR-LOGIN',
            'name' => $user->name,
            'is_active' => true,
            'last_location_at' => now()->subDay(),
        ]);

        $this->post('/login', ['username' => $user->username, 'password' => 'password'])->assertRedirect();
        $this->assertTrue($courier->fresh()->is_online);

        $this->post('/logout')->assertRedirect('/');
        $this->assertFalse($courier->fresh()->is_online);
    }
}
