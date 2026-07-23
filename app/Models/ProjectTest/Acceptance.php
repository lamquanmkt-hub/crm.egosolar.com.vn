<?php
namespace App\Models\ProjectTest;
use Illuminate\Database\Eloquent\Model;
class Acceptance extends Model
{
    protected $table = 'project_test_acceptances';
    protected $guarded = [];
    protected $casts = ['accepted_at' => 'date', 'checklist_json' => 'array'];
    public function project(){ return $this->belongsTo(Project::class, 'project_id'); }
}
