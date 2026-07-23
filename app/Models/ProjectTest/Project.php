<?php

namespace App\Models\ProjectTest;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $table = 'project_test_projects';

    protected $guarded = [];

    protected $casts = [
        'proposed_survey_at' => 'datetime',
        'survey_confirmed_at' => 'datetime',
        'proposed_installation_at' => 'datetime',
        'installation_confirmed_at' => 'datetime',
        'target_completion_at' => 'date',
        'estimated_kwp' => 'decimal:2',
        'progress' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function salesUser()
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }

    public function technicalManager()
    {
        return $this->belongsTo(User::class, 'technical_manager_id');
    }

    public function leadTechnician()
    {
        return $this->belongsTo(User::class, 'lead_technician_id');
    }

    public function survey()
    {
        return $this->hasOne(Survey::class, 'project_id');
    }

    public function proposal()
    {
        return $this->hasOne(Proposal::class, 'project_id');
    }

    public function materialRequests()
    {
        return $this->hasMany(MaterialRequest::class, 'project_id')->latest();
    }

    public function latestMaterialRequest()
    {
        return $this->hasOne(MaterialRequest::class, 'project_id')->latestOfMany();
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class, 'project_id');
    }

    public function dailyLogs()
    {
        return $this->hasMany(DailyLog::class, 'project_id')->latest('log_date')->latest('id');
    }

    public function acceptance()
    {
        return $this->hasOne(Acceptance::class, 'project_id');
    }

    public function warranty()
    {
        return $this->hasOne(Warranty::class, 'project_id');
    }

    public function histories()
    {
        return $this->hasMany(History::class, 'project_id')->latest();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['admin', 'management', 'technical_manager', 'sales_manager'])) {
            return $query;
        }

        if ($user->hasAnyRole(['warehouse', 'kho'])) {
            return $query->whereHas('materialRequests', function (Builder $q) {
                $q->whereIn('status', ['approved', 'preparing', 'issued']);
            });
        }

        if ($user->hasRole('sales')) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('sales_user_id', $user->id)
                    ->orWhere('created_by', $user->id);
            });
        }

        if ($user->hasRole('ky_thuat')) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('lead_technician_id', $user->id)
                    ->orWhere('technical_manager_id', $user->id)
                    ->orWhereHas('assignments', fn (Builder $a) => $a->where('user_id', $user->id));
            });
        }

        return $query->whereRaw('1 = 0');
    }
}
