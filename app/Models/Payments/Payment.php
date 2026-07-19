<?php
namespace App\Models\Payments;
use App\Models\CRM\Orders\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Payment extends Model
{
    use HasFactory;
    protected $table = 'crm_payments';
    protected $fillable = [
        'order_id',
        'payment_date',
        'amount',
        'method_id',
        'recorded_by',
        'note', // ✅ thêm ghi chú
    ];
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'method_id');
    }
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
