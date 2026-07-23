<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class TaskDashboardAlertService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(?int $userId): array
    {
        $result = [
            'display_count' => 0,
            'unread_count' => 0,
            'notification_count' => 0,
            'active_count' => 0,

            'mode' => 'assigned',
            'eyebrow' => 'Công việc được giao',
            'heading' => 'Công việc đang phụ trách',
            'more_label' => 'công việc đang mở',

            'latest' => null,
            'items' => [],

            'list_url' => $this->routeUrl(
                'tasks.my',
                '/chat/tasks/my'
            ),
        ];

        if (! $userId) {
            return $result;
        }

        try {
            /*
             * Thông báo mới chưa đọc.
             */
            $notificationItems =
                $this->notificationItems(
                    $userId,
                    5
                );

            $notificationCount =
                $this->notificationCount(
                    $userId
                );

            /*
             * Công việc thật đang giao cho user.
             */
            $taskData = $this->taskItems(
                userId: $userId,
                scopeColumn: 'assignee_id',
                limit: 5
            );

            $mode = 'assigned';

            /*
             * Với quản lý không có việc cá nhân:
             * hiện các công việc họ đã giao
             * nhưng nhân viên chưa hoàn tất.
             */
            if (
                (int) $taskData['count'] < 1
                && $notificationCount < 1
            ) {
                $managedTasks = $this->taskItems(
                    userId: $userId,
                    scopeColumn: 'requester_id',
                    limit: 5
                );

                if (
                    (int) $managedTasks['count']
                    > 0
                ) {
                    $mode = 'managed';
                    $taskData = $managedTasks;

                    $result['list_url'] =
                        $this->routeUrl(
                            'tasks.index',
                            '/chat/tasks'
                        );
                }
            }

            /*
             * Ưu tiên notification mới,
             * sau đó bổ sung task đang mở.
             */
            $items = collect(
                $notificationItems
            )
                ->concat(
                    $taskData['items']
                )
                ->unique(
                    function (
                        array $item
                    ): string {
                        $taskId = (int) (
                            $item['task_id']
                            ?? 0
                        );

                        if ($taskId > 0) {
                            return 'task_'.$taskId;
                        }

                        return 'notification_'
                            .(int) (
                                $item['id']
                                ?? 0
                            );
                    }
                )
                ->take(5)
                ->values()
                ->all();

            $activeCount =
                (int) $taskData['count'];

            $displayCount =
                $mode === 'managed'
                    ? $activeCount
                    : max(
                        $activeCount,
                        $notificationCount
                    );

            $result['display_count'] =
                $displayCount;

            /*
             * Giữ key cũ để tương thích Blade.
             */
            $result['unread_count'] =
                $displayCount;

            $result['notification_count'] =
                $notificationCount;

            $result['active_count'] =
                $activeCount;

            $result['mode'] = $mode;
            $result['items'] = $items;
            $result['latest'] =
                $items[0] ?? null;

            if ($mode === 'managed') {
                $result['eyebrow'] =
                    'Công việc đang theo dõi';

                $result['heading'] =
                    'Công việc bạn đã giao';

                $result['more_label'] =
                    'công việc chưa hoàn tất';
            } elseif (
                $notificationCount > 0
            ) {
                $result['eyebrow'] =
                    'Công việc mới';

                $result['heading'] =
                    'Công việc được giao';

                $result['more_label'] =
                    'công việc chưa xem hoặc đang mở';
            }

            return $result;
        } catch (Throwable $exception) {
            report($exception);

            return $result;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function notificationItems(
        int $userId,
        int $limit
    ): array {
        if (
            ! Schema::hasTable(
                'task_notifications'
            )
        ) {
            return [];
        }

        $columns =
            Schema::getColumnListing(
                'task_notifications'
            );

        if (
            ! in_array(
                'user_id',
                $columns,
                true
            )
        ) {
            return [];
        }

        $query = DB::table(
            'task_notifications'
        )->where(
            'user_id',
            $userId
        );

        if (
            in_array(
                'type',
                $columns,
                true
            )
        ) {
            $query->where(
                'type',
                'assigned'
            );
        }

        if (
            in_array(
                'is_read',
                $columns,
                true
            )
        ) {
            $query->where(
                'is_read',
                0
            );
        } elseif (
            in_array(
                'read_at',
                $columns,
                true
            )
        ) {
            $query->whereNull(
                'read_at'
            );
        }

        $select = array_values(
            array_intersect(
                [
                    'id',
                    'task_id',
                    'title',
                    'message',
                    'link',
                    'created_at',
                ],
                $columns
            )
        );

        if ($select === []) {
            return [];
        }

        $orderColumn =
            in_array(
                'created_at',
                $columns,
                true
            )
                ? 'created_at'
                : 'id';

        return $query
            ->orderByDesc($orderColumn)
            ->limit($limit)
            ->get($select)
            ->map(
                function (
                    object $row
                ): array {
                    $taskId = (int) (
                        $row->task_id
                        ?? 0
                    );

                    $link = trim(
                        (string) (
                            $row->link
                            ?? ''
                        )
                    );

                    if ($link === '') {
                        $link = $taskId > 0
                            ? $this->taskUrl(
                                $taskId
                            )
                            : $this->routeUrl(
                                'tasks.my',
                                '/chat/tasks/my'
                            );
                    }

                    return [
                        'id' => (int) (
                            $row->id
                            ?? 0
                        ),

                        'task_id' =>
                            $taskId > 0
                                ? $taskId
                                : null,

                        'title' => trim(
                            (string) (
                                $row->title
                                ?? 'Công việc mới được giao'
                            )
                        ),

                        'message' => trim(
                            (string) (
                                $row->message
                                ?? ''
                            )
                        ),

                        'link' => $link,

                        'time_label' =>
                            $this->formatDateTime(
                                $row->created_at
                                ?? null
                            ),

                        'source' =>
                            'notification',
                    ];
                }
            )
            ->values()
            ->all();
    }

    private function notificationCount(
        int $userId
    ): int {
        if (
            ! Schema::hasTable(
                'task_notifications'
            )
        ) {
            return 0;
        }

        $columns =
            Schema::getColumnListing(
                'task_notifications'
            );

        if (
            ! in_array(
                'user_id',
                $columns,
                true
            )
        ) {
            return 0;
        }

        $query = DB::table(
            'task_notifications'
        )->where(
            'user_id',
            $userId
        );

        if (
            in_array(
                'type',
                $columns,
                true
            )
        ) {
            $query->where(
                'type',
                'assigned'
            );
        }

        if (
            in_array(
                'is_read',
                $columns,
                true
            )
        ) {
            $query->where(
                'is_read',
                0
            );
        } elseif (
            in_array(
                'read_at',
                $columns,
                true
            )
        ) {
            $query->whereNull(
                'read_at'
            );
        }

        return (int) $query->count();
    }

    /**
     * @return array{
     *     count:int,
     *     items:array<int, array<string, mixed>>
     * }
     */
    private function taskItems(
        int $userId,
        string $scopeColumn,
        int $limit
    ): array {
        if (
            ! Schema::hasTable('tasks')
        ) {
            return [
                'count' => 0,
                'items' => [],
            ];
        }

        $columns =
            Schema::getColumnListing(
                'tasks'
            );

        if (
            ! in_array(
                $scopeColumn,
                $columns,
                true
            )
        ) {
            return [
                'count' => 0,
                'items' => [],
            ];
        }

        $activeStatuses = [
            'new',
            'in_progress',
            'submitted',
            'revision',
            'rejected',
        ];

        $query = DB::table(
            'tasks as t'
        )->where(
            't.'.$scopeColumn,
            $userId
        );

        if (
            in_array(
                'status',
                $columns,
                true
            )
        ) {
            $query->whereIn(
                't.status',
                $activeStatuses
            );
        }

        $actorColumn =
            $scopeColumn === 'assignee_id'
                ? 'requester_id'
                : 'assignee_id';

        $hasActor =
            Schema::hasTable('users')
            && in_array(
                $actorColumn,
                $columns,
                true
            );

        if ($hasActor) {
            $query->leftJoin(
                'users as actor',
                'actor.id',
                '=',
                't.'.$actorColumn
            );
        }

        $count = (int) (
            clone $query
        )->count('t.id');

        if ($count < 1) {
            return [
                'count' => 0,
                'items' => [],
            ];
        }

        if (
            in_array(
                'status',
                $columns,
                true
            )
        ) {
            $query->orderByRaw(
                "CASE t.status
                    WHEN 'new' THEN 0
                    WHEN 'revision' THEN 1
                    WHEN 'rejected' THEN 2
                    WHEN 'in_progress' THEN 3
                    WHEN 'submitted' THEN 4
                    ELSE 5
                END"
            );
        }

        if (
            in_array(
                'due_at',
                $columns,
                true
            )
        ) {
            $query
                ->orderByRaw(
                    'CASE
                        WHEN t.due_at IS NULL
                        THEN 1
                        ELSE 0
                    END'
                )
                ->orderBy('t.due_at');
        }

        $query->orderByDesc(
            in_array(
                'created_at',
                $columns,
                true
            )
                ? 't.created_at'
                : 't.id'
        );

        $select = ['t.id'];

        foreach (
            [
                'title',
                'status',
                'priority',
                'due_at',
                'created_at',
            ] as $column
        ) {
            if (
                in_array(
                    $column,
                    $columns,
                    true
                )
            ) {
                $select[] =
                    't.'.$column;
            }
        }

        if ($hasActor) {
            $select[] =
                'actor.name as actor_name';
        }

        $items = $query
            ->limit($limit)
            ->get($select)
            ->map(
                function (
                    object $row
                ) use (
                    $scopeColumn
                ): array {
                    $status = (string) (
                        $row->status
                        ?? 'new'
                    );

                    $actorLabel =
                        $scopeColumn
                        === 'assignee_id'
                            ? 'Người giao'
                            : 'Người nhận';

                    $messageParts = [];

                    if (
                        ! empty(
                            $row->actor_name
                        )
                    ) {
                        $messageParts[] =
                            $actorLabel
                            .': '
                            .$row->actor_name;
                    }

                    $messageParts[] =
                        'Trạng thái: '
                        .$this->statusLabel(
                            $status
                        );

                    if (
                        ! empty(
                            $row->due_at
                        )
                    ) {
                        $messageParts[] =
                            'Hạn: '
                            .$this->formatDateTime(
                                $row->due_at
                            );
                    }

                    return [
                        'id' =>
                            (int) $row->id,

                        'task_id' =>
                            (int) $row->id,

                        'title' => trim(
                            (string) (
                                $row->title
                                ?? 'Công việc đang xử lý'
                            )
                        ),

                        'message' =>
                            implode(
                                ' · ',
                                $messageParts
                            ),

                        'link' =>
                            $this->taskUrl(
                                (int) $row->id
                            ),

                        'time_label' =>
                            ! empty(
                                $row->due_at
                            )
                                ? 'Hạn '
                                    .$this->formatDateTime(
                                        $row->due_at
                                    )
                                : $this->formatDateTime(
                                    $row->created_at
                                    ?? null
                                ),

                        'source' => 'task',
                        'status' => $status,
                    ];
                }
            )
            ->values()
            ->all();

        return [
            'count' => $count,
            'items' => $items,
        ];
    }

    private function taskUrl(
        int $taskId
    ): string {
        try {
            if (
                Route::has(
                    'tasks.show'
                )
            ) {
                return route(
                    'tasks.show',
                    [
                        'task' => $taskId,
                    ]
                );
            }
        } catch (Throwable) {
        }

        return url(
            '/chat/tasks/'.$taskId
        );
    }

    private function routeUrl(
        string $routeName,
        string $fallback
    ): string {
        try {
            if (
                Route::has($routeName)
            ) {
                return route($routeName);
            }
        } catch (Throwable) {
        }

        return url($fallback);
    }

    private function formatDateTime(
        mixed $value
    ): string {
        if (empty($value)) {
            return '';
        }

        try {
            return Carbon::parse(
                $value
            )->format(
                'd/m/Y H:i'
            );
        } catch (Throwable) {
            return (string) $value;
        }
    }

    private function statusLabel(
        string $status
    ): string {
        return match ($status) {
            'new' => 'Mới giao',
            'in_progress' => 'Đang làm',
            'submitted' => 'Đã nộp',
            'revision' => 'Cần sửa',
            'rejected' => 'Bị từ chối',
            'approved' => 'Đã duyệt',

            default =>
                $status !== ''
                    ? $status
                    : 'Chưa xác định',
        };
    }
}
