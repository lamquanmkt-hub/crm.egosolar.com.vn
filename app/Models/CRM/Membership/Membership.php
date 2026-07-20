<?php

namespace App\Models\CRM\Membership;

use App\Models\CRM\Customers\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Membership extends Model
{
    use HasFactory;

    protected $table = 'crm_memberships';

    protected $fillable = [
        'customer_id',
        'package_id',
        'membership_code',
        'start_date',
        'end_date',
        'status',
        'points',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'points' => 'integer',
    ];

    // Khách hàng
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // Gói hội viên
    public function package(): BelongsTo
    {
        return $this->belongsTo(MembershipPackage::class, 'package_id');
    }

    // Người tạo
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Lịch sử
    public function history(): HasMany
    {
        return $this->hasMany(MembershipHistory::class, 'membership_id');
    }

    // Điểm tích lũy
    public function pointsHistory(): HasMany
    {
        return $this->hasMany(MembershipPoint::class, 'membership_id');
    }
}
