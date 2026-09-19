<?php

declare(strict_types=1);

namespace App\Models\Technical;

use App\Models\ProjectTest\Project;
use App\Models\SolarMaintenanceSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TechnicalScheduleEvent extends Model
{
    protected $table = 'technical_schedule_events';

    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'boolean',
        'metadata' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(SolarMaintenanceSchedule::class, 'source_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TechnicalScheduleEventUser::class, 'event_id');
    }

    public function scopeBetween(Builder $query, mixed $from, mixed $to): Builder
    {
        return $query->where('starts_at', '<=', $to)
            ->where(function (Builder $builder) use ($from): void {
                $builder->whereNull('ends_at')->where('starts_at', '>=', $from)
                    ->orWhere('ends_at', '>=', $from);
            });
    }
}
