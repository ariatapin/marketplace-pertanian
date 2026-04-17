<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminUsersModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_users_module_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.modules.users'));

        $response->assertOk();
        $response->assertSee('Modul Users');
        $response->assertSee('Total User');
    }

    public function test_admin_can_filter_users_by_role_and_mode_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $pendingConsumer = User::factory()->create([
            'role' => 'consumer',
            'email' => 'pending.consumer@example.test',
        ]);

        $approvedConsumer = User::factory()->create([
            'role' => 'consumer',
            'email' => 'approved.consumer@example.test',
        ]);

        User::factory()->create([
            'role' => 'mitra',
            'email' => 'mitra.filter@example.test',
        ]);

        DB::table('consumer_profiles')->insert([
            [
                'user_id' => $pendingConsumer->id,
                'address' => null,
                'mode' => 'buyer',
                'mode_status' => 'pending',
                'requested_mode' => 'farmer_seller',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $approvedConsumer->id,
                'address' => null,
                'mode' => 'affiliate',
                'mode_status' => 'approved',
                'requested_mode' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.modules.users', [
            'role' => 'consumer',
            'mode_status' => 'pending',
        ]));

        $response->assertOk();
        $response->assertSee('pending.consumer@example.test');
        $response->assertDontSee('approved.consumer@example.test');
        $response->assertDontSee('mitra.filter@example.test');
    }

    public function test_admin_cannot_suspend_own_account(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_suspended' => false,
            'suspended_at' => null,
            'suspension_note' => null,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.users'))
            ->post(route('admin.modules.users.suspend', ['userId' => $admin->id]), [
                'note' => 'self suspend attempt',
            ]);

        $response->assertRedirect(route('admin.modules.users'));
        $response->assertSessionHasErrors(['users']);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_suspended' => false,
            'suspended_at' => null,
            'suspension_note' => null,
        ]);
    }

    public function test_users_page_hides_suspend_and_block_actions_for_current_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = User::factory()->create(['role' => 'consumer']);

        $response = $this->actingAs($admin)->get(route('admin.modules.users'));

        $response->assertOk();
        $response->assertSee('Akun Anda');

        $response->assertDontSee(
            route('admin.modules.users.suspend', ['userId' => $admin->id]),
            false
        );
        $response->assertDontSee(
            route('admin.modules.users.block', ['userId' => $admin->id]),
            false
        );

        $response->assertSee(
            route('admin.modules.users.suspend', ['userId' => $consumer->id]),
            false
        );
        $response->assertSee(
            route('admin.modules.users.block', ['userId' => $consumer->id]),
            false
        );
    }
}
