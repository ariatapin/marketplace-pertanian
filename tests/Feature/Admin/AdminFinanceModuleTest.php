<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminFinanceModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_finance_module_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.modules.finance'));

        $response->assertOk();
        $response->assertSee('data-testid="admin-finance-page"', false);
        $response->assertSee('Antrian Withdraw');
    }

    public function test_admin_can_update_affiliate_commission_range(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.finance', ['section' => 'affiliate']))
            ->post(route('admin.modules.finance.affiliateCommissionRange.update'), [
                'affiliate_commission_min_percent' => 5,
                'affiliate_commission_max_percent' => 18.5,
            ]);

        $response->assertRedirect(route('admin.modules.finance', ['section' => 'affiliate']));

        $this->assertDatabaseHas('admin_profiles', [
            'user_id' => $admin->id,
            'affiliate_commission_min_percent' => 5.00,
            'affiliate_commission_max_percent' => 18.50,
        ]);
    }

    public function test_admin_cannot_save_affiliate_commission_range_when_max_below_min(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.finance', ['section' => 'affiliate']))
            ->post(route('admin.modules.finance.affiliateCommissionRange.update'), [
                'affiliate_commission_min_percent' => 20,
                'affiliate_commission_max_percent' => 10,
            ]);

        $response->assertRedirect(route('admin.modules.finance', ['section' => 'affiliate']));
        $response->assertSessionHasErrors('affiliate_commission_max_percent');
    }

    public function test_admin_can_filter_withdraw_rows_and_approve_from_finance_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $consumer = User::factory()->create([
            'role' => 'consumer',
            'email' => 'withdraw.pending@example.test',
        ]);

        DB::table('withdraw_requests')->insert([
            [
                'user_id' => $consumer->id,
                'amount' => 100000,
                'bank_name' => 'BCA',
                'account_number' => '123',
                'account_holder' => 'User Pending',
                'status' => 'pending',
                'processed_by' => null,
                'processed_at' => null,
                'transfer_proof_url' => null,
                'transfer_reference' => null,
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $consumer->id,
                'amount' => 50000,
                'bank_name' => 'BNI',
                'account_number' => '456',
                'account_holder' => 'User Paid',
                'status' => 'paid',
                'processed_by' => $admin->id,
                'processed_at' => now(),
                'transfer_proof_url' => null,
                'transfer_reference' => 'TRX-1',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.modules.finance', [
            'withdraw_status' => 'pending',
        ]));

        $response->assertOk();
        $response->assertSee('User Pending');
        $response->assertDontSee('User Paid');

        $pendingId = DB::table('withdraw_requests')->where('status', 'pending')->value('id');

        $this->actingAs($admin)
            ->post(route('admin.withdraws.approve', ['withdrawId' => $pendingId]))
            ->assertRedirect();

        $this->assertDatabaseHas('withdraw_requests', [
            'id' => $pendingId,
            'status' => 'approved',
            'processed_by' => $admin->id,
        ]);
    }

    public function test_admin_can_monitor_transfer_payments_with_waiting_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $buyer = User::factory()->create(['role' => 'consumer', 'name' => 'Buyer Waiting']);
        $verifiedBuyer = User::factory()->create(['role' => 'consumer', 'name' => 'Buyer Verified']);
        $seller = User::factory()->create(['role' => 'mitra', 'name' => 'Seller Alpha']);

        DB::table('orders')->insert([
            [
                'buyer_id' => $buyer->id,
                'seller_id' => $seller->id,
                'order_source' => 'store_online',
                'total_amount' => 180000,
                'payment_method' => 'gopay',
                'payment_status' => 'unpaid',
                'order_status' => 'pending_payment',
                'payment_proof_url' => 'storage/payment_proofs/orders/waiting.jpg',
                'paid_amount' => 180000,
                'payment_submitted_at' => now(),
                'shipping_status' => 'pending',
                'resi_number' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'buyer_id' => $verifiedBuyer->id,
                'seller_id' => $seller->id,
                'order_source' => 'store_online',
                'total_amount' => 120000,
                'payment_method' => 'bank_transfer',
                'payment_status' => 'paid',
                'order_status' => 'paid',
                'payment_proof_url' => 'storage/payment_proofs/orders/verified.jpg',
                'paid_amount' => 120000,
                'payment_submitted_at' => now()->subHour(),
                'shipping_status' => 'pending',
                'resi_number' => null,
                'created_at' => now()->subHour(),
                'updated_at' => now()->subHour(),
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.modules.finance', [
            'section' => 'transfer',
            'transfer_state' => 'waiting',
        ]));

        $response->assertOk();
        $response->assertSee('Monitoring Pembayaran Transfer');
        $response->assertSee('Buyer Waiting');
        $response->assertSee('Menunggu Verifikasi');
        $response->assertDontSee('Buyer Verified');
    }

    public function test_admin_mark_paid_deducts_admin_and_user_wallet_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $affiliate = User::factory()->create(['role' => 'consumer']);
        DB::table('consumer_profiles')->insert([
            'user_id' => $affiliate->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wallet_transactions')->insert([
            [
                'wallet_id' => $admin->id,
                'amount' => 200000,
                'transaction_type' => 'demo_topup',
                'idempotency_key' => "test:wallet:topup:admin:{$admin->id}",
                'reference_order_id' => null,
                'reference_withdraw_id' => null,
                'description' => 'Admin topup untuk payout test',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'wallet_id' => $affiliate->id,
                'amount' => 90000,
                'transaction_type' => 'affiliate_commission',
                'idempotency_key' => "test:wallet:commission:user:{$affiliate->id}",
                'reference_order_id' => null,
                'reference_withdraw_id' => null,
                'description' => 'Komisi affiliate',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $withdrawId = DB::table('withdraw_requests')->insertGetId([
            'user_id' => $affiliate->id,
            'amount' => 50000,
            'bank_name' => 'BCA',
            'account_number' => '123123',
            'account_holder' => 'Affiliate User',
            'status' => 'approved',
            'processed_by' => $admin->id,
            'processed_at' => now(),
            'transfer_proof_url' => null,
            'transfer_reference' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.withdraws.paid', ['withdrawId' => $withdrawId]), [
                'transfer_reference' => 'TRX-WD-001',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('withdraw_requests', [
            'id' => $withdrawId,
            'status' => 'paid',
            'processed_by' => $admin->id,
            'paid_by' => $admin->id,
            'transfer_reference' => 'TRX-WD-001',
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $affiliate->id,
            'transaction_type' => 'withdrawal',
            'reference_withdraw_id' => $withdrawId,
            'amount' => -50000,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $admin->id,
            'transaction_type' => 'admin_payout',
            'reference_withdraw_id' => $withdrawId,
            'amount' => -50000,
        ]);
    }

    public function test_admin_cannot_mark_paid_when_admin_wallet_is_insufficient(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $mitra = User::factory()->create(['role' => 'mitra']);

        DB::table('wallet_transactions')->insert([
            [
                'wallet_id' => $admin->id,
                'amount' => 10000,
                'transaction_type' => 'demo_topup',
                'idempotency_key' => "test:wallet:topup:admin:insufficient:{$admin->id}",
                'reference_order_id' => null,
                'reference_withdraw_id' => null,
                'description' => 'Saldo admin minim',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'wallet_id' => $mitra->id,
                'amount' => 80000,
                'transaction_type' => 'sale_revenue',
                'idempotency_key' => "test:wallet:income:mitra:{$mitra->id}",
                'reference_order_id' => null,
                'reference_withdraw_id' => null,
                'description' => 'Pendapatan mitra',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $withdrawId = DB::table('withdraw_requests')->insertGetId([
            'user_id' => $mitra->id,
            'amount' => 50000,
            'bank_name' => 'BRI',
            'account_number' => '999000',
            'account_holder' => 'Mitra User',
            'status' => 'approved',
            'processed_by' => $admin->id,
            'processed_at' => now(),
            'transfer_proof_url' => null,
            'transfer_reference' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.finance', ['section' => 'withdraw']))
            ->post(route('admin.withdraws.paid', ['withdrawId' => $withdrawId]), [
                'transfer_reference' => 'TRX-WD-INSUFF',
            ]);

        $response->assertRedirect(route('admin.modules.finance', ['section' => 'withdraw']));
        $response->assertSessionHasErrors('admin_balance');

        $this->assertDatabaseHas('withdraw_requests', [
            'id' => $withdrawId,
            'status' => 'approved',
        ]);

        $this->assertDatabaseMissing('wallet_transactions', [
            'wallet_id' => $admin->id,
            'transaction_type' => 'admin_payout',
            'reference_withdraw_id' => $withdrawId,
        ]);
    }

    public function test_admin_cannot_mark_paid_when_withdraw_still_pending(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $affiliate = User::factory()->create(['role' => 'consumer']);
        DB::table('consumer_profiles')->insert([
            'user_id' => $affiliate->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wallet_transactions')->insert([
            [
                'wallet_id' => $admin->id,
                'amount' => 200000,
                'transaction_type' => 'demo_topup',
                'idempotency_key' => "test:wallet:topup:admin:pending:{$admin->id}",
                'reference_order_id' => null,
                'reference_withdraw_id' => null,
                'description' => 'Saldo admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'wallet_id' => $affiliate->id,
                'amount' => 90000,
                'transaction_type' => 'affiliate_commission',
                'idempotency_key' => "test:wallet:affiliate:pending:{$affiliate->id}",
                'reference_order_id' => null,
                'reference_withdraw_id' => null,
                'description' => 'Saldo affiliate',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $withdrawId = DB::table('withdraw_requests')->insertGetId([
            'user_id' => $affiliate->id,
            'amount' => 50000,
            'bank_name' => 'BCA',
            'account_number' => '123456',
            'account_holder' => 'Affiliate User',
            'status' => 'pending',
            'processed_by' => null,
            'processed_at' => null,
            'transfer_proof_url' => null,
            'transfer_reference' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.finance', ['section' => 'withdraw']))
            ->post(route('admin.withdraws.paid', ['withdrawId' => $withdrawId]), [
                'transfer_reference' => 'TRX-PENDING-01',
            ]);

        $response->assertRedirect(route('admin.modules.finance', ['section' => 'withdraw']));
        $response->assertSessionHasErrors('status');

        $this->assertDatabaseHas('withdraw_requests', [
            'id' => $withdrawId,
            'status' => 'pending',
            'paid_by' => null,
        ]);
    }

    public function test_admin_mark_paid_requires_transfer_reference_or_proof(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $affiliate = User::factory()->create(['role' => 'consumer']);
        DB::table('consumer_profiles')->insert([
            'user_id' => $affiliate->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wallet_transactions')->insert([
            [
                'wallet_id' => $admin->id,
                'amount' => 200000,
                'transaction_type' => 'demo_topup',
                'idempotency_key' => "test:wallet:topup:admin:evidence:{$admin->id}",
                'reference_order_id' => null,
                'reference_withdraw_id' => null,
                'description' => 'Saldo admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'wallet_id' => $affiliate->id,
                'amount' => 90000,
                'transaction_type' => 'affiliate_commission',
                'idempotency_key' => "test:wallet:affiliate:evidence:{$affiliate->id}",
                'reference_order_id' => null,
                'reference_withdraw_id' => null,
                'description' => 'Saldo affiliate',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $withdrawId = DB::table('withdraw_requests')->insertGetId([
            'user_id' => $affiliate->id,
            'amount' => 50000,
            'bank_name' => 'BCA',
            'account_number' => '123456',
            'account_holder' => 'Affiliate User',
            'status' => 'approved',
            'processed_by' => $admin->id,
            'processed_at' => now(),
            'transfer_proof_url' => null,
            'transfer_reference' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.finance', ['section' => 'withdraw']))
            ->post(route('admin.withdraws.paid', ['withdrawId' => $withdrawId]), [
                'transfer_reference' => '',
                'transfer_proof_url' => '',
            ]);

        $response->assertRedirect(route('admin.modules.finance', ['section' => 'withdraw']));
        $response->assertSessionHasErrors('transfer_reference');

        $this->assertDatabaseHas('withdraw_requests', [
            'id' => $withdrawId,
            'status' => 'approved',
            'paid_by' => null,
        ]);
    }

    public function test_mark_paid_keeps_approver_audit_and_records_paid_actor(): void
    {
        $approver = User::factory()->create(['role' => 'admin']);
        $payer = User::factory()->create(['role' => 'admin']);
        $affiliate = User::factory()->create(['role' => 'consumer']);
        DB::table('consumer_profiles')->insert([
            'user_id' => $affiliate->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wallet_transactions')->insert([
            [
                'wallet_id' => $payer->id,
                'amount' => 200000,
                'transaction_type' => 'demo_topup',
                'idempotency_key' => "test:wallet:topup:payer:audit:{$payer->id}",
                'reference_order_id' => null,
                'reference_withdraw_id' => null,
                'description' => 'Saldo admin payer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'wallet_id' => $affiliate->id,
                'amount' => 90000,
                'transaction_type' => 'affiliate_commission',
                'idempotency_key' => "test:wallet:affiliate:audit:{$affiliate->id}",
                'reference_order_id' => null,
                'reference_withdraw_id' => null,
                'description' => 'Saldo affiliate',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $approvedAt = now()->subMinutes(5);
        $withdrawId = DB::table('withdraw_requests')->insertGetId([
            'user_id' => $affiliate->id,
            'amount' => 50000,
            'bank_name' => 'BCA',
            'account_number' => '123456',
            'account_holder' => 'Affiliate User',
            'status' => 'approved',
            'processed_by' => $approver->id,
            'processed_at' => $approvedAt,
            'transfer_proof_url' => null,
            'transfer_reference' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($payer)
            ->post(route('admin.withdraws.paid', ['withdrawId' => $withdrawId]), [
                'transfer_reference' => 'TRX-AUDIT-01',
            ])
            ->assertRedirect();

        $row = DB::table('withdraw_requests')
            ->where('id', $withdrawId)
            ->first([
                'status',
                'processed_by',
                'processed_at',
                'paid_by',
                'paid_at',
                'transfer_reference',
            ]);

        $this->assertNotNull($row);
        $this->assertSame('paid', (string) $row->status);
        $this->assertSame((int) $approver->id, (int) $row->processed_by);
        $this->assertSame($approvedAt->toDateTimeString(), \Illuminate\Support\Carbon::parse($row->processed_at)->toDateTimeString());
        $this->assertSame((int) $payer->id, (int) $row->paid_by);
        $this->assertNotNull($row->paid_at);
        $this->assertSame('TRX-AUDIT-01', (string) $row->transfer_reference);
    }

    public function test_admin_mark_paid_is_blocked_when_finance_demo_mode_is_disabled(): void
    {
        config()->set('finance.demo_mode', false);

        $admin = User::factory()->create(['role' => 'admin']);
        $affiliate = User::factory()->create(['role' => 'consumer']);
        DB::table('consumer_profiles')->insert([
            'user_id' => $affiliate->id,
            'address' => null,
            'mode' => 'affiliate',
            'mode_status' => 'approved',
            'requested_mode' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wallet_transactions')->insert([
            [
                'wallet_id' => $admin->id,
                'amount' => 200000,
                'transaction_type' => 'demo_topup',
                'idempotency_key' => "test:wallet:topup:admin:demo-off:{$admin->id}",
                'reference_order_id' => null,
                'reference_withdraw_id' => null,
                'description' => 'Saldo admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'wallet_id' => $affiliate->id,
                'amount' => 90000,
                'transaction_type' => 'affiliate_commission',
                'idempotency_key' => "test:wallet:affiliate:demo-off:{$affiliate->id}",
                'reference_order_id' => null,
                'reference_withdraw_id' => null,
                'description' => 'Saldo affiliate',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $withdrawId = DB::table('withdraw_requests')->insertGetId([
            'user_id' => $affiliate->id,
            'amount' => 50000,
            'bank_name' => 'BCA',
            'account_number' => '123456',
            'account_holder' => 'Affiliate User',
            'status' => 'approved',
            'processed_by' => $admin->id,
            'processed_at' => now(),
            'transfer_proof_url' => null,
            'transfer_reference' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.modules.finance', ['section' => 'withdraw']))
            ->post(route('admin.withdraws.paid', ['withdrawId' => $withdrawId]), [
                'transfer_reference' => 'TRX-DEMO-OFF-01',
            ]);

        $response->assertRedirect(route('admin.modules.finance', ['section' => 'withdraw']));
        $response->assertSessionHasErrors('withdraw');

        $this->assertDatabaseHas('withdraw_requests', [
            'id' => $withdrawId,
            'status' => 'approved',
            'paid_by' => null,
        ]);
        $this->assertDatabaseMissing('wallet_transactions', [
            'wallet_id' => $affiliate->id,
            'transaction_type' => 'withdrawal',
            'reference_withdraw_id' => $withdrawId,
        ]);
        $this->assertDatabaseMissing('wallet_transactions', [
            'wallet_id' => $admin->id,
            'transaction_type' => 'admin_payout',
            'reference_withdraw_id' => $withdrawId,
        ]);
    }
}
