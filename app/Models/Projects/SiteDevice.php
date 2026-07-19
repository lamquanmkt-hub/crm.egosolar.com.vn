<?php
namespace App\Models\Projects;
use Illuminate\Database\Eloquent\Model;
class SiteDevice extends Model
{
    protected $table = 'site_devices';
    protected $fillable = [
        'site_id','type','brand','model','serial','power_kw','capacity_kwh','qty','warranty_to'
    ];
    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }
}
