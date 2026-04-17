<?php

namespace Tests\Feature\Consumer;

use App\Models\User;
use App\Support\MitraApplicationStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MitraNotificationReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumer_can_mark_mitra_notifications_as_read(): void
    {
        $consumer = User::factory()->create([
            'role' => 'consumer',
        ]);

        $notificationId = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => MitraApplicationStatusNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $consumer->id,
            'data' => json_encode([
                'status' => 'pending',
                'title' => 'Pengajuan Mitra Sedang Direview',
                'message' => 'Pengajuan Anda sedang diproses admin.',
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($consumer)
            ->post(route('profile.notifications.read'));

        $response->assertRedirect(route('program.mitra.form') . '#mitra-notifications');

        $this->assertDatabaseMissing('notifications', [
            'id' => $notificationId,
            'read_at' => null,
        ]);
    }
}
