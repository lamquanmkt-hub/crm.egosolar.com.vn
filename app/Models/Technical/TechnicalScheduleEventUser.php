<?php

declare(strict_types=1);

namespace App\Models\Technical;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TechnicalScheduleEventUser extends Model
{
    protected $table = 'technical_schedule_event_users';

    protected $guarded = [];

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
