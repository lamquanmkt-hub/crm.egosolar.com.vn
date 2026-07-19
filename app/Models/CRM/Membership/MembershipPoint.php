<?php
namespace App\Models\CRM\Membership;
use App\Models\CRM\Orders\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MembershipPoint extends Model
{
	use HasFactory;
	protected $table = 'crm_membership_points';
	protected $fillable = [
		'membership_id',
		'points',
		'type',
		'reason',
		'order_id',
		'created_by',
	];
	protected $casts = [
		'points' => 'integer',
	];
	// Membership
	public function membership(): BelongsTo
	{
		return $this->belongsTo(Membership::class);
	}
	// Đơn hàng
	public function order(): BelongsTo
	{
		return $this->belongsTo(Order::class);
	}
	// Người tạo
	public function creator(): BelongsTo
	{
		return $this->belongsTo(User::class, 'created_by');
	}
}
