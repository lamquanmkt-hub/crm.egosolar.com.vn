<?php
namespace App\Models\ProjectTest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
class Assignment extends Model
{
    protected $table = 'project_test_assignments';
    protected $guarded = [];
    protected $casts = ['work_date' => 'date'];
    public function user(){ return $this->belongsTo(User::class); }
    public function project(){ return $this->belongsTo(Project::class, 'project_id'); }
}
