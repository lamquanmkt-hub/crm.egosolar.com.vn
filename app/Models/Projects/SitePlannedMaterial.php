<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Model;

class SitePlannedMaterial extends Model
{
    protected $table = 'site_planned_materials';

    protected $fillable = [
        'site_id',
        'product_id',
        'name',
        'unit',
        'qty',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
