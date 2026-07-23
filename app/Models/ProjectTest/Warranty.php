<?php
namespace App\Models\ProjectTest;
use Illuminate\Database\Eloquent\Model;
class Warranty extends Model
{
    protected $table = 'project_test_warranties';
    protected $guarded = [];
    protected $casts = ['starts_at' => 'date', 'ends_at' => 'date', 'next_maintenance_at' => 'date'];
    public function project(){ return $this->belongsTo(Project::class, 'project_id'); }
}
