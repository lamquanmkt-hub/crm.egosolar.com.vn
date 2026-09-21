<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Media\MediaRelation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    /**
     * Quyền chuyên biệt cho phép sửa/xóa bản ghi tài chính đã hoàn tất
     * (ĐNTT đã duyệt/đã chi, công nợ/đợt thanh toán đã hoàn thành).
     * Admin (Giám đốc) luôn có quyền này qua role, không cần gán riêng.
     */
    public const PERMISSION_OVERRIDE_LOCKED_FINANCE = 'payment_requests.override_locked';

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'department_id',
        'position_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Xác định Admin / Giám đốc (cùng một vai trò trong hệ thống này).
     *
     * Gom về một chỗ đúng logic đang dùng rải rác ở các controller
     * (Spatie role theo config `role_permissions.admin_roles`, cột `role`
     * legacy, cột `is_admin` legacy) để không tạo ra cơ chế phân quyền mới.
     */
    public function isAdmin(): bool
    {
        try {
            if ($this->hasAnyRole(config('role_permissions.admin_roles', ['admin']))) {
                return true;
            }
        } catch (\Throwable) {
            // Bảng role/permission chưa sẵn sàng: rơi xuống các cách kiểm tra legacy.
        }

        return (($this->role ?? null) === 'admin')
            || ((int) ($this->is_admin ?? 0) === 1);
    }

    /**
     * Được phép sửa/xóa bản ghi tài chính đã hoàn tất.
     *
     * Admin luôn được (yêu cầu nghiệp vụ); nhân sự khác chỉ được khi
     * được gán riêng permission `payment_requests.override_locked`.
     */
    public function canOverrideLockedFinanceRecords(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        try {
            return (bool) $this->can(self::PERMISSION_OVERRIDE_LOCKED_FINANCE);
        } catch (\Throwable) {
            return false;
        }
    }

    public function leadsAssigned(): HasMany
    {
        return $this->hasMany(CRM\Leads\Lead::class, 'assigned_to');
    }

    public function leadsCreated(): HasMany
    {
        return $this->hasMany(CRM\Leads\Lead::class, 'created_by');
    }

    public function ordersCreated(): HasMany
    {
        return $this->hasMany(CRM\Orders\Order::class, 'created_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(CRM\Orders\OrderApproval::class, 'approved_by');
    }

    public function avatar(): MorphOne
    {
        return $this->morphOne(MediaRelation::class, 'model')
            ->where('usage_type', 'avatar');
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(System\Conversation::class)
            ->withTimestamps()
            ->withPivot('last_read_at');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Department::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Position::class, 'position_id');
    }
}
