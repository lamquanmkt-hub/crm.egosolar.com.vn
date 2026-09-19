@extends('layouts.app')
<?php
$statusLabels = [
    'pending_admin' => 'Chờ Admin duyệt', 'pending_manager' => 'Chờ quản lý duyệt', 'pending' => 'Chờ xử lý', 'submitted' => 'Đã gửi đề xuất',
    'materials_admin_review' => 'Admin đang duyệt', 'materials_revision' => 'Cần bổ sung',
    'approved' => 'Đã duyệt', 'preparing' => 'Kho đang chuẩn bị', 'warehouse_preparing' => 'Kho đang chuẩn bị',
    'reserved' => 'Đã giữ hàng', 'partial' => 'Cấp một phần', 'shortage' => 'Thiếu vật tư',
    'waiting_import' => 'Chờ nhập hàng', 'issued' => 'Đã xuất kho', 'warehouse_issued' => 'Đã xuất kho',
    'completed' => 'Hoàn thành', 'legacy_archived' => 'Dữ liệu lưu trữ', 'rejected' => 'Từ chối', 'cancelled' => 'Đã hủy',
];
$finishedStatuses = ['issued', 'warehouse_issued', 'completed'];
?>
@section('title', 'Đề xuất vật tư Kỹ thuật')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/technical-workspace.css') }}?v={{ file_exists(public_path('css/technical-workspace.css')) ? filemtime(public_path('css/technical-workspace.css')) : time() }}">
@endpush
@section('content')
<div class="tw-page"><div class="tw-shell">
    <section class="tw-hero tw-hero--compact">
        <div class="tw-hero__row">
            <div>
                <div class="tw-kicker">V15.4 · VẬT TƯ CÔNG TRÌNH</div>
                <h1>Đề xuất vật tư &amp; cảnh báo thiếu</h1>
                <p>Một nơi để Kỹ thuật theo dõi đề xuất, Kho kiểm tra tồn và nhận biết công trình có nguy cơ thiếu vật tư trước ngày thi công.</p>
            </div>
            <div class="tw-title-actions"><a class="tw-btn" href="#tw-create-material"><i class="bi bi-plus-lg"></i>Tạo đề xuất</a></div>
        </div>
        @include('technical.workspace.partials-nav')
    </section>

    <section class="tw-kpis tw-material-kpis">
        <a href="{{ route('technical-workspace.materials.index') }}" class="tw-kpi tw-kpi--blue"><i class="bi bi-files"></i><div><span>Tổng đề xuất</span><strong>{{ $summary['total'] }}</strong><small>Tất cả công trình được xem</small></div></a>
        <a href="{{ route('technical-workspace.materials.index', ['status' => 'pending_admin']) }}" class="tw-kpi tw-kpi--amber"><i class="bi bi-hourglass-split"></i><div><span>Chờ xử lý</span><strong>{{ $summary['pending'] }}</strong><small>Cần kiểm tra hoặc phê duyệt</small></div></a>
        <a href="{{ route('technical-workspace.materials.index', ['alert' => 'shortage']) }}" class="tw-kpi {{ $summary['shortage'] > 0 ? 'tw-kpi--red' : 'tw-kpi--neutral' }}"><i class="bi bi-exclamation-triangle"></i><div><span>Thiếu tồn kho</span><strong>{{ $summary['shortage'] }}</strong><small>So sánh theo kho đã chọn</small></div></a>
        <a href="{{ route('technical-workspace.materials.index', ['alert' => 'unmapped']) }}" class="tw-kpi {{ $summary['unmapped'] > 0 ? 'tw-kpi--amber' : 'tw-kpi--neutral' }}"><i class="bi bi-upc-scan"></i><div><span>Chưa gắn sản phẩm</span><strong>{{ $summary['unmapped'] }}</strong><small>Kho chưa thể đối chiếu tồn</small></div></a>
        <a href="{{ route('technical-workspace.materials.index', ['status' => 'warehouse_preparing']) }}" class="tw-kpi tw-kpi--cyan"><i class="bi bi-box-seam"></i><div><span>Kho đang chuẩn bị</span><strong>{{ $summary['warehouse'] }}</strong><small>Giữ hàng hoặc cấp một phần</small></div></a>
        <a href="{{ route('technical-workspace.materials.index', ['status' => 'issued']) }}" class="tw-kpi tw-kpi--green"><i class="bi bi-truck"></i><div><span>Đã xuất kho</span><strong>{{ $summary['issued'] }}</strong><small>Sẵn sàng giao Kỹ thuật</small></div></a>
    </section>

    <?php if ($summary['shortage'] > 0): ?>
        <section class="tw-alert tw-alert--danger"><i class="bi bi-exclamation-octagon"></i><div><strong>{{ $summary['shortage'] }} đề xuất đang thiếu vật tư</strong><span>Cảnh báo được tính từ số lượng còn cần của từng mặt hàng so với tồn kho hiện tại.</span></div><a class="tw-btn" href="{{ route('technical-workspace.materials.index', ['alert' => 'shortage']) }}">Xem ngay</a></section>
    <?php endif; ?>

    <section id="tw-create-material" class="tw-card tw-material-create">
        <div class="tw-card__head"><div><h2><i class="bi bi-plus-square"></i> Tạo đề xuất vật tư</h2><p>Chọn công trình rồi mở đúng hồ sơ vật tư hiện có; không tạo phiếu trùng bên ngoài công trình.</p></div><span class="tw-pill">{{ $proposalProjects->count() }} công trình đang hoạt động</span></div>
        <div class="tw-card__body">
            <div class="tw-create-row"><label>Chọn công trình<select id="tw-material-project" class="tw-select"><option value="">— Chọn công trình cần vật tư —</option>
                <?php foreach ($proposalProjects as $project): ?><option value="{{ $project['url'] }}">{{ $project['label'] }}</option><?php endforeach; ?>
            </select></label><button id="tw-open-material-project" class="tw-btn" type="button"><i class="bi bi-arrow-right-circle"></i>Mở và lập đề xuất</button></div>
        </div>
    </section>

    <section class="tw-card"><div class="tw-card__body">
        <form class="tw-filter" method="GET">
            <label class="tw-filter__search">Tìm kiếm<input class="tw-input" name="q" value="{{ request('q') }}" placeholder="Mã phiếu, công trình, địa điểm..."></label>
            <label>Trạng thái<select class="tw-select" name="status"><option value="">Tất cả trạng thái</option>
                <?php foreach ($statuses as $item): ?><option value="{{ $item }}" {{ request('status') === $item ? 'selected' : '' }}>{{ $statusLabels[$item] ?? str_replace('_', ' ', $item) }}</option><?php endforeach; ?>
            </select></label>
            <label>Cảnh báo<select class="tw-select" name="alert"><option value="">Tất cả tình trạng</option><option value="shortage" {{ $alert === 'shortage' ? 'selected' : '' }}>Thiếu tồn kho</option><option value="unmapped" {{ $alert === 'unmapped' ? 'selected' : '' }}>Chưa gắn sản phẩm</option><option value="late" {{ $alert === 'late' ? 'selected' : '' }}>Quá ngày cần</option></select></label>
            <button class="tw-btn"><i class="bi bi-funnel"></i>Lọc</button><a class="tw-btn tw-btn--soft" href="{{ route('technical-workspace.materials.index') }}">Đặt lại</a>
        </form>
    </div></section>

    <section class="tw-card">
        <div class="tw-card__head"><div><h2>Danh sách đề xuất vật tư</h2><p>Ưu tiên xử lý theo ngày cần, cảnh báo tồn kho và ngày thi công.</p></div><span class="tw-pill">{{ $requests->total() }} phiếu</span></div>
        <div class="tw-table-wrap"><table class="tw-table tw-material-table"><thead><tr><th>Phiếu / Công trình</th><th>Mốc cần vật tư</th><th>Khối lượng</th><th>Kiểm tra tồn</th><th>Người đề xuất</th><th>Trạng thái</th><th></th></tr></thead><tbody>
        <?php foreach ($requests as $item): ?>
            <?php
            $installAt = $item->installation_confirmed_at ?: $item->proposed_installation_at;
            $isFinished = in_array($item->status, $finishedStatuses, true);
            $late = $item->needed_at && ! $isFinished && \Carbon\Carbon::parse($item->needed_at)->isPast();
            $hasShortage = ! $isFinished && (bool) $item->has_shortage_alert;
            $hasUnmapped = ! $isFinished && (bool) $item->has_unmapped_alert;
            ?>
            <tr class="{{ $hasShortage || $late ? 'has-alert' : '' }}">
                <td><a class="tw-name tw-link" href="{{ route('project-test.show', $item->project_id) }}">{{ $item->code }} · {{ $item->project_code }}</a><div class="tw-sub">{{ $item->project_name }}</div><div class="tw-sub"><i class="bi bi-geo-alt"></i>{{ \Illuminate\Support\Str::limit($item->project_address, 65) }}</div></td>
                <td><strong>{{ $item->needed_at ? \Carbon\Carbon::parse($item->needed_at)->format('d/m/Y') : 'Chưa đặt ngày' }}</strong><div class="tw-sub">Thi công: {{ $installAt ? \Carbon\Carbon::parse($installAt)->format('d/m/Y H:i') : 'chưa có lịch' }}</div><?php if ($late): ?><span class="tw-operation-status is-danger"><i class="bi bi-clock-history"></i>Quá ngày cần</span><?php endif; ?></td>
                <td><strong>{{ (int) $item->item_count }} mặt hàng</strong><div class="tw-sub">Còn cần: {{ number_format((float) $item->required_qty, 2, ',', '.') }}</div></td>
                <td>
                    <?php if ($isFinished): ?><span class="tw-operation-status is-office"><i class="bi bi-check-circle"></i>Đã xuất đủ</span>
                    <?php elseif ($hasShortage): ?><span class="tw-operation-status is-danger"><i class="bi bi-exclamation-triangle"></i>{{ (int) $item->shortage_item_count > 0 ? 'Thiếu '.((int) $item->shortage_item_count).' mặt hàng' : 'Kho báo thiếu vật tư' }}</span><?php if ((float) $item->shortage_qty > 0): ?><div class="tw-sub">Thiếu khoảng {{ number_format((float) $item->shortage_qty, 2, ',', '.') }}</div><?php endif; ?>
                    <?php elseif ($hasUnmapped): ?><span class="tw-operation-status is-warning"><i class="bi bi-upc-scan"></i>{{ (int) $item->unmapped_item_count }} mục chưa gắn SP</span>
                    <?php else: ?><span class="tw-operation-status is-office"><i class="bi bi-check-circle"></i>Tồn kho đáp ứng</span><?php endif; ?>
                </td>
                <td>{{ $item->requester_name ?: 'Không rõ' }}</td>
                <td><span class="tw-pill {{ $hasShortage || $late ? 'danger' : '' }}">{{ $statusLabels[$item->status] ?? str_replace('_', ' ', $item->status) }}</span></td>
                <td><a class="tw-btn tw-btn--soft" href="{{ route('project-test.show', $item->project_id) }}">Mở đề xuất <i class="bi bi-arrow-right"></i></a></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($requests->count() === 0): ?><tr><td colspan="7"><div class="tw-empty"><i class="bi bi-inbox"></i><strong>Chưa có đề xuất phù hợp</strong><span>Thử bỏ bộ lọc hoặc tạo đề xuất cho một công trình đang hoạt động.</span></div></td></tr><?php endif; ?>
        </tbody></table></div>
        <?php if ($requests->hasPages()): ?><div class="tw-card__body">{{ $requests->links() }}</div><?php endif; ?>
    </section>
</div></div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',function(){var select=document.getElementById('tw-material-project');var button=document.getElementById('tw-open-material-project');if(!select||!button){return;}button.addEventListener('click',function(){if(!select.value){select.focus();return;}window.location.href=select.value;});});
</script>
@endpush
