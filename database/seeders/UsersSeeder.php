<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            // =========================
            // ADMIN
            // =========================
            $adminId = $this->upsertUser([
                'name' => 'Admin System',
                'email' => 'admin@demo.test',
                'password' => Hash::make('password'),
                'phone_number' => '081111111111',
                'role' => 'admin',
            ]);

            // CATATAN-AUDIT:
            // Secara default hanya seed akun admin agar komposisi automation load
            // tetap tepat 100 non-admin (10 mitra, 10 penjual, 10 affiliate, 70 buyer).
            if (! (bool) config('demo.seed_legacy_users', false)) {
                return;
            }

            // =========================
            // MITRA (akun khusus, bukan buyer)
            // =========================
            $mitraId = $this->upsertUser([
                'name' => 'Mitra Toko Sumber Rejeki',
                'email' => 'mitra@demo.test',
                'password' => Hash::make('password'),
                'phone_number' => '082222222222',
                'role' => 'mitra',
            ]);

            // Mitra profile (aktif)
            $this->upsertMitraProfile($mitraId, [
                'store_name' => 'Toko Sumber Rejeki',
                'store_address' => 'Jl. Pasar Baru No. 1',
                'region_id' => 1101,
                'is_active' => true,
                'wallet_balance' => 0,
            ]);

            // Mitra application (approved)
            $this->upsertMitraApplication($mitraId, [
                'full_name' => 'Mitra Toko Sumber Rejeki',
                'email' => 'mitra@demo.test',
                'region_id' => 1101,
                'ktp_url' => 'uploads/demo/mitra/ktp.jpg',
                'npwp_url' => 'uploads/demo/mitra/npwp.jpg',
                'nib_url' => 'uploads/demo/mitra/nib.jpg',
                'warehouse_address' => 'Gudang Mitra - Jl. Gudang No. 10',
                'warehouse_lat' => -7.7955800,
                'warehouse_lng' => 110.3694900,
                'warehouse_building_photo_url' => 'uploads/demo/mitra/gudang.jpg',
                'products_managed' => 'Beras, Gula, Minyak Goreng',
                'warehouse_capacity' => 5000,
                'special_certification_url' => null,
                'status' => 'approved',
                'decided_by' => $adminId,
                'notes' => 'Approved demo',
            ]);

            // =========================
            // PETANI PENJUAL (P2P seller)
            // =========================
            $petaniPenjualId = $this->upsertUser([
                'name' => 'Petani Penjual',
                'email' => 'petani.penjual@demo.test',
                'password' => Hash::make('password'),
                'phone_number' => '083333333333',
                'role' => 'consumer',
            ]);

            $this->upsertConsumerProfile($petaniPenjualId, [
                'address' => 'Desa Makmur, RT 01/RW 02',
                'mode' => 'buyer',
                'mode_status' => 'pending',
                'requested_mode' => 'farmer_seller',
            ]);

            // rekening (dipakai untuk pencairan P2P)
            $this->upsertFarmerProfile($petaniPenjualId, [
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_holder' => 'Petani Penjual',
            ]);

            // seller application (approved)
            $this->upsertFarmerSellerApplication($petaniPenjualId, [
                'full_name' => 'Petani Penjual',
                'email' => 'petani.penjual@demo.test',
                'region_id' => 1101,
                'ktp_url' => 'uploads/demo/petani_penjual/ktp.jpg',
                'selfie_url' => 'uploads/demo/petani_penjual/selfie.jpg',
                'bankbook_photo_url' => 'uploads/demo/petani_penjual/buku_tabungan.jpg',
                'land_location' => 'Lahan Petani - Desa Makmur Blok A',
                'land_lat' => -7.8000000,
                'land_lng' => 110.3600000,
                'land_photo_url' => 'uploads/demo/petani_penjual/lahan.jpg',
                'estimated_land_area' => 1.50, // contoh (ha)
                'main_commodities' => json_encode(['cabai', 'bawang', 'padi']),
                'pickup_address' => 'Rumah Petani - Desa Makmur RT 01/RW 02',
                'has_scale' => true,
                'status' => 'pending',
                'decided_by' => null,
                'notes' => 'Menunggu review admin',
            ]);

            // contoh produk panen agar P2P “jalan”
            $this->upsertFarmerHarvest([
                'farmer_id' => $petaniPenjualId,
                'name' => 'Cabai Rawit 1kg',
                'description' => 'Cabai segar hasil panen sendiri.',
                'price' => 35000,
                'stock_qty' => 50,
                'harvest_date' => now()->toDateString(),
                'image_url' => null,
                'status' => 'approved',
            ]);

            // =========================
            // PETANI AFFILIATE (khusus affiliate, tidak bisa jadi penjual)
            // =========================
            $petaniAffiliateId = $this->upsertUser([
                'name' => 'Petani Affiliate',
                'email' => 'petani.affiliate@demo.test',
                'password' => Hash::make('password'),
                'phone_number' => '084444444444',
                'role' => 'consumer',
            ]);

            $this->upsertConsumerProfile($petaniAffiliateId, [
                'address' => 'Desa Sejahtera, RT 03/RW 01',
                'mode' => 'buyer',
                'mode_status' => 'pending',
                'requested_mode' => 'affiliate',
            ]);

            // affiliate application (approved, bisa auto-approve kalau kamu cek transaksi)
            $this->upsertAffiliateApplication($petaniAffiliateId, [
                'full_name' => 'Petani Affiliate',
                'email' => 'petani.affiliate@demo.test',
                'region_id' => 1101,
                'ktp_url' => 'uploads/demo/petani_affiliate/ktp.jpg',
                'selfie_url' => 'uploads/demo/petani_affiliate/selfie.jpg',
                'bank_name' => 'BRI',
                'account_number' => '9876543210',
                'account_holder' => 'Petani Affiliate',
                'is_auto_approved' => false,
                'status' => 'pending',
                'decided_by' => null,
                'notes' => 'Menunggu review admin',
            ]);

            // =========================
            // PETANI/CONSUMER (buyer biasa)
            // =========================
            $petaniConsumerId = $this->upsertUser([
                'name' => 'Petani Consumer',
                'email' => 'petani.consumer@demo.test',
                'password' => Hash::make('password'),
                'phone_number' => '085555555555',
                'role' => 'consumer',
            ]);

            $this->upsertConsumerProfile($petaniConsumerId, [
                'address' => 'Kampung Tani, RT 05/RW 04',
                'mode' => 'buyer',
                'mode_status' => 'none',
                'requested_mode' => null,
            ]);

            // Pastikan state demo untuk uji konfirmasi admin konsisten:
            // - penjual/affiliate masih pending (belum otomatis approved)
            // - buyer biasa tetap none
            DB::table('consumer_profiles')->where('user_id', $petaniPenjualId)->update([
                'mode' => 'buyer',
                'mode_status' => 'pending',
                'requested_mode' => 'farmer_seller',
                'updated_at' => now(),
            ]);
            DB::table('consumer_profiles')->where('user_id', $petaniAffiliateId)->update([
                'mode' => 'buyer',
                'mode_status' => 'pending',
                'requested_mode' => 'affiliate',
                'updated_at' => now(),
            ]);
            DB::table('consumer_profiles')->where('user_id', $petaniConsumerId)->update([
                'mode' => 'buyer',
                'mode_status' => 'none',
                'requested_mode' => null,
                'updated_at' => now(),
            ]);

            // =========================
            // contoh produk mitra untuk landing page
            // =========================
            $this->upsertStoreProduct([
                'mitra_id' => $mitraId,
                'name' => 'Beras Premium 5kg',
                'description' => 'Beras premium kualitas bagus.',
                'price' => 78000,
                'stock_qty' => 100,
                'image_url' => null,
                'is_affiliate_enabled' => true,
                'affiliate_commission' => 5000,
                'affiliate_expire_date' => now()->addDays(30)->toDateString(),
            ]);
        });
    }

    // ===================================================
    // HELPERS (UPSERT)
    // ===================================================
    private function upsertUser(array $data): int
    {
        $now = now();
        $existing = DB::table('users')->where('email', $data['email'])->first();

        if ($existing) {
            DB::table('users')->where('id', $existing->id)->update([
                'name' => $data['name'],
                'password' => $data['password'],
                'phone_number' => $data['phone_number'] ?? null,
                'role' => $data['role'] ?? 'consumer',
                'updated_at' => $now,
            ]);
            return (int) $existing->id;
        }

        return (int) DB::table('users')->insertGetId([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone_number' => $data['phone_number'] ?? null,
            'role' => $data['role'] ?? 'consumer',
            'email_verified_at' => $data['email_verified_at'] ?? null,
            'remember_token' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function upsertConsumerProfile(int $userId, array $data): void
    {
        $now = now();
        $exists = DB::table('consumer_profiles')->where('user_id', $userId)->exists();

        $payload = [
            'address' => $data['address'] ?? null,
            'mode' => $data['mode'] ?? 'buyer',
            'mode_status' => $data['mode_status'] ?? 'none',
            'requested_mode' => $data['requested_mode'] ?? null,
            'updated_at' => $now,
        ];

        if ($exists) {
            DB::table('consumer_profiles')->where('user_id', $userId)->update($payload);
        } else {
            DB::table('consumer_profiles')->insert($payload + [
                'user_id' => $userId,
                'created_at' => $now,
            ]);
        }
    }

    private function upsertMitraProfile(int $userId, array $data): void
    {
        $now = now();
        $exists = DB::table('mitra_profiles')->where('user_id', $userId)->exists();

        $payload = [
            'store_name' => $data['store_name'],
            'store_address' => $data['store_address'],
            'region_id' => $data['region_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'wallet_balance' => $data['wallet_balance'] ?? 0,
            'updated_at' => $now,
        ];

        if ($exists) {
            DB::table('mitra_profiles')->where('user_id', $userId)->update($payload);
        } else {
            DB::table('mitra_profiles')->insert($payload + [
                'user_id' => $userId,
                'created_at' => $now,
            ]);
        }
    }

    private function upsertFarmerProfile(int $userId, array $data): void
    {
        $now = now();
        $exists = DB::table('farmer_profiles')->where('user_id', $userId)->exists();

        $payload = [
            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'account_holder' => $data['account_holder'] ?? null,
            'updated_at' => $now,
        ];

        if ($exists) {
            DB::table('farmer_profiles')->where('user_id', $userId)->update($payload);
        } else {
            DB::table('farmer_profiles')->insert($payload + [
                'user_id' => $userId,
                'created_at' => $now,
            ]);
        }
    }

    private function upsertMitraApplication(int $userId, array $data): void
    {
        $now = now();
        $exists = DB::table('mitra_applications')->where('user_id', $userId)->exists();

        $payload = [
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'region_id' => $data['region_id'] ?? null,
            'ktp_url' => $data['ktp_url'] ?? null,
            'npwp_url' => $data['npwp_url'] ?? null,
            'nib_url' => $data['nib_url'] ?? null,
            'warehouse_address' => $data['warehouse_address'] ?? null,
            'warehouse_lat' => $data['warehouse_lat'] ?? null,
            'warehouse_lng' => $data['warehouse_lng'] ?? null,
            'warehouse_building_photo_url' => $data['warehouse_building_photo_url'] ?? null,
            'products_managed' => $data['products_managed'] ?? null,
            'warehouse_capacity' => $data['warehouse_capacity'] ?? null,
            'special_certification_url' => $data['special_certification_url'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'decided_by' => $data['decided_by'] ?? null,
            'decided_at' => ($data['status'] ?? 'pending') !== 'pending' ? $now : null,
            'notes' => $data['notes'] ?? null,
            'updated_at' => $now,
        ];

        if ($exists) {
            DB::table('mitra_applications')->where('user_id', $userId)->update($payload);
        } else {
            DB::table('mitra_applications')->insert($payload + [
                'user_id' => $userId,
                'created_at' => $now,
            ]);
        }
    }

    private function upsertAffiliateApplication(int $userId, array $data): void
    {
        $now = now();
        $exists = DB::table('affiliate_applications')->where('user_id', $userId)->exists();

        $payload = [
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'region_id' => $data['region_id'] ?? null,
            'ktp_url' => $data['ktp_url'] ?? null,
            'selfie_url' => $data['selfie_url'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'account_holder' => $data['account_holder'] ?? null,
            'is_auto_approved' => (bool) ($data['is_auto_approved'] ?? false),
            'status' => $data['status'] ?? 'pending',
            'decided_by' => $data['decided_by'] ?? null,
            'decided_at' => ($data['status'] ?? 'pending') !== 'pending' ? $now : null,
            'notes' => $data['notes'] ?? null,
            'updated_at' => $now,
        ];

        if ($exists) {
            DB::table('affiliate_applications')->where('user_id', $userId)->update($payload);
        } else {
            DB::table('affiliate_applications')->insert($payload + [
                'user_id' => $userId,
                'created_at' => $now,
            ]);
        }
    }

    private function upsertFarmerSellerApplication(int $userId, array $data): void
    {
        $now = now();
        $exists = DB::table('farmer_seller_applications')->where('user_id', $userId)->exists();

        $payload = [
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'region_id' => $data['region_id'] ?? null,
            'ktp_url' => $data['ktp_url'] ?? null,
            'selfie_url' => $data['selfie_url'] ?? null,
            'bankbook_photo_url' => $data['bankbook_photo_url'] ?? null,
            'land_location' => $data['land_location'] ?? null,
            'land_lat' => $data['land_lat'] ?? null,
            'land_lng' => $data['land_lng'] ?? null,
            'land_photo_url' => $data['land_photo_url'] ?? null,
            'estimated_land_area' => $data['estimated_land_area'] ?? null,
            'main_commodities' => $data['main_commodities'] ?? null,
            'pickup_address' => $data['pickup_address'] ?? null,
            'has_scale' => (bool) ($data['has_scale'] ?? false),
            'status' => $data['status'] ?? 'pending',
            'decided_by' => $data['decided_by'] ?? null,
            'decided_at' => ($data['status'] ?? 'pending') !== 'pending' ? $now : null,
            'notes' => $data['notes'] ?? null,
            'updated_at' => $now,
        ];

        if ($exists) {
            DB::table('farmer_seller_applications')->where('user_id', $userId)->update($payload);
        } else {
            DB::table('farmer_seller_applications')->insert($payload + [
                'user_id' => $userId,
                'created_at' => $now,
            ]);
        }
    }

    private function upsertFarmerHarvest(array $data): int
    {
        $now = now();
        $existing = DB::table('farmer_harvests')
            ->where('farmer_id', $data['farmer_id'])
            ->where('name', $data['name'])
            ->first();

        $payload = [
            'farmer_id' => $data['farmer_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'stock_qty' => $data['stock_qty'] ?? 0,
            'harvest_date' => $data['harvest_date'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'status' => $data['status'] ?? 'approved',
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::table('farmer_harvests')->where('id', $existing->id)->update($payload);
            return (int) $existing->id;
        }

        $payload['created_at'] = $now;
        return (int) DB::table('farmer_harvests')->insertGetId($payload);
    }

    private function upsertStoreProduct(array $data): int
    {
        $now = now();
        $existing = DB::table('store_products')
            ->where('mitra_id', $data['mitra_id'])
            ->where('name', $data['name'])
            ->first();

        $payload = [
            'mitra_id' => $data['mitra_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'stock_qty' => $data['stock_qty'] ?? 0,
            'image_url' => $data['image_url'] ?? null,
            'is_affiliate_enabled' => (bool) ($data['is_affiliate_enabled'] ?? false),
            'affiliate_commission' => $data['affiliate_commission'] ?? 0,
            'affiliate_expire_date' => $data['affiliate_expire_date'] ?? null,
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::table('store_products')->where('id', $existing->id)->update($payload);
            return (int) $existing->id;
        }

        $payload['created_at'] = $now;
        return (int) DB::table('store_products')->insertGetId($payload);
    }
}
