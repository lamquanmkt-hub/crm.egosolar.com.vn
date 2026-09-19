@extends('layouts.app')
@php
    $mode = $mode ?? 'progress';
    $modeMeta = [
        'progress' => ['title' => 'Tiến độ công trình', 'desc' => 'Theo dõi giai đoạn, phần trăm hoàn thành, người phụ trách và mốc gần nhất của từng công trình.'],
        'performance' => ['title' => 'Hiệu suất nhân sự', 'desc' => 'Đối chiếu số công trình, nhật ký, công việc hoàn tất và số việc quá hạn theo kỹ thuật viên.'],
        'overdue' => ['title' => 'Công việc quá hạn', 'desc' => 'Danh sách nhiệm vụ đã qua deadline nhưng chưa được duyệt hoặc hoàn tất.'],
        'incidents' => ['title' => 'Báo cáo phát sinh', 'desc' => 'Tổng hợp rủi ro, sự cố và phát sinh từ nhật ký công trình hoặc báo cáo công việc kỹ thuật.'],
    ][$mode] ?? ['title' => 'Báo cáo kỹ thuật', 'desc' => 'Báo cáo thực thi phòng Kỹ thuật.'];
    $projectStatuses = \App\Http\Controllers\Projects\ProjectTestController::STATUSES;
    $resetRoute = match($mode) {
        'performance' => 'technical-workspace.reports.performance',
        'overdue' => 'technical-workspace.reports.overdue',
        'incidents' => 'technical-workspace.reports.incidents',
        default => 'technical-workspace.reports.progress',
    };
@endphp
@section('title', $modeMeta['title'].' • Phòng Kỹ thuật')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/technical-workspace.css') }}?v={{ file_exists(public_path('css/technical-workspace.css')) ? filemtime(public_path('css/technical-workspace.css')) : time() }}">
@endpush
@section('content')
<div class="tw-page"><div class="tw-shell">
    <section class="tw-hero">
        <div class="tw-hero__row"><div>
            <div class="tw-kicker">PHÒNG KỸ THUẬT · BÁO CÁO</div>
            <h1>{{ $modeMeta['title'] }}</h1>
            <p>{{ $modeMeta['desc'] }}</p>
        </div><div class="tw-title-actions tw-report-actions">
            <a class="tw-btn tw-btn--soft" href="{{ route('technical-workspace.reports.history') }}"><i class="bi bi-clock-history"></i>Lịch sử</a>
            <a class="tw-btn tw-btn--soft" href="{{ route('technical-workspace.reports.export', ['mode'=>$mode,'from'=>$range['from']->toDateString(),'to'=>$range['to']->toDateString()]) }}"><i class="bi bi-file-earmark-excel"></i>Xuất Excel</a>
            <form method="POST" action="{{ route('technical-workspace.reports.snapshot.store') }}">
                @csrf
                <input type="hidden" name="mode" value="{{ $mode }}">
                <input type="hidden" name="from" value="{{ $range['from']->toDateString() }}">
                <input type="hidden" name="to" value="{{ $range['to']->toDateString() }}">
                <button class="tw-btn" type="submit"><i class="bi bi-cloud-check"></i>Lưu báo cáo</button>
            </form>
        </div></div>
        @include('technical.workspace.partials-nav')
    </section>

    <section class="tw-subnav">
        <a href="{{ route('technical-workspace.reports.progress') }}" class="{{ $mode === 'progress' ? 'active' : '' }}"><i class="bi bi-speedometer2"></i>Tiến độ công trình</a>
        <a href="{{ route('technical-workspace.reports.performance') }}" class="{{ $mode === 'performance' ? 'active' : '' }}"><i class="bi bi-people"></i>Hiệu suất nhân sự</a>
        <a href="{{ route('technical-workspace.reports.overdue') }}" class="{{ $mode === 'overdue' ? 'active' : '' }}"><i class="bi bi-alarm"></i>Công việc quá hạn</a>
        <a href="{{ route('technical-workspace.reports.incidents') }}" class="{{ $mode === 'incidents' ? 'active' : '' }}"><i class="bi bi-exclamation-triangle"></i>Báo cáo phát sinh</a>
    </section>

    <section class="tw-card"><div class="tw-card__body">
        <form class="tw-filter" method="GET">
            <label>Từ ngày<input class="tw-input" type="date" name="from" value="{{ request('from', $range['from']->toDateString()) }}"></label>
            <label>Đến ngày<input class="tw-input" type="date" name="to" value="{{ request('to', $range['to']->toDateString()) }}"></label>
            <button class="tw-btn"><i class="bi bi-funnel"></i>Áp dụng</button>
            <a class="tw-btn tw-btn--soft" href="{{ route($resetRoute) }}">Đặt lại</a>
        </form>
    </div></section>

    <section class="tw-kpis">
        <article class="tw-kpi"><span>Công trình phát sinh</span><strong>{{ number_format($stats['projects_total']) }}</strong><small>{{ $range['label'] }}</small></article>
        <article class="tw-kpi"><span>Công trình đang chạy</span><strong>{{ number_format($stats['projects_running']) }}</strong><small>Khảo sát → nghiệm thu</small></article>
        <article class="tw-kpi"><span>Khảo sát hoàn tất</span><strong>{{ number_format($stats['surveys_done']) }}</strong><small>Có hồ sơ khảo sát</small></article>
        <article class="tw-kpi"><span>Nhật ký thi công</span><strong>{{ number_format($stats['logs_count']) }}</strong><small>Báo cáo trong kỳ</small></article>
        <article class="tw-kpi"><span>Nghiệm thu</span><strong>{{ number_format($stats['acceptances']) }}</strong><small>Hồ sơ đã bàn giao</small></article>
        <article class="tw-kpi"><span>Việc quá hạn</span><strong>{{ number_format($stats['tasks_overdue']) }}</strong><small>Cần ưu tiên xử lý</small></article>
    </section>

    @if($mode === 'progress')
        <section class="tw-card">
            <div class="tw-card__head"><div><h2>Tiến độ theo công trình</h2><p>Ưu tiên công trình chưa hoàn tất và có phần trăm tiến độ thấp.</p></div><span class="tw-pill">{{ $projects->count() }} hồ sơ gần nhất</span></div>
            <div class="tw-table-wrap"><table class="tw-table">
                <thead><tr><th>Công trình</th><th>Giai đoạn</th><th>Tiến độ</th><th>Phụ trách</th><th>Mốc gần nhất</th><th>Cập nhật</th></tr></thead>
                <tbody>@forelse($projects as $project)
                    @php
                        $status = $projectStatuses[$project->status] ?? ['label' => str_replace('_',' ', $project->status)];
                        $milestone = $project->proposed_installation_at ?: $project->proposed_survey_at ?: $project->target_completion_at;
                    @endphp
                    <tr>
                        <td><a class="tw-name tw-link" href="{{ route('project-test.show',$project) }}">{{ $project->code }} · {{ $project->name }}</a><div class="tw-sub">{{ $project->address ?: 'Chưa có địa chỉ' }}</div></td>
                        <td><span class="tw-pill {{ $project->progress < 60 ? 'warning' : '' }}">{{ $status['label'] }}</span></td>
                        <td><div class="tw-name">{{ (int) $project->progress }}%</div><div class="tw-progress"><span style="width:{{ min(100,(int)$project->progress) }}%"></span></div></td>
                        <td>{{ $project->leadTechnician?->name ?: $project->salesUser?->name ?: 'Chưa phân công' }}</td>
                        <td>{{ $milestone ? \Carbon\Carbon::parse($milestone)->format('d/m/Y H:i') : 'Chưa có lịch' }}</td>
                        <td>{{ $project->updated_at?->format('d/m/Y H:i') ?: '—' }}</td>
                    </tr>
                @empty<tr><td colspan="6"><div class="tw-empty">Chưa có công trình trong phạm vi được xem.</div></td></tr>@endforelse</tbody>
            </table></div>
        </section>
    @elseif($mode === 'performance')
        <div class="tw-two-col">
            <section class="tw-card">
                <div class="tw-card__head"><div><h2>Hiệu suất kỹ thuật viên</h2><p>Công trình được phân công, nhật ký và tỷ lệ hoàn thành công việc.</p></div><span class="tw-pill">Theo phạm vi phòng</span></div>
                <div class="tw-table-wrap"><table class="tw-table">
                    <thead><tr><th>Nhân sự</th><th>Công trình</th><th>Nhật ký</th><th>Việc hoàn tất</th><th>Quá hạn</th><th>Hiệu suất</th></tr></thead>
                    <tbody>@forelse($performance as $member)<tr>
                        <td><div class="tw-name">{{ $member['name'] }}</div></td><td>{{ $member['projects'] }}</td><td>{{ $member['logs'] }}</td><td>{{ $member['tasks_approved'] }}/{{ $member['tasks_total'] }}</td>
                        <td><span class="tw-pill {{ $member['overdue'] > 0 ? 'danger' : '' }}">{{ $member['overdue'] }}</span></td>
                        <td><div class="tw-name">{{ number_format($member['completion_rate'],1,',','.') }}%</div><div class="tw-progress"><span style="width:{{ min(100,$member['completion_rate']) }}%"></span></div></td>
                    </tr>@empty<tr><td colspan="6"><div class="tw-empty">Chưa có dữ liệu hiệu suất trong kỳ.</div></td></tr>@endforelse</tbody>
                </table></div>
            </section>
            <section class="tw-card"><div class="tw-card__head"><div><h2>Nhật ký gần nhất</h2><p>Bằng chứng thi công và tiến độ thực tế.</p></div></div><div class="tw-card__body">
                @forelse($recentLogs as $log)<div class="tw-log"><div><strong>{{ \Carbon\Carbon::parse($log->log_date)->format('d/m/Y') }}</strong><small>{{ $log->author_name ?: 'Kỹ thuật' }}</small></div><div><strong>{{ $log->code }} · {{ $log->project_name }}</strong><small>{{ \Illuminate\Support\Str::limit($log->content,100) }}</small></div><div><span class="tw-pill">{{ $log->progress }}%</span><div class="tw-sub"><a href="{{ route('project-test.show',$log->project_id) }}">Mở hồ sơ</a></div></div></div>@empty<div class="tw-empty">Chưa có nhật ký kỹ thuật.</div>@endforelse
            </div></section>
        </div>
    @elseif($mode === 'overdue')
        <section class="tw-card">
            <div class="tw-card__head"><div><h2>Danh sách công việc quá hạn</h2><p>Sắp xếp theo deadline cũ nhất để trưởng phòng ưu tiên điều phối.</p></div><span class="tw-pill danger">{{ $overdueTasks->count() }} việc</span></div>
            <div class="tw-table-wrap"><table class="tw-table"><thead><tr><th>Công việc</th><th>Công trình</th><th>Người thực hiện</th><th>Ưu tiên</th><th>Tiến độ</th><th>Deadline</th></tr></thead><tbody>
                @forelse($overdueTasks as $task)<tr>
                    <td><a class="tw-name tw-link" href="{{ route('tasks.show',$task->id) }}">{{ $task->title }}</a><div class="tw-sub">{{ str_replace('_',' ', $task->status) }}</div></td>
                    <td>{{ $task->project_code ?? '—' }} @if(!empty($task->project_name))<div class="tw-sub">{{ $task->project_name }}</div>@endif</td>
                    <td>{{ $task->assignee_name ?: 'Chưa phân công' }}</td>
                    <td><span class="tw-pill {{ in_array($task->priority,['high','urgent'],true) ? 'danger' : 'warning' }}">{{ str_replace('_',' ', $task->priority) }}</span></td>
                    <td><div class="tw-name">{{ (int) $task->progress_percent }}%</div><div class="tw-progress"><span style="width:{{ min(100,(int)$task->progress_percent) }}%"></span></div></td>
                    <td><span class="tw-status is-overdue">{{ \Carbon\Carbon::parse($task->due_at)->format('d/m/Y H:i') }}</span><div class="tw-sub">Trễ {{ \Carbon\Carbon::parse($task->due_at)->diffForHumans(now(), true) }}</div></td>
                </tr>@empty<tr><td colspan="6"><div class="tw-empty"><i class="bi bi-check-circle"></i><strong>Không có công việc quá hạn</strong><span>Toàn bộ nhiệm vụ trong phạm vi hiện tại đang đúng tiến độ.</span></div></td></tr>@endforelse
            </tbody></table></div>
        </section>
    @else
        <section class="tw-card">
            <div class="tw-card__head"><div><h2>Phát sinh và sự cố trong kỳ</h2><p>Tổng hợp từ nhật ký công trình và phần phát sinh trên công việc kỹ thuật.</p></div><span class="tw-pill warning">{{ $incidents->count() }} ghi nhận</span></div>
            <div class="tw-table-wrap"><table class="tw-table"><thead><tr><th>Thời gian</th><th>Nguồn</th><th>Công trình/Công việc</th><th>Người báo cáo</th><th>Nội dung phát sinh</th><th>Trạng thái</th></tr></thead><tbody>
                @forelse($incidents as $incident)<tr>
                    <td>{{ $incident['occurred_at'] ? \Carbon\Carbon::parse($incident['occurred_at'])->format('d/m/Y H:i') : '—' }}</td>
                    <td><span class="tw-pill">{{ $incident['source'] }}</span></td>
                    <td><a class="tw-name tw-link" href="{{ $incident['url'] }}">{{ trim(($incident['project_code'] ? $incident['project_code'].' · ' : '').($incident['project_name'] ?: 'Không rõ')) }}</a></td>
                    <td>{{ $incident['reporter_name'] ?: 'Không rõ' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit((string)$incident['description'],180) }}</td>
                    <td><span class="tw-pill warning">{{ str_replace('_',' ',(string)$incident['status']) }}</span></td>
                </tr>@empty<tr><td colspan="6"><div class="tw-empty"><i class="bi bi-shield-check"></i><strong>Chưa có phát sinh trong kỳ</strong><span>Không tìm thấy nhật ký hoặc công việc có nội dung rủi ro/sự cố.</span></div></td></tr>@endforelse
            </tbody></table></div>
        </section>
    @endif
</div></div>
@endsection
