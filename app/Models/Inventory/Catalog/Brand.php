<?php

namespace App\Models\Inventory\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Brand extends Model
{
    protected $table = 'crm_brands';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function booted(): void
    {
        static::saving(function (self $brand) {
            if (blank($brand->slug) && filled($brand->name)) {
                $brand->slug = Str::slug($brand->name);
            }
        });
    }
}
