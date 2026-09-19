<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SolarMaintenanceChecklistItem extends Model
{
    protected $table = 'solar_maintenance_checklist_items';

    protected $fillable = [
        'maintenance_schedule_id','checklist_template_id','item_key','label','sort_order','is_required',
        'requires_evidence','min_evidence','is_done','note','completed_by','completed_at',
    ];

    protected $casts = [
        'is_done' => 'boolean',
        'is_required' => 'boolean',
        'requires_evidence' => 'boolean',
        'min_evidence' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(SolarMaintenanceSchedule::class, 'maintenance_schedule_id');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SolarMaintenanceChecklistTemplate::class, 'checklist_template_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SolarMaintenanceAttachment::class, 'checklist_item_id');
    }
}
