<?php
namespace App\Models\CRM\Customers;
use App\Models\CRM\Orders\Order;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerDebt extends Model
{
	use HasFactory;
	protected $table = 'crm_customer_debts';
	protected $fillable = [
		'customer_id',
		'order_id',
		'total_amount',
		'paid_amount',
		'due_date',
		'status',
	];
	protected $casts = [
		'total_amount' => 'decimal:2',
		'paid_amount' => 'decimal:2',
		'due_date' => 'date',
	];
	// Khách hàng
	public function customer(): BelongsTo
	{
		return $this->belongsTo(Customer::class);
	}
	// Đơn hàng
	public function order(): BelongsTo
	{
		return $this->belongsTo(Order::class);
	}
	// Lịch sử thanh toán
	public function paymentHistory(): HasMany
	{
		return $this->hasMany(DebtPaymentHistory::class, 'debt_id');
	}
}
