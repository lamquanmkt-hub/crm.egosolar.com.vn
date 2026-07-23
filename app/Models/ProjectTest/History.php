<?php
namespace App\Models\ProjectTest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
class History extends Model
{
    protected $table = 'project_test_histories';
    protected $guarded = [];
    protected $casts = ['meta' => 'array'];
    public function user(){ return $this->belongsTo(User::class); }
    public function project(){ return $this->belongsTo(Project::class, 'project_id'); }
}
