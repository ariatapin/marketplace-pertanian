<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'mitra_id',
        'source_admin_product_id',
        'name',
        'description',
        'price',
        'unit',
        'stock_qty',
        'image_url',
        'is_active',
        'reactivation_available_at',
        'is_affiliate_enabled',
        'affiliate_commission',
        'affiliate_expire_date',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'reactivation_available_at' => 'datetime',
        'is_affiliate_enabled' => 'boolean',
        'affiliate_commission' => 'float',
        'affiliate_expire_date' => 'date',
    ];
    
    // Relasi ke User (Pemilik Toko)
    public function mitra()
    {
        return $this->belongsTo(User::class, 'mitra_id');
    }

    public function galleryImages()
    {
        return $this->hasMany(StoreProductImage::class, 'store_product_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
