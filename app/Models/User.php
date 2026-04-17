<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'google_avatar',
        'avatar_path',
        'phone_number',
        'role',
        'is_suspended',
        'suspended_at',
        'suspension_note',
        // WILAYAH
        'province_id',
        'city_id',
        'district_id',
        // LOKASI CUACA
        'lat',
        'lng',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_suspended' => 'boolean',
        'suspended_at' => 'datetime',
    ];

    public function consumerProfile()
    {
        return $this->hasOne(\App\Models\ConsumerProfile::class, 'user_id', 'id');
    }

    public function mitraProfile()
    {
        return $this->hasOne(\App\Models\MitraProfile::class, 'user_id', 'id');
    }

    public function farmerProfile()
    {
        return $this->hasOne(\App\Models\FarmerProfile::class, 'user_id', 'id');
    }

    public function isAdmin(): bool
    {
        return $this->isRole('admin');
    }

    public function isMitra(): bool
    {
        return $this->isRole('mitra');
    }

    public function isConsumer(): bool
    {
        return $this->isRole('consumer');
    }

    public function normalizedRole(): string
    {
        return self::normalizeRoleValue((string) $this->role);
    }

    public function isRole(string $role): bool
    {
        return $this->normalizedRole() === self::normalizeRoleValue($role);
    }

    public static function normalizeRoleValue(?string $role): string
    {
        return strtolower(trim((string) $role));
    }

    public function scopeWhereNormalizedRole(Builder $query, string $role): Builder
    {
        return $query->whereRaw('LOWER(TRIM(role)) = ?', [self::normalizeRoleValue($role)]);
    }

    /**
     * @param array<int, string> $roles
     */
    public function scopeWhereInNormalizedRoles(Builder $query, array $roles): Builder
    {
        $normalizedRoles = collect($roles)
            ->map(fn ($role) => self::normalizeRoleValue((string) $role))
            ->filter(fn ($role) => $role !== '')
            ->unique()
            ->values()
            ->all();

        if (count($normalizedRoles) === 0) {
            return $query->whereRaw('1 = 0');
        }

        $placeholders = implode(', ', array_fill(0, count($normalizedRoles), '?'));

        return $query->whereRaw("LOWER(TRIM(role)) IN ({$placeholders})", $normalizedRoles);
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function avatarImageUrl(): ?string
    {
        $customAvatarPath = trim((string) ($this->avatar_path ?? ''));
        if ($customAvatarPath !== '') {
            if (Str::startsWith($customAvatarPath, ['http://', 'https://', '/storage/', 'storage/'])) {
                return $customAvatarPath;
            }

            return asset('storage/' . ltrim($customAvatarPath, '/'));
        }

        $googleAvatarUrl = trim((string) ($this->google_avatar ?? ''));

        return $googleAvatarUrl !== '' ? $googleAvatarUrl : null;
    }

    public function avatarInitial(): string
    {
        $source = trim((string) ($this->email ?? ''));
        if ($source === '') {
            $source = trim((string) ($this->name ?? ''));
        }

        $initial = strtoupper((string) Str::substr($source, 0, 1));

        return $initial !== '' ? $initial : 'U';
    }
}
