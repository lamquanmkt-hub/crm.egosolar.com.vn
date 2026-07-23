<?php
namespace App\Models\ProjectTest;
use Illuminate\Database\Eloquent\Model;
class Proposal extends Model
{
    protected $table = 'project_test_proposals';
    protected $guarded = [];
    protected $casts = ['preliminary_materials_json' => 'array', 'sales_confirmed_at' => 'datetime', 'proposed_kwp' => 'decimal:2'];
    public function project(){ return $this->belongsTo(Project::class, 'project_id'); }
}
