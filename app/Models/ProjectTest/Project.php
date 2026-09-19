<?php

namespace App\Models\ProjectTest;

use App\Models\User;
use App\Models\CRM\Orders\Order;
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
        'legacy_payload_json' => 'array',
        'contract_amount' => 'decimal:2',
        'amount_collected' => 'decimal:2',
        'extra_revenue' => 'decimal:2',
        'labor_cost' => 'decimal:2',
        'transport_cost' => 'decimal:2',
        'other_cost' => 'decimal:2',
        'financial_updated_at' => 'datetime',
        'installed_at' => 'date',
        'warranty_to' => 'date',
        'imported_from_legacy_at' => 'datetime',
        'customer_confirmation_required' => 'boolean',
        'customer_confirmed_at' => 'datetime',
        'handover_at' => 'datetime',
    ];


    protected static function booted(): void
    {
        static::saved(function (self $project): void {
            app(\App\Services\Technical\TechnicalScheduleSyncService::class)->syncProject($project);
        });

        static::deleted(function (self $project): void {
            app(\App\Services\Technical\TechnicalScheduleSyncService::class)->removeProject($project);
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function salesUser()
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }


    public function salesOrder()
    {
        return $this->belongsTo(Order::class, 'sales_order_id');
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

    public function materialAftercareRequests()
    {
        return $this->hasMany(MaterialAftercareRequest::class, 'project_id')->latest('id');
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

    public function paymentMilestones()
    {
        return $this->hasMany(PaymentMilestone::class, 'project_id')->orderBy('sequence')->orderBy('id');
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class, 'project_id')->latest('paid_at')->latest('id');
    }
    public function paymentAdjustments()
    {
        return $this->hasMany(PaymentAdjustment::class, 'project_id')->latest('id');
    }

    public function expenses()
    {
        return $this->hasMany(ProjectExpense::class, 'project_id')->latest('expense_date')->latest('id');
    }
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        // EGO_TECHNICAL_FULL_PROJECT_VISIBILITY_V2
        // Mọi nhân sự Kỹ thuật được XEM toàn bộ công trình.
        // Phân công chỉ quyết định quyền thực hiện, không quyết định quyền nhìn.
        if ($user->hasAnyRole([
            'ky_thuat',
            'technical',
            'technician',
            'technical_staff',
            'technical_leader',
            'technical_manager',
            'truong_phong_ky_thuat',
            'maintenance',
            'bao_hanh',
        ])) {
            return $query;
        }

        if ($user->hasAnyRole(['admin', 'management', 'manager', 'accounting'])) {
            return $query;
        }

        if ($user->hasRole('sales_manager')) {
            return $query->where('request_source', 'sales');
        }

        if ($user->hasAnyRole(['warehouse', 'kho'])) {
            return $query->whereHas('materialRequests', function (Builder $q) {
                $q->whereIn('status', ['approved', 'preparing', 'issued']);
            });
        }

        if ($user->hasAnyRole(['sales', 'sales_staff'])) {
            return $query->where('request_source', 'sales')
                ->where(function (Builder $q) use ($user) {
                    $q->where('sales_user_id', $user->id)
                        ->orWhere('created_by', $user->id);
                });
        }

        if ($user->hasAnyRole(['cskh', 'customer_service', 'customer_care'])) {
            return $query->where(function (Builder $q) use ($user) {
                $q->whereIn('request_source', ['sales', 'cskh'])
                    ->where(function (Builder $inner) use ($user) {
                        $inner->where('created_by', $user->id)
                            ->orWhere('sales_user_id', $user->id);
                    });
            });
        }


        return $query->whereRaw('1 = 0');
    }
}
