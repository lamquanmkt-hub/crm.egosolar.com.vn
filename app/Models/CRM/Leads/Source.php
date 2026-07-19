<?php
namespace App\Models\CRM\Leads;
use App\Models\CRM;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Source extends Model
{
	protected $table = 'crm_sources';
	protected $fillable = ['name', 'description'];
	public function leads(): HasMany
    {
		return $this->hasMany(CRM\Leads\Lead::class, 'source_id');
	}
}
