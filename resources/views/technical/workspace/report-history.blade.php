@extends('layouts.app')
@section('title', 'Lịch sử báo cáo Kỹ thuật')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/technical-workspace.css') }}?v={{ file_exists(public_path('css/technical-workspace.css')) ? filemtime(public_path('css/technical-workspace.css')) : time() }}">
@endpush
@section('content')
<?php
    $modeLabels = [
        'daily' => 'Báo cáo ngày',
        'progress' => 'Tiến độ công trình',
        'performance' => 'Hiệu suất nhân sự',
        'overdue' => 'Công việc quá hạn',
        'incidents' => 'Phát sinh / sự cố',
    ];
?>
<div class="tw-page"><div class="tw-shell">
    <section class="tw-hero tw-hero--compact">
        <div class="tw-hero__row"><div>
            <div class="tw-kicker">V15.5 · KHO BÁO CÁO KỸ THUẬT</div>
            <h1>Lịch sử báo cáo</h1>
            <p>Mỗi lần lưu là một bản chụp độc lập; số liệu cũ không thay đổi khi dữ liệu vận hành được cập nhật.</p>
        </div><div class="tw-title-actions">
            <a class="tw-btn tw-btn--soft" href="{{ route('technical-workspace.reports.progress') }}"><i class="bi bi-bar-chart"></i>Báo cáo tổng hợp</a>
            <a class="tw-btn tw-btn--soft" href="{{ route('technical-workspace.operations.daily-report') }}"><i class="bi bi-calendar-day"></i>Báo cáo ngày</a>
        </div></div>
        @include('technical.workspace.partials-nav')
    </section>

    <?php if (session('success')): ?>
        <section class="tw-alert tw-alert--success"><i class="bi bi-check-circle-fill"></i><div><strong>Đã hoàn tất</strong><span><?= e((string) session('success')) ?></span></div></section>
    <?php endif; ?>
    <?php if (session('error')): ?>
        <section class="tw-alert tw-alert--danger"><i class="bi bi-exclamation-octagon"></i><div><strong>Không thể xử lý</strong><span><?= e((string) session('error')) ?></span></div></section>
    <?php endif; ?>

    <section class="tw-card"><div class="tw-card__body">
        <form class="tw-filter" method="GET">
            <label>Tìm báo cáo<input class="tw-input" type="search" name="q" value="<?= e($keyword) ?>" placeholder="Mã, tên hoặc người tạo"></label>
            <label>Loại báo cáo<select class="tw-input" name="mode">
                <option value="">Tất cả loại</option>
                <?php foreach ($modeLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $mode === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select></label>
            <button class="tw-btn" type="submit"><i class="bi bi-search"></i>Lọc</button>
            <a class="tw-btn tw-btn--soft" href="{{ route('technical-workspace.reports.history') }}">Đặt lại</a>
        </form>
    </div></section>

    <section class="tw-card">
        <div class="tw-card__head"><div><h2>Bản chụp đã lưu</h2><p>Quản lý có thể mở lại hoặc tải đúng dữ liệu tại thời điểm tạo báo cáo.</p></div><span class="tw-pill"><?= number_format((int) $snapshots->total()) ?> báo cáo</span></div>
        <div class="tw-table-wrap"><table class="tw-table tw-report-history-table">
            <thead><tr><th>Mã báo cáo</th><th>Loại</th><th>Kỳ dữ liệu</th><th>Người tạo</th><th>Thời gian lưu</th><th>Thông báo</th><th>Thao tác</th></tr></thead>
            <tbody>
            <?php if ($snapshots->count() === 0): ?>
                <tr><td colspan="7"><div class="tw-empty"><i class="bi bi-inbox"></i><strong>Chưa có báo cáo đã lưu</strong><span>Mở báo cáo ngày hoặc báo cáo tổng hợp rồi bấm “Lưu báo cáo”.</span></div></td></tr>
            <?php else: ?>
                <?php foreach ($snapshots as $snapshot): ?>
                    <?php
                        $from = $snapshot->period_from ? \Carbon\Carbon::parse($snapshot->period_from) : null;
                        $to = $snapshot->period_to ? \Carbon\Carbon::parse($snapshot->period_to) : null;
                        $range = $from && $to && $from->isSameDay($to)
                            ? $from->format('d/m/Y')
                            : (($from?->format('d/m/Y') ?: '—').' - '.($to?->format('d/m/Y') ?: '—'));
                        $rows = json_decode((string) ($snapshot->rows ?: '[]'), true) ?: [];
                    ?>
                    <tr>
                        <td><a class="tw-name tw-link" href="{{ route('technical-workspace.reports.history.show', $snapshot->id) }}"><?= e((string) $snapshot->report_code) ?></a><div class="tw-sub"><?= count($rows) ?> dòng chi tiết</div></td>
                        <td><span class="tw-pill"><?= e($modeLabels[$snapshot->report_type] ?? (string) $snapshot->report_type) ?></span></td>
                        <td><?= e($range) ?></td>
                        <td><div class="tw-name"><?= e((string) ($snapshot->generated_name ?: 'Hệ thống')) ?></div></td>
                        <td><?= e(\Carbon\Carbon::parse($snapshot->generated_at)->format('d/m/Y H:i:s')) ?></td>
                        <td><span class="tw-pill"><?= number_format((int) $snapshot->notification_count) ?> người</span></td>
                        <td><div class="tw-inline-actions"><a class="tw-icon-btn" href="{{ route('technical-workspace.reports.history.show', $snapshot->id) }}" title="Xem báo cáo"><i class="bi bi-eye"></i></a><a class="tw-icon-btn" href="{{ route('technical-workspace.reports.history.export', $snapshot->id) }}" title="Tải Excel"><i class="bi bi-file-earmark-excel"></i></a></div></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table></div>
        <?php if ($snapshots->hasPages()): ?><div class="tw-card__body"><?= $snapshots->links() ?></div><?php endif; ?>
    </section>
</div></div>
@endsection
