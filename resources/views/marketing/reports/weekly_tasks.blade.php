@extends('layouts.app')

@section('title', 'Công việc hàng tuần')

@section('content')
@php
use Illuminate\Support\Facades\DB;

$dbName  = DB::connection()->getDatabaseName();
$dbCount = DB::table('weekly_tasks')->count();
    // categories: controller nên truyền $categories
    $categories = $categories ?? collect($tasks ?? [])->pluck('category')->filter()->unique()->sort()->values();

    $reqCategory = request('category');
    $reqStatus   = request('status');

    $fmtDate = function ($v) {
        if (empty($v)) return '-';
        try {
            return \Carbon\Carbon::parse($v)->format('Y-m-d'); // ✅ không có giờ
        } catch (\Throwable $e) {
            // nếu DB trả string lạ
            return (string) $v;
        }
    };
@endphp

<div class="container-fluid px-4 weekly-task-page mt-3">

 {{-- Header --}}
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <h3 class="fw-bold mb-1">Công việc hàng tuần</h3>
        <div class="text-muted">Tổng quan + danh sách công việc theo khoảng ngày</div>
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-end">
        {{-- Filter --}}
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end wt-filter">
            <div>
                <label class="form-label small text-muted mb-1">Từ ngày</label>
                <input type="date" name="from" class="form-control"
                       value="{{ old('from', request('from')) }}">
            </div>

            <div>
                <label class="form-label small text-muted mb-1">Đến ngày</label>
                <input type="date" name="to" class="form-control"
                       value="{{ old('to', request('to')) }}">
            </div>

            <div>
                <label class="form-label small text-muted mb-1">Hạng mục</label>
                <select name="category" class="form-select">
                    <option value="">Tất cả</option>
                    @foreach($categories as $c)
                        <option value="{{ $c }}" {{ (request('category')===$c) ? 'selected' : '' }}>
                            {{ $c }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label small text-muted mb-1">Trạng thái</label>
                <select name="status" class="form-select">
                    <option value="">Tất cả</option>
                    <option value="pending" {{ request('status')==='pending' ? 'selected' : '' }}>Todo</option>
                    <option value="doing"   {{ request('status')==='doing' ? 'selected' : '' }}>Doing</option>
                    <option value="done"    {{ request('status')==='done' ? 'selected' : '' }}>Done</option>
                </select>
            </div>

            <div class="pb-1 d-flex gap-2">
                <button type="submit" class="btn btn-ego wt-btn">
                    <i class="bi bi-funnel"></i> Lọc
                </button>

                <a href="{{ route('marketing.reports.weekly-tasks') }}"
                   class="btn btn-ego-soft wt-btn" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </form>

        {{-- Nút tạo mới: để NGOÀI form để không bị form nuốt click --}}
        <div class="pb-1">
            <a href="{{ route('marketing.reports.weekly-tasks.create') }}"
               class="btn btn-ego-soft wt-btn">
                <i class="bi bi-plus-circle"></i> Thêm công việc
            </a>
        </div>
    </div>
</div>


    {{-- Dashboard cards --}}
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card wt-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="wt-label">Tổng task</div>
                            <div class="wt-value">{{ $total ?? 0 }}</div>
                        </div>
                        <div class="wt-icon"><i class="bi bi-list-task"></i></div>
                    </div>
                    <div class="wt-sub">Trong khoảng lọc hiện tại</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card wt-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="wt-label">Hoàn thành</div>
                            <div class="wt-value">{{ $done ?? 0 }}</div>
                        </div>
                        <div class="wt-icon"><i class="bi bi-check2-circle"></i></div>
                    </div>
                    <div class="wt-sub">Status: done</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card wt-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="wt-label">Đang làm</div>
                            <div class="wt-value">{{ $doing ?? 0 }}</div>
                        </div>
                        <div class="wt-icon"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                    <div class="wt-sub">Status: doing</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card wt-card wt-card-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="wt-label">Quá hạn</div>
                            <div class="wt-value">{{ $overdue ?? 0 }}</div>
                        </div>
                        <div class="wt-icon"><i class="bi bi-exclamation-triangle"></i></div>
                    </div>
                    <div class="wt-sub">Due date &lt; hôm nay & chưa done</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Priority mini --}}
    <div class="card wt-card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="fw-semibold">
                    Ưu tiên:
                    <span class="wt-pill wt-high">High: {{ $priorityCount['high'] ?? 0 }}</span>
                    <span class="wt-pill wt-medium">Medium: {{ $priorityCount['medium'] ?? 0 }}</span>
                    <span class="wt-pill wt-low">Low: {{ $priorityCount['low'] ?? 0 }}</span>
                </div>
                <div class="text-muted small">
                    Tip: Thêm chart (pie/bar) sau.
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card wt-card">
        <div class="card-header wt-card-header d-flex justify-content-between align-items-center">
            <div class="fw-bold">Task list</div>
            <div class="small text-muted">Hiển thị: {{ ($tasks ?? collect())->count() }} dòng</div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 wt-table">
                    <thead>
                        <tr>
                            <th style="width: 64px;">STT</th>
                            <th>Tên công việc</th>
                            <th style="width: 120px;">Priority</th>
                            <th style="width: 140px;">Hạng mục</th>
                            <th style="width: 230px;">Chịu trách nhiệm</th>
                            <th style="width: 120px;">Ngày bắt đầu</th>
                            <th style="width: 120px;">Hạn</th>
                            <th style="width: 120px;">Trạng thái</th>
                            <th style="width: 260px;" class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse(($tasks ?? []) as $t)
                        @php
                        


                            $p = strtolower($t->priority ?? '');
                            $s = strtolower($t->status ?? '');

                            $priorityBadge = match($p) {
                                'high' => 'bg-danger',
                                'medium' => 'bg-warning text-dark',
                                'low' => 'bg-info text-dark',
                                default => 'bg-secondary'
                            };

                            $statusBadge = match($s) {
                                'done' => 'bg-success',
                                'doing' => 'bg-primary',
                                'pending' => 'bg-secondary',
                                default => 'bg-secondary'
                            };
                        @endphp

                        <tr>
                            {{-- ✅ STT theo danh sách hiển thị --}}
                            <td class="text-muted fw-semibold">{{ $loop->iteration }}</td>

                            {{-- Title: click xem chi tiết --}}
                            <td class="fw-semibold">
                                <a class="wt-link" href="{{ route('marketing.reports.weekly-tasks.show', $t->id) }}">
                                    {{ $t->title ?? '-' }}
                                </a>
                            </td>

                            <td>
                                <span class="badge {{ $priorityBadge }} wt-badge">{{ strtoupper($p ?: 'N/A') }}</span>
                            </td>

                            <td class="text-muted">{{ $t->category ?? '-' }}</td>

                            <td class="wt-ellipsis" title="{{ $t->assignee ?? '-' }}">
                                {{ $t->assignee ?? '-' }}
                            </td>

                            <td class="text-muted wt-nowrap">{{ $fmtDate($t->start_date ?? null) }}</td>
                            <td class="text-muted wt-nowrap">{{ $fmtDate($t->due_date ?? null) }}</td>

                            <td>
                                <span class="badge {{ $statusBadge }} wt-badge">{{ strtoupper($s ?: 'N/A') }}</span>
                            </td>

                            <td class="text-end">
                                <div class="d-inline-flex gap-2">

                                    {{-- Xem --}}
                                    <a href="{{ route('marketing.reports.weekly-tasks.show', $t->id) }}"
                                       class="btn btn-sm btn-ego-soft wt-action-btn">
                                        <i class="bi bi-eye"></i> Xem
                                    </a>

                                    {{-- Sửa (chỉ 1 nút) --}}
                                    <a href="{{ route('marketing.reports.weekly-tasks.edit', $t->id) }}"
                                       class="btn btn-sm btn-ego-soft wt-action-btn">
                                        <i class="bi bi-pencil-square"></i> Sửa
                                    </a>

                                    {{-- Xóa --}}
                                    <form method="POST"
                                          action="{{ route('marketing.reports.weekly-tasks.destroy', $t->id) }}"
                                          class="wt-del-form m-0">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger wt-action-btn wt-danger">
                                            <i class="bi bi-trash"></i> Xóa
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                Chưa có dữ liệu.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
/* ===== Weekly Task - EGO style ===== */
.weekly-task-page{
    --ego: #0E7C86;
    --ego2: #0B5E66;
    --border: rgba(12, 92, 100, .10);
    --text: #0f172a;
    --muted: #64748b;
}

.weekly-task-page .wt-filter .form-control,
.weekly-task-page .wt-filter .form-select{
    border-radius: 12px;
    border: 1px solid var(--border);
    min-height: 42px;
}

.weekly-task-page .wt-btn{
    height: 42px;
    display:inline-flex;
    align-items:center;
    gap: 8px;
    border-radius: 12px;
    font-weight: 900;
    padding: 0 14px;
}

.weekly-task-page .btn-ego{
    background: linear-gradient(135deg, var(--ego), var(--ego2));
    border: none;
    color: #fff;
    box-shadow: 0 10px 22px rgba(14, 124, 134, .18);
}
.weekly-task-page .btn-ego:hover{ filter: brightness(.98); color:#fff; }

.weekly-task-page .btn-ego-soft{
    background: rgba(14, 124, 134, .10);
    border: 1px solid var(--border);
    color: var(--ego2);
    border-radius: 12px;
    font-weight: 900;
}

.weekly-task-page .wt-card{
    border: 1px solid var(--border);
    border-radius: 18px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 12px 30px rgba(15, 23, 42, .04);
}
.weekly-task-page .wt-card-danger{
    border-color: rgba(220, 53, 69, .20);
}

.weekly-task-page .wt-label{
    font-size: 13px;
    color: var(--muted);
    font-weight: 800;
}
.weekly-task-page .wt-value{
    font-size: 30px;
    font-weight: 900;
    color: var(--text);
    line-height: 1.1;
}
.weekly-task-page .wt-sub{
    margin-top: 6px;
    font-size: 12px;
    color: var(--muted);
}

.weekly-task-page .wt-icon{
    width: 44px; height: 44px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    background: rgba(14, 124, 134, .12);
    color: var(--ego2);
    font-size: 20px;
}

.weekly-task-page .wt-pill{
    display: inline-flex;
    align-items: center;
    padding: 6px 10px;
    border-radius: 999px;
    font-weight: 900;
    font-size: 12px;
    margin-left: 8px;
    border: 1px solid var(--border);
}
.weekly-task-page .wt-high{ background: rgba(220,53,69,.10); color: #b4232c; border-color: rgba(220,53,69,.18); }
.weekly-task-page .wt-medium{ background: rgba(255,193,7,.18); color: #7a5b00; border-color: rgba(255,193,7,.25); }
.weekly-task-page .wt-low{ background: rgba(13,202,240,.16); color: #075d6d; border-color: rgba(13,202,240,.22); }

.weekly-task-page .wt-card-header{
    background: linear-gradient(135deg, rgba(14,124,134,.10), rgba(14,124,134,.03));
    border-bottom: 1px solid var(--border);
    padding: 14px 16px;
}

.weekly-task-page .wt-table thead th{
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .4px;
    color: var(--muted);
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
.weekly-task-page .wt-table td{
    border-top: 1px solid rgba(15,23,42,.06);
    padding: 12px 12px;
}
.weekly-task-page .wt-badge{
    border-radius: 999px;
    padding: 7px 10px;
    font-weight: 900;
    letter-spacing: .2px;
}
.weekly-task-page .wt-link{
    text-decoration: none;
    color: var(--text);
}
.weekly-task-page .wt-link:hover{
    color: var(--ego2);
    text-decoration: underline;
}
.weekly-task-page .wt-nowrap{
    white-space: nowrap;
}
.weekly-task-page .wt-ellipsis{
    max-width: 240px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.weekly-task-page .wt-action-btn{
    border-radius: 12px;
    font-weight: 900;
    padding: 7px 10px;
    height: 38px;
    display:inline-flex;
    align-items:center;
    gap: 8px;
}
.weekly-task-page .wt-danger{
    box-shadow: 0 10px 18px rgba(220,53,69,.12);
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.wt-del-form').forEach(form => {
    form.addEventListener('submit', (e) => {
      if (!confirm('Bạn chắc chắn muốn xóa công việc này? Hành động không thể hoàn tác.')) {
        e.preventDefault();
      }
    });
  });
});
</script>
@endpush
