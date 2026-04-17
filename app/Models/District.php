<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class District extends Model {
    protected $fillable = ['city_id','name','lat','lng'];
    public function city(){ return $this->belongsTo(City::class); }
}
