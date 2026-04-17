<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\MitraApplicationStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminMitraApplicationReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_pending_mitra_application(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $consumer = User::factory()->create([
            'role' => 'consumer',
            'email' => 'approve.mitra@example.test',
        ]);

        DB::table('mitra_applications')->insert([
            'user_id' => $consumer->id,
            'full_name' => 'Mitra Approve',
            'email' => $consumer->email,
            'region_id' => 1101,
            'ktp_url' => 'uploads/test/ktp.pdf',
            'npwp_url' => 'uploads/test/npwp.pdf',
            'nib_url' => 'uploads/test/nib.pdf',
            'warehouse_address' => 'Gudang Approve',
            'warehouse_lat' => -7.81,
            'warehouse_lng' => 110.36,
            'warehouse_building_photo_url' => 'uploads/test/gudang.jpg',
            'products_managed' => 'Pupuk',
            'warehouse_capacity' => 5000,
            'special_certification_url' => null,
            'status' => 'pending',
            'submitted_at' => now(),
            'decided_by' => null,
            'decided_at' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $application = DB::table('mitra_applications')->where('user_id', $consumer->id)->first();

        $response = $this->actingAs($admin)
            ->post(route('admin.mitraApplications.review', ['applicationId' => $application->id]), [
                'decision' => 'approved',
                'notes' => 'Data usaha valid dan layak operasional.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('mitra_applications', [
            'id' => $application->id,
            'status' => 'approved',
            'decided_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $consumer->id,
            'role' => 'mitra',
        ]);

        $this->assertDatabaseHas('mitra_profiles', [
            'user_id' => $consumer->id,
            'store_name' => 'Mitra Approve',
        ]);

        $this->assertDatabaseMissing('consumer_profiles', [
            'user_id' => $consumer->id,
            'mode' => 'farmer_seller',
            'mode_status' => 'approved',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $consumer->id,
            'type' => MitraApplicationStatusNotification::class,
        ]);
    }

    public function test_admin_can_reject_pending_mitra_application(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $consumer = User::factory()->create([
            'role' => 'consumer',
            'email' => 'reject.mitra@example.test',
        ]);

        DB::table('mitra_applications')->insert([
            'user_id' => $consumer->id,
            'full_name' => 'Mitra Reject',
            'email' => $consumer->email,
            'region_id' => 1101,
            'ktp_url' => 'uploads/test/ktp.pdf',
            'npwp_url' => 'uploads/test/npwp.pdf',
            'nib_url' => 'uploads/test/nib.pdf',
            'warehouse_address' => 'Gudang Reject',
            'warehouse_lat' => -7.81,
            'warehouse_lng' => 110.36,
            'warehouse_building_photo_url' => 'uploads/test/gudang.jpg',
            'products_managed' => 'Pupuk',
            'warehouse_capacity' => 5000,
            'special_certification_url' => null,
            'status' => 'pending',
            'submitted_at' => now(),
            'decided_by' => null,
            'decided_at' => null,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $application = DB::table('mitra_applications')->where('user_id', $consumer->id)->first();

        $response = $this->actingAs($admin)
            ->post(route('admin.mitraApplications.review', ['applicationId' => $application->id]), [
                'decision' => 'rejected',
                'notes' => 'Dokumen NPWP tidak valid.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('mitra_applications', [
            'id' => $application->id,
            'status' => 'rejected',
            'decided_by' => $admin->id,
            'notes' => 'Dokumen NPWP tidak valid.',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $consumer->id,
            'role' => 'consumer',
        ]);

        $this->assertDatabaseMissing('consumer_profiles', [
            'user_id' => $consumer->id,
            'mode_status' => 'rejected',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $consumer->id,
            'type' => MitraApplicationStatusNotification::class,
        ]);
    }
}
