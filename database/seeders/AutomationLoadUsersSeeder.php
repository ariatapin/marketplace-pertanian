<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AutomationLoadUsersSeeder extends Seeder
{
    private const MITRA_COUNT = 10;
    private const SELLER_COUNT = 10;
    private const AFFILIATE_COUNT = 10;
    private const CONSUMER_COUNT = 70;

    public function run(): void
    {
        DB::transaction(function (): void {
            $adminId = (int) (DB::table('users')
                ->whereRaw('LOWER(TRIM(email)) = ?', ['admin@demo.test'])
                ->value('id') ?? 0);

            $this->seedMitraUsers($adminId);
            $this->seedSellerUsers($adminId);
            $this->seedAffiliateUsers($adminId);
            $this->seedConsumerUsers();
        });
    }

    private function seedMitraUsers(int $adminId): void
    {
        for ($i = 1; $i <= self::MITRA_COUNT; $i++) {
            $userId = $this->upsertUser(
                name: sprintf('Load Mitra %02d', $i),
                email: sprintf('load.mitra%02d@demo.test', $i),
                role: 'mitra',
                phoneNumber: sprintf('0819%07d', $i)
            );

            $this->upsertMitraProfile($userId, [
                'store_name' => sprintf('Toko Mitra Load %02d', $i),
                'store_address' => sprintf('Jl. Mitra Load No. %d', $i),
                'region_id' => 1101,
                'is_active' => true,
                'wallet_balance' => 0,
            ]);

            $this->upsertMitraApplication($userId, $adminId, [
                'full_name' => sprintf('Load Mitra %02d', $i),
                'email' => sprintf('load.mitra%02d@demo.test', $i),
                'region_id' => 1101,
                'status' => 'approved',
                'notes' => 'Auto seeded untuk simulasi aktivitas peran.',
            ]);

            $this->upsertWithdrawBankAccount($userId, [
                'bank_name' => 'BCA',
                'account_number' => sprintf('7100%06d', $i),
                'account_holder' => sprintf('Load Mitra %02d', $i),
            ]);

            $this->upsertStoreProduct($userId, [
                'name' => sprintf('Produk Mitra Load %02d', $i),
                'description' => 'Produk dummy untuk simulasi checkout.',
                'price' => 25000 + ($i * 1000),
                'stock_qty' => 500,
                'is_affiliate_enabled' => true,
                'affiliate_commission' => 1500,
                'affiliate_expire_date' => now()->addDays(30)->toDateString(),
                'unit' => 'kg',
                'is_active' => true,
            ]);
        }
    }

    private function seedSellerUsers(int $adminId): void
    {
        for ($i = 1; $i <= self::SELLER_COUNT; $i++) {
            $userId = $this->upsertUser(
                name: sprintf('Load Penjual %02d', $i),
                email: sprintf('load.seller%02d@demo.test', $i),
                role: 'consumer',
                phoneNumber: sprintf('0828%07d', $i)
            );

            $this->upsertConsumerProfile($userId, [
                'mode' => 'farmer_seller',
                'mode_status' => 'approved',
                'requested_mode' => null,
                'address' => sprintf('Alamat Penjual Load %02d', $i),
            ]);

            $this->upsertFarmerProfile($userId, [
                'bank_name' => 'BRI',
                'account_number' => sprintf('8100%06d', $i),
                'account_holder' => sprintf('Load Penjual %02d', $i),
            ]);

            $this->upsertFarmerSellerApplication($userId, $adminId, [
                'full_name' => sprintf('Load Penjual %02d', $i),
                'email' => sprintf('load.seller%02d@demo.test', $i),
                'region_id' => 1101,
                'status' => 'approved',
                'notes' => 'Auto approved untuk simulasi role seller.',
            ]);

            $this->upsertWithdrawBankAccount($userId, [
                'bank_name' => 'BRI',
                'account_number' => sprintf('8100%06d', $i),
                'account_holder' => sprintf('Load Penjual %02d', $i),
            ]);

            $this->upsertFarmerHarvest($userId, [
                'name' => sprintf('Panen Load %02d', $i),
                'description' => 'Produk panen dummy untuk simulasi P2P.',
                'price' => 18000 + ($i * 500),
                'stock_qty' => 400,
            ]);
        }
    }

    private function seedAffiliateUsers(int $adminId): void
    {
        for ($i = 1; $i <= self::AFFILIATE_COUNT; $i++) {
            $userId = $this->upsertUser(
                name: sprintf('Load Affiliate %02d', $i),
                email: sprintf('load.affiliate%02d@demo.test', $i),
                role: 'consumer',
                phoneNumber: sprintf('0837%07d', $i)
            );

            $this->upsertConsumerProfile($userId, [
                'mode' => 'affiliate',
                'mode_status' => 'approved',
                'requested_mode' => null,
                'address' => sprintf('Alamat Affiliate Load %02d', $i),
            ]);

            $this->upsertAffiliateApplication($userId, $adminId, [
                'full_name' => sprintf('Load Affiliate %02d', $i),
                'email' => sprintf('load.affiliate%02d@demo.test', $i),
                'region_id' => 1101,
                'status' => 'approved',
                'notes' => 'Auto approved untuk simulasi role affiliate.',
            ]);

            $this->upsertWithdrawBankAccount($userId, [
                'bank_name' => 'Mandiri',
                'account_number' => sprintf('9100%06d', $i),
                'account_holder' => sprintf('Load Affiliate %02d', $i),
            ]);
        }
    }

    private function seedConsumerUsers(): void
    {
        for ($i = 1; $i <= self::CONSUMER_COUNT; $i++) {
            $userId = $this->upsertUser(
                name: sprintf('Load Consumer %03d', $i),
                email: sprintf('load.consumer%03d@demo.test', $i),
                role: 'consumer',
                phoneNumber: sprintf('0851%07d', $i)
            );

            $this->upsertConsumerProfile($userId, [
                'mode' => 'buyer',
                'mode_status' => 'none',
                'requested_mode' => null,
                'address' => sprintf('Alamat Consumer Load %03d', $i),
            ]);
        }
    }

    private function upsertUser(string $name, string $email, string $role, string $phoneNumber): int
    {
        $now = now();
        $password = Hash::make('password');

        $existing = DB::table('users')->where('email', $email)->first(['id']);
        $payload = [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'phone_number' => $phoneNumber,
            'role' => $role,
            'email_verified_at' => now(),
            'remember_token' => null,
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::table('users')->where('id', (int) $existing->id)->update($payload);

            return (int) $existing->id;
        }

        $payload['created_at'] = $now;

        return (int) DB::table('users')->insertGetId($payload);
    }

    private function upsertConsumerProfile(int $userId, array $payload): void
    {
        if (! Schema::hasTable('consumer_profiles')) {
            return;
        }

        $now = now();
        $basePayload = [
            'address' => $payload['address'] ?? null,
            'mode' => $payload['mode'] ?? 'buyer',
            'mode_status' => $payload['mode_status'] ?? 'none',
            'requested_mode' => $payload['requested_mode'] ?? null,
            'updated_at' => $now,
        ];

        if (DB::table('consumer_profiles')->where('user_id', $userId)->exists()) {
            DB::table('consumer_profiles')->where('user_id', $userId)->update($basePayload);

            return;
        }

        DB::table('consumer_profiles')->insert($basePayload + [
            'user_id' => $userId,
            'created_at' => $now,
        ]);
    }

    private function upsertMitraProfile(int $userId, array $payload): void
    {
        if (! Schema::hasTable('mitra_profiles')) {
            return;
        }

        $now = now();
        $basePayload = [
            'store_name' => (string) ($payload['store_name'] ?? 'Toko Mitra'),
            'store_address' => (string) ($payload['store_address'] ?? '-'),
            'region_id' => $payload['region_id'] ?? null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'wallet_balance' => (float) ($payload['wallet_balance'] ?? 0),
            'updated_at' => $now,
        ];

        if (DB::table('mitra_profiles')->where('user_id', $userId)->exists()) {
            DB::table('mitra_profiles')->where('user_id', $userId)->update($basePayload);

            return;
        }

        DB::table('mitra_profiles')->insert($basePayload + [
            'user_id' => $userId,
            'created_at' => $now,
        ]);
    }

    private function upsertFarmerProfile(int $userId, array $payload): void
    {
        if (! Schema::hasTable('farmer_profiles')) {
            return;
        }

        $now = now();
        $basePayload = [
            'bank_name' => $payload['bank_name'] ?? null,
            'account_number' => $payload['account_number'] ?? null,
            'account_holder' => $payload['account_holder'] ?? null,
            'updated_at' => $now,
        ];

        if (DB::table('farmer_profiles')->where('user_id', $userId)->exists()) {
            DB::table('farmer_profiles')->where('user_id', $userId)->update($basePayload);

            return;
        }

        DB::table('farmer_profiles')->insert($basePayload + [
            'user_id' => $userId,
            'created_at' => $now,
        ]);
    }

    private function upsertWithdrawBankAccount(int $userId, array $payload): void
    {
        if (! Schema::hasTable('withdraw_bank_accounts')) {
            return;
        }

        $now = now();
        $basePayload = [
            'bank_name' => $payload['bank_name'] ?? null,
            'account_number' => $payload['account_number'] ?? null,
            'account_holder' => $payload['account_holder'] ?? null,
            'updated_at' => $now,
        ];

        if (DB::table('withdraw_bank_accounts')->where('user_id', $userId)->exists()) {
            DB::table('withdraw_bank_accounts')->where('user_id', $userId)->update($basePayload);

            return;
        }

        DB::table('withdraw_bank_accounts')->insert($basePayload + [
            'user_id' => $userId,
            'created_at' => $now,
        ]);
    }

    private function upsertMitraApplication(int $userId, int $adminId, array $payload): void
    {
        if (! Schema::hasTable('mitra_applications')) {
            return;
        }

        $now = now();
        $status = (string) ($payload['status'] ?? 'pending');
        $basePayload = [
            'full_name' => (string) $payload['full_name'],
            'email' => (string) $payload['email'],
            'region_id' => $payload['region_id'] ?? null,
            'status' => $status,
            'decided_by' => $status === 'approved' && $adminId > 0 ? $adminId : null,
            'decided_at' => $status === 'approved' ? $now : null,
            'notes' => $payload['notes'] ?? null,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('mitra_applications', 'submitted_at')) {
            $basePayload['submitted_at'] = $status !== 'draft' ? $now : null;
        }

        if (DB::table('mitra_applications')->where('user_id', $userId)->exists()) {
            DB::table('mitra_applications')->where('user_id', $userId)->update($basePayload);

            return;
        }

        DB::table('mitra_applications')->insert($basePayload + [
            'user_id' => $userId,
            'created_at' => $now,
        ]);
    }

    private function upsertAffiliateApplication(int $userId, int $adminId, array $payload): void
    {
        if (! Schema::hasTable('affiliate_applications')) {
            return;
        }

        $now = now();
        $status = (string) ($payload['status'] ?? 'pending');
        $basePayload = [
            'full_name' => (string) $payload['full_name'],
            'email' => (string) $payload['email'],
            'region_id' => $payload['region_id'] ?? null,
            'status' => $status,
            'is_auto_approved' => $status === 'approved',
            'decided_by' => $status === 'approved' && $adminId > 0 ? $adminId : null,
            'decided_at' => $status === 'approved' ? $now : null,
            'notes' => $payload['notes'] ?? null,
            'updated_at' => $now,
        ];

        if (DB::table('affiliate_applications')->where('user_id', $userId)->exists()) {
            DB::table('affiliate_applications')->where('user_id', $userId)->update($basePayload);

            return;
        }

        DB::table('affiliate_applications')->insert($basePayload + [
            'user_id' => $userId,
            'created_at' => $now,
        ]);
    }

    private function upsertFarmerSellerApplication(int $userId, int $adminId, array $payload): void
    {
        if (! Schema::hasTable('farmer_seller_applications')) {
            return;
        }

        $now = now();
        $status = (string) ($payload['status'] ?? 'pending');
        $basePayload = [
            'full_name' => (string) $payload['full_name'],
            'email' => (string) $payload['email'],
            'region_id' => $payload['region_id'] ?? null,
            'main_commodities' => json_encode(['padi', 'cabai']),
            'status' => $status,
            'decided_by' => $status === 'approved' && $adminId > 0 ? $adminId : null,
            'decided_at' => $status === 'approved' ? $now : null,
            'notes' => $payload['notes'] ?? null,
            'updated_at' => $now,
        ];

        if (DB::table('farmer_seller_applications')->where('user_id', $userId)->exists()) {
            DB::table('farmer_seller_applications')->where('user_id', $userId)->update($basePayload);

            return;
        }

        DB::table('farmer_seller_applications')->insert($basePayload + [
            'user_id' => $userId,
            'created_at' => $now,
        ]);
    }

    private function upsertStoreProduct(int $mitraId, array $payload): void
    {
        if (! Schema::hasTable('store_products')) {
            return;
        }

        $now = now();
        $name = (string) ($payload['name'] ?? 'Produk Mitra');
        $basePayload = [
            'mitra_id' => $mitraId,
            'name' => $name,
            'description' => $payload['description'] ?? null,
            'price' => (float) ($payload['price'] ?? 0),
            'stock_qty' => (int) ($payload['stock_qty'] ?? 0),
            'image_url' => null,
            'is_affiliate_enabled' => (bool) ($payload['is_affiliate_enabled'] ?? false),
            'affiliate_commission' => (float) ($payload['affiliate_commission'] ?? 0),
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('store_products', 'affiliate_expire_date')) {
            $basePayload['affiliate_expire_date'] = $payload['affiliate_expire_date'] ?? null;
        }
        if (Schema::hasColumn('store_products', 'unit')) {
            $basePayload['unit'] = (string) ($payload['unit'] ?? 'kg');
        }
        if (Schema::hasColumn('store_products', 'is_active')) {
            $basePayload['is_active'] = (bool) ($payload['is_active'] ?? true);
        }

        $existing = DB::table('store_products')
            ->where('mitra_id', $mitraId)
            ->where('name', $name)
            ->first(['id']);

        if ($existing) {
            DB::table('store_products')
                ->where('id', (int) $existing->id)
                ->update($basePayload);

            return;
        }

        DB::table('store_products')->insert($basePayload + [
            'created_at' => $now,
        ]);
    }

    private function upsertFarmerHarvest(int $farmerId, array $payload): void
    {
        if (! Schema::hasTable('farmer_harvests')) {
            return;
        }

        $now = now();
        $name = (string) ($payload['name'] ?? 'Panen');
        $basePayload = [
            'farmer_id' => $farmerId,
            'name' => $name,
            'description' => $payload['description'] ?? null,
            'price' => (float) ($payload['price'] ?? 0),
            'stock_qty' => (int) ($payload['stock_qty'] ?? 0),
            'harvest_date' => now()->toDateString(),
            'image_url' => null,
            'status' => 'approved',
            'updated_at' => $now,
        ];

        $existing = DB::table('farmer_harvests')
            ->where('farmer_id', $farmerId)
            ->where('name', $name)
            ->first(['id']);

        if ($existing) {
            DB::table('farmer_harvests')
                ->where('id', (int) $existing->id)
                ->update($basePayload);

            return;
        }

        DB::table('farmer_harvests')->insert($basePayload + [
            'created_at' => $now,
        ]);
    }
}
