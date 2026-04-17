<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminReportsModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_reports_module_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.modules.reports'));

        $response->assertOk();
        $response->assertSee('data-testid="admin-reports-page"', false);
        $response->assertSee('data-testid="admin-reports-filters"', false);
    }

    public function test_reports_module_can_filter_disputes_and_show_aggregations(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'consumer']);
        $seller = User::factory()->create(['role' => 'mitra']);

        $orderA = DB::table('orders')->insertGetId([
                'buyer_id' => $buyer->id,
                'seller_id' => $seller->id,
                'order_source' => 'farmer_p2p',
                'total_amount' => 100000,
                'payment_status' => 'paid',
                'order_status' => 'completed',
                'payment_proof_url' => null,
                'shipping_status' => 'delivered',
                'resi_number' => 'RESI-1',
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subDays(1),
        ]);

        $orderB = DB::table('orders')->insertGetId([
                'buyer_id' => $buyer->id,
                'seller_id' => $seller->id,
                'order_source' => 'store_online',
                'total_amount' => 50000,
                'payment_status' => 'paid',
                'order_status' => 'completed',
                'payment_proof_url' => null,
                'shipping_status' => 'delivered',
                'resi_number' => null,
                'created_at' => now()->subDays(20),
                'updated_at' => now()->subDays(20),
        ]);

        $disputeA = DB::table('disputes')->insertGetId([
            'order_id' => $orderA,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'opened_by' => $buyer->id,
            'category' => 'wrong_item',
            'description' => 'Barang tidak sesuai pesanan.',
            'status' => 'pending',
            'handled_by' => null,
            'handled_at' => null,
            'resolution' => null,
            'resolution_notes' => null,
            'evidence_urls' => json_encode([], JSON_UNESCAPED_UNICODE),
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);

        $disputeB = DB::table('disputes')->insertGetId([
            'order_id' => $orderB,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'opened_by' => $buyer->id,
            'category' => 'delayed_shipping',
            'description' => 'Pengiriman terlambat.',
            'status' => 'resolved_buyer',
            'handled_by' => $admin->id,
            'handled_at' => now()->subDay(),
            'resolution' => 'refund_partial',
            'resolution_notes' => 'Refund sebagian.',
            'evidence_urls' => json_encode([], JSON_UNESCAPED_UNICODE),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.modules.reports', [
            'status' => 'pending',
            'category' => 'wrong_item',
            'q' => (string) $buyer->email,
        ]));

        $response->assertOk();
        $response->assertSee('data-testid="admin-reports-summary"', false);
        $response->assertSee('WRONG_ITEM');
        $response->assertSee("admin/reports/{$disputeA}", false);
        $response->assertDontSee("admin/reports/{$disputeB}", false);
    }
}
