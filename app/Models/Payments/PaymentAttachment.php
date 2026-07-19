<?php
namespace App\Models\Payments;
use App\Models\Payments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PaymentAttachment extends Model
{
    protected $fillable = [
        'payment_request_id',
        'original_name',
        'path',
        'mime_type',
        'size',
    ];
    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(Payments\PaymentRequest::class);
    }
}
