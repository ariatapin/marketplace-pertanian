<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Menyimpan cache respons cuaca provider eksternal (khusus BMKG fallback).
 */
class WeatherCache extends Model
{
    protected $fillable = [
        'provider',
        'cache_key',
        'kode_wilayah',
        'payload',
        'fetched_at',
        'valid_until',
    ];

    protected $casts = [
        'payload' => 'array',
        'fetched_at' => 'datetime',
        'valid_until' => 'datetime',
    ];
}

