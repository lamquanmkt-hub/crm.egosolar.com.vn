<?php

declare(strict_types=1);

namespace App\Services\Workspace;

use App\Models\User;
use App\Support\EgoCompanyScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class WorkspaceBadgeService
{
    public function counts(User $user): array
    {
        return [
            'orders' => $this->safe(fn (): int => $this->pendingOrders()),
            'consignments' => $this->safe(fn (): int => $this->pendingConsignments($user)),
            'payment_requests' => $this->safe(fn (): int => $this->pendingPaymentRequests($user)),
            'proposals' => $this->safe(fn (): int => $this->pendingProposals($user)),
            'tasks' => $this->safe(fn (): int => $this->unfinishedTasks($user)),
            'recruitment' => $this->safe(fn (): int => $this->recruitmentQueue()),
        ];
    }

    private function pendingOrders(): int
    {
        if (! Schema::hasTable('crm_order_approvals')) {
            return 0;
        }

        $query = DB::table('crm_order_approvals');

        if (Schema::hasColumn('crm_order_approvals', 'status')) {
            $query->whereIn(DB::raw('LOWER(status)'), ['pending', 'submitted', 'waiting']);
        }

        if (Schema::hasColumn('crm_order_approvals', 'order_id')) {
            return (int) $query->distinct()->count('order_id');
        }

        return (int) $query->count();
    }

    private function pendingConsignments(User $user): int
    {
        if (! Schema::hasTable('customer_consignments')) {
            return 0;
        }

        $query = DB::table('customer_consignments');
        EgoCompanyScope::applyToQuery($query, 'customer_consignments');

        if (Schema::hasColumn('customer_consignments', 'status')) {
            $query->whereIn(DB::raw('LOWER(status)'), ['pending_approval', 'approved', 'revision_requested']);
        }

        $this->scopePersonalRows($query, 'customer_consignments', $user, ['sales_user_id', 'created_by']);

        return (int) $query->count();
    }

    private function pendingPaymentRequests(User $user): int
    {
        if (! Schema::hasTable('payment_requests')) {
            return 0;
        }

        $query = DB::table('payment_requests');
        EgoCompanyScope::applyToQuery($query, 'payment_requests');

        if (Schema::hasColumn('payment_requests', 'status')) {
            $query->whereIn(DB::raw('LOWER(status)'), [
                'pending',
                'submitted',
                'admin_pending',
                'admin_approved',
                'accounting_pending',
            ]);
        }

        if (Schema::hasColumn('payment_requests', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $this->scopePersonalRows($query, 'payment_requests', $user, ['created_by', 'user_id', 'requester_id']);

        return (int) $query->count();
    }

    private function pendingProposals(User $user): int
    {
        if (! Schema::hasTable('proposals')) {
            return 0;
        }

        $query = DB::table('proposals');
        EgoCompanyScope::applyToQuery($query, 'proposals');

        if (Schema::hasColumn('proposals', 'status')) {
            $query->whereIn(DB::raw('LOWER(status)'), ['pending', 'submitted', 'revision_requested']);
        }

        if (Schema::hasColumn('proposals', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $this->scopePersonalRows($query, 'proposals', $user, ['user_id', 'created_by', 'requester_id']);

        return (int) $query->count();
    }

    private function unfinishedTasks(User $user): int
    {
        if (! Schema::hasTable('tasks') || ! Schema::hasColumn('tasks', 'assignee_id')) {
            return 0;
        }

        $query = DB::table('tasks')->where('assignee_id', $user->getKey());

        if (Schema::hasColumn('tasks', 'status')) {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('status')
                    ->orWhereNotIn(DB::raw('LOWER(status)'), [
                        'approved', 'done', 'completed', 'complete', 'closed', 'cancelled', 'canceled',
                    ]);
            });
        }

        if (Schema::hasColumn('tasks', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return (int) $query->count();
    }

    private function recruitmentQueue(): int
    {
        if (! Schema::hasTable('hr_recruitment_candidates')) {
            return 0;
        }

        $query = DB::table('hr_recruitment_candidates');

        if (Schema::hasColumn('hr_recruitment_candidates', 'status')) {
            $query->whereIn(DB::raw('LOWER(status)'), ['new', 'screening', 'contacted']);
        }

        return (int) $query->count();
    }

    private function scopePersonalRows(Builder $query, string $table, User $user, array $candidateColumns): void
    {
        if ($this->canSeeDepartmentQueue($user)) {
            return;
        }

        foreach ($candidateColumns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $query->where($column, $user->getKey());

                return;
            }
        }
    }

    private function canSeeDepartmentQueue(User $user): bool
    {
        return $user->hasAnyRole([
            'admin',
            'management',
            'manager',
            'accounting',
            'warehouse',
            'kho',
            'sales_manager',
            'marketing_manager',
            'technical_manager',
            'hr',
        ]);
    }

    private function safe(callable $callback): int
    {
        try {
            return max(0, (int) $callback());
        } catch (Throwable) {
            return 0;
        }
    }
}
