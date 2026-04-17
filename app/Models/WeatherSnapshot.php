<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherSnapshot extends Model
{
    protected $fillable = [
        'provider',
        'location_type',
        'location_id',
        'lat',
        'lng',
        'payload',
        'fetched_at',
        'valid_until',
        'kind',
    ];

    protected $casts = [
        'payload' => 'array',
        'fetched_at' => 'datetime',
        'valid_until' => 'datetime',
    ];
}
