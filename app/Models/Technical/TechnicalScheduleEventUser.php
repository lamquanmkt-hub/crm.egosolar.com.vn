<?php

declare(strict_types=1);

namespace App\Models\Technical;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TechnicalScheduleEventUser extends Model
{
    protected $table = 'technical_schedule_event_users';

    /*
     * Chuyển từ `$guarded = []` sang allow-list tường minh (đợt vá P0).
     * Nguồn: migration `2026_08_04_231500_create_technical_schedule_workspace`.
     * Hiện tại bảng này chỉ được ghi bằng raw query (DB::table), nên allow-list
     * này là lớp phòng vệ cho các đoạn code Eloquent về sau.
     */
    protected $fillable = [
        'event_id',
        'user_id',
        'assignment_role',
        'status',
        'confirmed_at',
        'check_in_at',
        'check_out_at',
        'note',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(TechnicalScheduleEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
