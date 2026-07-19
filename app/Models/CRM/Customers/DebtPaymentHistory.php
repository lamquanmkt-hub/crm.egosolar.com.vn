<?php
namespace App\Models\CRM\Customers;
use App\Models\Payments\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtPaymentHistory extends Model
{
	use HasFactory;
	protected $table = 'crm_debt_payment_history';
	protected $fillable = [
		'debt_id',
		'payment_id',
		'amount',
		'payment_date',
		'note',
		'recorded_by',
	];
	protected $casts = [
		'amount' => 'decimal:2',
		'payment_date' => 'date',
	];
	// Debt
	public function debt(): BelongsTo
	{
		return $this->belongsTo(CustomerDebt::class, 'debt_id');
	}
	// Payment
	public function payment(): BelongsTo
	{
		return $this->belongsTo(Payment::class);
	}
	// Người ghi nhận
	public function recorder(): BelongsTo
	{
		return $this->belongsTo(User::class, 'recorded_by');
	}
}
