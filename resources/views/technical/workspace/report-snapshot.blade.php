@extends('layouts.app')
@section('title', $row->report_code.' · Báo cáo Kỹ thuật')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/technical-workspace.css') }}?v={{ file_exists(public_path('css/technical-workspace.css')) ? filemtime(public_path('css/technical-workspace.css')) : time() }}">
@endpush
@section('content')
<div class="tw-page"><div class="tw-shell">
    <section class="tw-hero tw-hero--compact">
        <div class="tw-hero__row"><div>
            <div class="tw-kicker">BẢN CHỤP BÁO CÁO · <?= e((string) $row->report_code) ?></div>
            <h1><?= e((string) $payload['title']) ?></h1>
            <p>Kỳ dữ liệu <?= e((string) $payload['range_label']) ?> · lưu bởi <?= e((string) ($row->generated_name ?: 'Hệ thống')) ?> lúc <?= e(\Carbon\Carbon::parse($row->generated_at)->format('d/m/Y H:i:s')) ?>.</p>
        </div><div class="tw-title-actions">
            <a class="tw-btn tw-btn--soft" href="{{ route('technical-workspace.reports.history') }}"><i class="bi bi-arrow-left"></i>Về lịch sử</a>
            <a class="tw-btn" href="{{ route('technical-workspace.reports.history.export', $row->id) }}"><i class="bi bi-file-earmark-excel"></i>Tải Excel</a>
        </div></div>
        @include('technical.workspace.partials-nav')
    </section>

    <?php if (session('success')): ?>
        <section class="tw-alert tw-alert--success"><i class="bi bi-check-circle-fill"></i><div><strong>Đã lưu bản chụp</strong><span><?= e((string) session('success')) ?></span></div></section>
    <?php endif; ?>

    <section class="tw-kpis tw-report-summary-grid">
        <?php foreach ($payload['summary'] as $label => $value): ?>
            <article class="tw-kpi"><span><?= e((string) $label) ?></span><strong><?= e(is_numeric($value) ? number_format((float) $value, is_float($value) ? 1 : 0, ',', '.') : (string) $value) ?></strong><small>Dữ liệu tại lúc lưu</small></article>
        <?php endforeach; ?>
    </section>

    <section class="tw-card">
        <div class="tw-card__head"><div><h2>Chi tiết báo cáo</h2><p>Nội dung dưới đây là dữ liệu lịch sử, không tự thay đổi theo dữ liệu hiện tại.</p></div><span class="tw-pill"><?= count($payload['rows']) ?> dòng</span></div>
        <div class="tw-table-wrap"><table class="tw-table tw-snapshot-table">
            <thead><tr><?php foreach ($payload['columns'] as $column): ?><th><?= e((string) $column) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php if (count($payload['rows']) === 0): ?>
                <tr><td colspan="<?= max(1, count($payload['columns'])) ?>"><div class="tw-empty"><i class="bi bi-inbox"></i><strong>Không có dòng chi tiết</strong><span>Báo cáo vẫn giữ nguyên phần tổng quan.</span></div></td></tr>
            <?php else: ?>
                <?php foreach ($payload['rows'] as $detailRow): ?><tr>
                    <?php foreach (array_values($detailRow) as $value): ?><td class="tw-preserve-lines"><?= e((string) $value) ?></td><?php endforeach; ?>
                </tr><?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table></div>
    </section>
</div></div>
@endsection
