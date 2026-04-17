<?php

namespace Tests\Feature\Consumer;

use App\Models\User;
use App\Support\MitraApplicationStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MitraApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumer_can_save_draft_mitra_application_when_submission_open(): void
    {
        Storage::fake('public');

        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        DB::table('feature_flags')->insert([
            'key' => 'accept_mitra',
            'is_enabled' => true,
            'description' => 'Pengajuan mitra dibuka.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $entryUrl = URL::temporarySignedRoute('program.mitra.entry', now()->addMinutes(5));
        $this->actingAs($consumer)->get($entryUrl)->assertRedirect(route('program.mitra.form'));

        $response = $this->actingAs($consumer)
            ->post(route('program.mitra.storeOrSubmit'), [
                'action' => 'draft',
                'full_name' => 'Mitra Draft',
                'email' => 'draft.mitra@example.test',
                'warehouse_address' => 'Gudang A',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('mitra_applications', [
            'user_id' => $consumer->id,
            'full_name' => 'Mitra Draft',
            'status' => 'draft',
        ]);
    }

    public function test_consumer_with_whitespace_role_can_access_mitra_program_entry(): void
    {
        $consumer = User::factory()->create([
            'role' => ' Consumer ',
        ]);

        DB::table('feature_flags')->insert([
            'key' => 'accept_mitra',
            'is_enabled' => true,
            'description' => 'Pengajuan mitra dibuka.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $entryUrl = URL::temporarySignedRoute('program.mitra.entry', now()->addMinutes(5));
        $this->actingAs($consumer)
            ->get($entryUrl)
            ->assertRedirect(route('program.mitra.form'));
    }

    public function test_consumer_can_submit_mitra_application_with_documents(): void
    {
        Storage::fake('public');

        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        DB::table('feature_flags')->insert([
            'key' => 'accept_mitra',
            'is_enabled' => true,
            'description' => 'Pengajuan mitra dibuka.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $entryUrl = URL::temporarySignedRoute('program.mitra.entry', now()->addMinutes(5));
        $this->actingAs($consumer)->get($entryUrl)->assertRedirect(route('program.mitra.form'));

        $response = $this->actingAs($consumer)
            ->post(route('program.mitra.storeOrSubmit'), [
                'action' => 'submit',
                'full_name' => 'Mitra Submit',
                'email' => 'submit.mitra@example.test',
                'region_id' => 1101,
                'warehouse_address' => 'Gudang Utama No. 1',
                'warehouse_lat' => -7.8000000,
                'warehouse_lng' => 110.3600000,
                'products_managed' => 'Pupuk, benih, alat panen',
                'warehouse_capacity' => 6000,
                'ktp_file' => UploadedFile::fake()->create('ktp.pdf', 50),
                'npwp_file' => UploadedFile::fake()->create('npwp.pdf', 50),
                'nib_file' => UploadedFile::fake()->create('nib.pdf', 50),
                'warehouse_photo_file' => UploadedFile::fake()->create('gudang.jpg', 50, 'image/jpeg'),
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $record = DB::table('mitra_applications')
            ->where('user_id', $consumer->id)
            ->first();

        $this->assertNotNull($record);
        $this->assertSame('pending', $record->status);
        $this->assertNotNull($record->submitted_at);
        $this->assertNotEmpty($record->ktp_url);
        $this->assertNotEmpty($record->npwp_url);
        $this->assertNotEmpty($record->nib_url);
        $this->assertNotEmpty($record->warehouse_building_photo_url);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $consumer->id,
            'type' => MitraApplicationStatusNotification::class,
        ]);

        $this->assertDatabaseMissing('consumer_profiles', [
            'user_id' => $consumer->id,
            'requested_mode' => 'farmer_seller',
            'mode_status' => 'pending',
        ]);
    }

    public function test_consumer_cannot_submit_mitra_application_when_submission_closed(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        DB::table('feature_flags')->insert([
            'key' => 'accept_mitra',
            'is_enabled' => false,
            'description' => 'Pengajuan mitra ditutup.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($consumer)
            ->withSession([
                'mitra_program_access_until' => now()->addMinutes(5)->timestamp,
            ])
            ->post(route('program.mitra.storeOrSubmit'), [
                'action' => 'submit',
                'full_name' => 'Mitra Closed',
                'email' => 'closed.mitra@example.test',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('mitra_application');

        $this->assertDatabaseMissing('mitra_applications', [
            'user_id' => $consumer->id,
        ]);
    }
}
