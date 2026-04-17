<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_product_id',
        'image_url',
        'sort_order',
    ];

    public function product()
    {
        return $this->belongsTo(StoreProduct::class, 'store_product_id');
    }
}
