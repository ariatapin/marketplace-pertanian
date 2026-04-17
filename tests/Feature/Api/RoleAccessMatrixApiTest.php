<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleAccessMatrixApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_access_matrix_is_enforced_for_five_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $affiliateMode = User::factory()->create(['role' => 'consumer']);
        $sellerMode = User::factory()->create(['role' => 'consumer']);
        $legacyAffiliate = User::factory()->create(['role' => 'affiliate']);

        $this->seedConsumerProfile($affiliateMode->id, 'affiliate', 'approved');
        $this->seedConsumerProfile($sellerMode->id, 'farmer_seller', 'approved');

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/approvals/pending')->assertOk();
        $this->getJson('/api/mitra/orders')->assertStatus(403);
        $this->getJson('/api/consumer/orders')->assertStatus(403);
        $this->getJson('/api/affiliate/commissions')->assertStatus(403);
        $this->getJson('/api/seller/orders')->assertStatus(403);

        Sanctum::actingAs($mitra);
        $this->getJson('/api/mitra/orders')->assertOk();
        $this->getJson('/api/admin/approvals/pending')->assertStatus(403);
        $this->getJson('/api/consumer/orders')->assertStatus(403);
        $this->getJson('/api/affiliate/commissions')->assertStatus(403);
        $this->getJson('/api/seller/orders')->assertStatus(403);

        Sanctum::actingAs($buyer);
        $this->getJson('/api/consumer/orders')->assertOk();
        $this->getJson('/api/admin/approvals/pending')->assertStatus(403);
        $this->getJson('/api/mitra/orders')->assertStatus(403);
        $this->getJson('/api/affiliate/commissions')->assertStatus(403);
        $this->getJson('/api/seller/orders')->assertStatus(403);

        Sanctum::actingAs($affiliateMode);
        $this->getJson('/api/affiliate/commissions')->assertOk();
        $this->getJson('/api/seller/orders')->assertStatus(403);
        $this->getJson('/api/admin/approvals/pending')->assertStatus(403);
        $this->getJson('/api/mitra/orders')->assertStatus(403);

        Sanctum::actingAs($sellerMode);
        $this->getJson('/api/seller/orders')->assertOk();
        $this->getJson('/api/affiliate/commissions')->assertStatus(403);
        $this->getJson('/api/admin/approvals/pending')->assertStatus(403);
        $this->getJson('/api/mitra/orders')->assertStatus(403);

        Sanctum::actingAs($legacyAffiliate);
        $this->getJson('/api/affiliate/commissions')->assertStatus(403);
        $this->getJson('/api/seller/orders')->assertStatus(403);
        $this->getJson('/api/admin/approvals/pending')->assertStatus(403);
        $this->getJson('/api/mitra/orders')->assertStatus(403);
    }

    private function seedConsumerProfile(int $userId, string $mode, string $modeStatus): void
    {
        DB::table('consumer_profiles')->insert([
            'user_id' => $userId,
            'address' => null,
            'mode' => $mode,
            'mode_status' => $modeStatus,
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
