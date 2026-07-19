<?php
namespace App\Models\Tasks;
use App\Models\CRM\Tasks\SalesTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TaskComment extends Model
{
	use HasFactory;
	protected $table = 'crm_task_comments';
	protected $fillable = [
		'task_id',
		'comment',
		'user_id',
	];
	// Task
	public function task(): BelongsTo
	{
		return $this->belongsTo(SalesTask::class, 'task_id');
	}
	// User
	public function user(): BelongsTo
	{
		return $this->belongsTo(User::class);
	}
}
