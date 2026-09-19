<?php

declare(strict_types=1);

namespace App\Models\CRM\Consignments;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerConsignmentActivity extends Model
{
    protected $table = 'customer_consignment_activities';

    protected $fillable = [
        'consignment_id', 'release_id', 'action', 'from_status', 'to_status', 'payload', 'user_id',
    ];

    protected $casts = ['payload' => 'array'];

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(CustomerConsignment::class, 'consignment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
