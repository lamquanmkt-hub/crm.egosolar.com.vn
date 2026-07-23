{{-- EGO_TASK_FLOAT_GLOBAL_COMPONENT_V150 --}}
@php
    $egoTaskDashboard = app(
        \App\Services\TaskDashboardAlertService::class
    )->snapshot(
        auth()->id()
    );

    $egoTaskCount = (int) (
        $egoTaskDashboard['display_count']
        ?? $egoTaskDashboard['unread_count']
        ?? 0
    );

    $egoTaskNotificationCount = (int) (
        $egoTaskDashboard['notification_count']
        ?? 0
    );

    $egoTaskLatest =
        $egoTaskDashboard['latest']
        ?? null;

    $egoTaskMode =
        $egoTaskDashboard['mode']
        ?? 'assigned';

    $egoTaskListUrl =
        $egoTaskDashboard['list_url']
        ?? url('/chat/tasks/my');

    $egoTaskHeading =
        $egoTaskMode === 'managed'
            ? 'Công việc bạn đã giao'
            : 'Công việc được giao';

    $egoTaskMoreLabel =
        $egoTaskMode === 'managed'
            ? 'công việc chưa hoàn tất'
            : 'công việc đang phụ trách';
@endphp

@if(
    auth()->check()
    && $egoTaskCount > 0
    && $egoTaskLatest
)
    <aside
        class="ego-task-float"
        id="egoTaskFloat"
        aria-label="{{ $egoTaskHeading }}"
        data-task-count="{{ $egoTaskCount }}"
    >
        <button
            type="button"
            class="ego-task-float__handle"
            id="egoTaskFloatToggle"
            aria-controls="egoTaskFloat"
            aria-expanded="false"
        >
            <span class="ego-task-float__pulse"></span>

            <i class="bi bi-list-check"></i>

            <strong>
                {{ number_format($egoTaskCount) }}
            </strong>

            <span class="ego-task-float__handle-text">
                Công việc
            </span>

            <i
                class="bi bi-chevron-left ego-task-float__arrow"
            ></i>
        </button>

        <div class="ego-task-float__panel">
            <button
                type="button"
                class="ego-task-float__close"
                id="egoTaskFloatClose"
                aria-label="Thu gọn thông báo"
            >
                <i class="bi bi-x-lg"></i>
            </button>

            <div class="ego-task-float__icon">
                <i class="bi bi-clipboard-check"></i>
            </div>

            <div class="ego-task-float__eyebrow">
                @if($egoTaskNotificationCount > 0)
                    Công việc mới
                @elseif($egoTaskMode === 'managed')
                    Công việc đang theo dõi
                @else
                    Việc cần xử lý
                @endif
            </div>

            <h3>
                {{ number_format($egoTaskCount) }}
                {{ $egoTaskHeading }}
            </h3>

            <div class="ego-task-float__latest">
                <strong>
                    {{
                        $egoTaskLatest['title']
                        ?: 'Công việc đang xử lý'
                    }}
                </strong>

                @if(
                    ! empty(
                        $egoTaskLatest['message']
                    )
                )
                    <p>
                        {{
                            $egoTaskLatest[
                                'message'
                            ]
                        }}
                    </p>
                @endif

                @if(
                    ! empty(
                        $egoTaskLatest[
                            'time_label'
                        ]
                    )
                )
                    <time>
                        <i class="bi bi-clock"></i>

                        {{
                            $egoTaskLatest[
                                'time_label'
                            ]
                        }}
                    </time>
                @endif
            </div>

            <div class="ego-task-float__chips">
                @if($egoTaskNotificationCount > 0)
                    <span>
                        <i class="bi bi-bell"></i>

                        {{
                            number_format(
                                $egoTaskNotificationCount
                            )
                        }}
                        thông báo mới
                    </span>
                @endif

                <span>
                    <i class="bi bi-person-check"></i>

                    Việc của tài khoản hiện tại
                </span>
            </div>

            @if($egoTaskCount > 1)
                <div class="ego-task-float__more">
                    <i class="bi bi-layers"></i>

                    Còn
                    {{
                        number_format(
                            $egoTaskCount - 1
                        )
                    }}
                    {{ $egoTaskMoreLabel }}
                </div>
            @endif

            <div class="ego-task-float__actions">
                <a
                    href="{{
                        $egoTaskLatest['link']
                        ?: $egoTaskListUrl
                    }}"
                    class="ego-task-float__action"
                >
                    Mở công việc

                    <i class="bi bi-arrow-right"></i>
                </a>

                <a
                    href="{{ $egoTaskListUrl }}"
                    class="ego-task-float__all"
                >
                    Xem tất cả
                </a>
            </div>
        </div>
    </aside>
@endif
