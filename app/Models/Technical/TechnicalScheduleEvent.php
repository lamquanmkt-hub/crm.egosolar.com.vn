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

    /*
     * Chuyển từ `$guarded = []` sang allow-list tường minh (đợt vá P0).
     * Nguồn: migration `2026_08_04_231500_create_technical_schedule_workspace`
     * + điểm updateOrCreate() duy nhất trong TechnicalScheduleSyncService.
     */
    protected $fillable = [
        'company_id',
        'event_type',
        'source_type',
        'source_id',
        'project_id',
        'title',
        'address',
        'starts_at',
        'ends_at',
        'all_day',
        'status',
        'priority',
        'note',
        'metadata',
        'created_by',
    ];

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
