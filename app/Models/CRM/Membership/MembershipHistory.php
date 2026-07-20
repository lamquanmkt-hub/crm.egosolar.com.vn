<?php

namespace App\Models\CRM\Membership;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipHistory extends Model
{
    use HasFactory;

    protected $table = 'crm_membership_history';

    protected $fillable = [
        'membership_id',
        'action_type',
        'from_package_id',
        'to_package_id',
        'amount_paid',
        'note',
        'processed_by',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
    ];

    // Membership
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    // Gói trước đó
    public function fromPackage(): BelongsTo
    {
        return $this->belongsTo(MembershipPackage::class, 'from_package_id');
    }

    // Gói mới
    public function toPackage(): BelongsTo
    {
        return $this->belongsTo(MembershipPackage::class, 'to_package_id');
    }

    // Người xử lý
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
