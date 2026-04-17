<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumerProfile extends Model
{
    protected $table = 'consumer_profiles';
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'address',
        'mode',
        'mode_status',
        'requested_mode',
    ];
}
