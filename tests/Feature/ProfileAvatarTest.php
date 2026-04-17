<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_profile_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'role' => 'consumer',
        ]);

        $response = $this->actingAs($user)->post(route('profile.avatar.update'), [
            'avatar' => $this->fakePng('avatar.png'),
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('status', 'Foto profil berhasil diperbarui.');

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists((string) $user->avatar_path);
    }

    public function test_user_can_delete_uploaded_profile_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'role' => 'consumer',
            'google_avatar' => 'https://example.com/google-avatar.png',
        ]);

        $avatarPath = $this->fakePng('avatar-old.png')
            ->store('avatars/users/' . $user->id, 'public');

        $user->forceFill([
            'avatar_path' => $avatarPath,
        ])->save();

        $response = $this->actingAs($user)->delete(route('profile.avatar.destroy'));

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('status', 'Foto profil dihapus.');

        $user->refresh();
        $this->assertNull($user->avatar_path);
        $this->assertSame('https://example.com/google-avatar.png', $user->avatarImageUrl());
        Storage::disk('public')->assertMissing($avatarPath);
    }

    public function test_avatar_initial_uses_email_when_no_avatar_is_available(): void
    {
        $user = User::factory()->make([
            'name' => 'Nama User',
            'email' => 'petani@example.com',
            'google_avatar' => null,
            'avatar_path' => null,
        ]);

        $this->assertSame('P', $user->avatarInitial());
        $this->assertNull($user->avatarImageUrl());
    }

    private function fakePng(string $filename): UploadedFile
    {
        $tinyPngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgN+QxkQAAAAASUVORK5CYII=';

        return UploadedFile::fake()->createWithContent($filename, (string) base64_decode($tinyPngBase64, true));
    }
}
