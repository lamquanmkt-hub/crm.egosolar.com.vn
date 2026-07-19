<?php
namespace App\Models\Payments;
use Illuminate\Database\Eloquent\Model;
class PaymentRequestApproval extends Model
{
    //
    protected $fillable = [
    'payment_request_id',
    'actor_id',
    'step',
    'action',
    'note',
];
}
