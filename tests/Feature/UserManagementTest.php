<?php

namespace Tests\Feature;

use App\Models\CashTransaction;
use App\Models\Courier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('Super Admin');
    }

    public function test_admin_can_deactivate_and_reactivate_courier_user(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Kurir');
        $courier = Courier::create([
            'user_id' => $user->id,
            'courier_code' => 'KUR-TEST',
            'name' => $user->name,
            'is_active' => true,
            'is_online' => true,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('users.status', $user), ['is_active' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse($user->fresh()->is_active);
        $this->assertFalse($courier->fresh()->is_active);
        $this->assertFalse($courier->fresh()->is_online);

        $this->actingAs($this->admin)
            ->patch(route('users.status', $user), ['is_active' => true])
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->is_active);
        $this->assertTrue($courier->fresh()->is_active);
    }

    public function test_current_account_and_last_active_super_admin_cannot_be_deactivated_or_deleted(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('users.status', $this->admin), ['is_active' => false])
            ->assertSessionHasErrors('action');

        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $this->admin))
            ->assertSessionHasErrors('action');

        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'is_active' => true]);
    }

    public function test_unused_user_can_be_deleted_but_user_with_transaction_history_cannot(): void
    {
        $unused = User::factory()->create();
        $unused->assignRole('Staff');

        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $unused))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('users', ['id' => $unused->id]);

        $used = User::factory()->create();
        $used->assignRole('Staff');
        CashTransaction::create([
            'transaction_number' => 'CM/TEST/00001',
            'type' => 'in',
            'transaction_date' => now()->toDateString(),
            'category' => 'Lainnya',
            'description' => 'Data historis pengguna',
            'payment_method' => 'cash',
            'amount' => 10000,
            'created_by' => $used->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $used))
            ->assertSessionHasErrors('action');
        $this->assertDatabaseHas('users', ['id' => $used->id]);
    }

    public function test_regular_edit_cannot_bypass_dedicated_status_action(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Staff');
        $originalPassword = $user->password;

        $this->actingAs($this->admin)->put(route('users.update', $user), [
            'name' => 'Nama Diperbarui',
            'username' => $user->username,
            'email' => $user->email,
            'password' => 'PasswordYangTidakBolehDipakai123!',
            'role' => 'Staff',
            'is_active' => false,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nama Diperbarui',
            'is_active' => true,
        ]);
        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->assertFalse(Hash::check('PasswordYangTidakBolehDipakai123!', $user->fresh()->password));
    }
}
