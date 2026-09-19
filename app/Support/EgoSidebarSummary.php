<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight cached counters for the global sidebar.
 *
 * Shared counters are cached by company. Personal task count is cached by user,
 * so one user's request no longer recalculates every global badge.
 */
final class EgoSidebarSummary
{
    private const GLOBAL_CACHE_SECONDS = 120;
    private const USER_CACHE_SECONDS = 60;

    /** @return array<string, int> */
    public static function data(?User $user): array
    {
        $companyId = (int) (EgoCompanyScope::currentId() ?? 0);
        $userId = (int) ($user?->id ?? 0);

        try {
            $global = Cache::remember(
                "ego:sidebar-summary:v3:global:company:{$companyId}",
                now()->addSeconds(self::GLOBAL_CACHE_SECONDS),
                fn (): array => self::buildGlobal($user)
            );
        } catch (\Throwable) {
            $global = self::buildGlobal($user);
        }

        try {
            $unfinishedTasks = Cache::remember(
                "ego:sidebar-summary:v3:tasks:user:{$userId}",
                now()->addSeconds(self::USER_CACHE_SECONDS),
                fn (): int => self::unfinishedTasks($user)
            );
        } catch (\Throwable) {
            $unfinishedTasks = self::unfinishedTasks($user);
        }

        return $global + ['unfinished_tasks' => (int) $unfinishedTasks];
    }

    /** @return array<string, int> */
    private static function buildGlobal(?User $user): array
    {
        $status = EgoSidebarStatus::data(false);

        return [
            'online' => (int) ($status['online'] ?? ($user ? 1 : 0)),
            'working' => (int) ($status['working'] ?? 0),
            'employees' => (int) ($status['employees'] ?? 0),
            'pending_orders' => self::countWhereIn(
                'crm_order_approvals',
                'status',
                ['pending'],
                'order_id'
            ),
            'pending_material_requests' => self::countWhereIn(
                'material_requests',
                'status',
                ['pending', 'submitted', 'admin_approved']
            ),
            'pending_payment_requests' => self::countWhereIn(
                'payment_requests',
                'status',
                ['pending', 'submitted', 'admin_pending', 'admin_approved', 'accounting_pending']
            ),
            'pending_proposals' => self::countWhereIn(
                'proposals',
                'status',
                ['pending', 'submitted']
            ),
        ];
    }

    private static function unfinishedTasks(?User $user): int
    {
        if (! $user || ! Schema::hasTable('tasks') || ! Schema::hasColumn('tasks', 'assignee_id')) {
            return 0;
        }

        try {
            $query = DB::table('tasks')->where('assignee_id', $user->id);

            if (Schema::hasColumn('tasks', 'status')) {
                $query->where(function ($sub): void {
                    $sub->whereNull('status')
                        ->orWhereNotIn(
                            DB::raw('LOWER(status)'),
                            ['approved', 'done', 'completed', 'complete', 'closed', 'cancelled', 'canceled']
                        );
                });
            }

            if (Schema::hasColumn('tasks', 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            return (int) $query->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function countWhereIn(
        string $table,
        string $statusColumn,
        array $statuses,
        ?string $distinctColumn = null
    ): int {
        try {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $statusColumn)) {
                return 0;
            }

            $query = DB::table($table)
                ->whereIn(DB::raw("LOWER({$statusColumn})"), $statuses);

            if ($distinctColumn && Schema::hasColumn($table, $distinctColumn)) {
                return (int) $query->distinct()->count($distinctColumn);
            }

            return (int) $query->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
