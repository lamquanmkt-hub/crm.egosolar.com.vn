<?php
namespace App\Models\ProjectTest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
class DailyLog extends Model
{
    protected $table = 'project_test_daily_logs';
    protected $guarded = [];
    protected $casts = ['log_date' => 'date', 'progress' => 'integer'];
    public function author(){ return $this->belongsTo(User::class, 'created_by'); }
    public function project(){ return $this->belongsTo(Project::class, 'project_id'); }
}
