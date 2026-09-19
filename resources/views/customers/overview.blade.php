@extends('layouts.app')

@section('title', 'Khách hàng - Tổng quan')

@section('content')
@php
    $money = static fn ($value): string => number_format((float) $value, 0, ',', '.').' đ';
    $statusLabels = [
        'new' => 'Mới',
        'contacted' => 'Đã liên hệ',
        'consulting' => 'Đang chăm sóc',
        'follow_up' => 'Cần follow-up',
        'quoted' => 'Đã báo giá',
        'won' => 'Đã chốt',
        'lost' => 'Đã mất',
        'invalid' => 'Không hợp lệ',
    ];
@endphp

<style>
    .cxo{padding:14px 16px 28px;max-width:1600px;margin:0 auto;color:#152337}
    .cxo-head{display:flex;justify-content:space-between;gap:14px;align-items:center;margin-bottom:12px}
    .cxo-head h1{font-size:26px;line-height:1.1;margin:0;font-weight:900;letter-spacing:-.035em}
    .cxo-head p{margin:5px 0 0;color:#718096;font-size:12.5px}
    .cxo-safe{display:inline-flex;align-items:center;gap:7px;padding:8px 11px;border:1px solid #d9eee7;border-radius:11px;background:#f2fbf8;color:#17735e;font-size:11.5px;font-weight:800;white-space:nowrap}
    .cxo-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:11px}
    .cxo-card{background:#fff;border:1px solid #e4ebf2;border-radius:14px;padding:14px 15px;box-shadow:0 8px 24px rgba(15,23,42,.035)}
    .cxo-label{font-size:10.5px;text-transform:uppercase;letter-spacing:.045em;color:#718096;font-weight:900}
    .cxo-value{font-size:25px;font-weight:900;letter-spacing:-.035em;margin:5px 0 2px;color:#142238}
    .cxo-note{font-size:11px;color:#8b98a8}
    .cxo-panel-grid{display:grid;grid-template-columns:1.15fr .85fr;gap:11px}
    .cxo-panel{background:#fff;border:1px solid #e4ebf2;border-radius:14px;overflow:hidden;box-shadow:0 8px 24px rgba(15,23,42,.035)}
    .cxo-panel-head{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:13px 15px;border-bottom:1px solid #eef2f6}
    .cxo-panel-head h2{font-size:13px;margin:0;font-weight:900}
    .cxo-panel-body{padding:12px 15px}
    .cxo-progress-row{display:grid;grid-template-columns:minmax(120px,1fr) 54px;gap:12px;align-items:center;padding:8px 0;border-bottom:1px dashed #edf1f5}
    .cxo-progress-row:last-child{border-bottom:0}
    .cxo-progress-label{font-size:12px;font-weight:750;color:#425466}
    .cxo-progress-count{text-align:right;font-size:12px;font-weight:900;color:#0d8b74}
    .cxo-shortcuts{display:grid;gap:8px}
    .cxo-shortcut{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px 12px;border:1px solid #e7edf3;border-radius:11px;color:#314255;text-decoration:none;font-weight:800;font-size:12px}
    .cxo-shortcut:hover{background:#f8fbfc;color:#087f6d;border-color:#cdeee5}
    .cxo-shortcut span{display:flex;align-items:center;gap:8px}
    @media(max-width:1100px){.cxo-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.cxo-panel-grid{grid-template-columns:1fr}}
    @media(max-width:650px){.cxo{padding:10px}.cxo-head{align-items:flex-start;flex-direction:column}.cxo-head h1{font-size:22px}.cxo-grid{grid-template-columns:1fr 1fr;gap:8px}.cxo-card{padding:12px}.cxo-value{font-size:21px}}
</style>

<div class="cxo">
    <div class="cxo-head">
        <div>
            <h1>Tổng quan khách hàng</h1>
            <p>Một nơi theo dõi hồ sơ khách, chăm sóc, báo giá và pipeline.</p>
        </div>
        <div class="cxo-safe">
            <i class="bi bi-shield-check"></i>
            Không thay đổi dữ liệu đơn hàng
        </div>
    </div>

    @include('customers._module_nav')

    <div class="cxo-grid">
        <article class="cxo-card">
            <div class="cxo-label">Tổng khách hàng</div>
            <div class="cxo-value">{{ number_format($stats['total'] ?? 0) }}</div>
            <div class="cxo-note">Hồ sơ khách hàng gốc</div>
        </article>
        <article class="cxo-card">
            <div class="cxo-label">Đang chăm sóc</div>
            <div class="cxo-value">{{ number_format($pipelineStats['caring'] ?? 0) }}</div>
            <div class="cxo-note">Consulting / follow-up / quoted</div>
        </article>
        <article class="cxo-card">
            <div class="cxo-label">Đã gửi báo giá</div>
            <div class="cxo-value">{{ number_format($pipelineStats['quote_sent'] ?? 0) }}</div>
            <div class="cxo-note">Đã gửi / đã xem / chờ phản hồi</div>
        </article>
        <article class="cxo-card">
            <div class="cxo-label">Đã chốt</div>
            <div class="cxo-value">{{ number_format($pipelineStats['won'] ?? 0) }}</div>
            <div class="cxo-note">Kết quả pipeline</div>
        </article>
        <article class="cxo-card">
            <div class="cxo-label">Lead</div>
            <div class="cxo-value">{{ number_format($stats['lead'] ?? 0) }}</div>
            <div class="cxo-note">Khách cần tiếp tục chăm sóc</div>
        </article>
        <article class="cxo-card">
            <div class="cxo-label">Member</div>
            <div class="cxo-value">{{ number_format($stats['member'] ?? 0) }}</div>
            <div class="cxo-note">Khách đã nâng hạng</div>
        </article>
        <article class="cxo-card">
            <div class="cxo-label">Cần follow-up</div>
            <div class="cxo-value">{{ number_format($pipelineStats['need_follow'] ?? 0) }}</div>
            <div class="cxo-note">Đến hạn trong 24 giờ</div>
        </article>
        <article class="cxo-card">
            <div class="cxo-label">Giá trị tiềm năng</div>
            <div class="cxo-value" style="font-size:20px">{{ $money($pipelineStats['potential_value'] ?? 0) }}</div>
            <div class="cxo-note">Tổng revenue expectation</div>
        </article>
    </div>

    <div class="cxo-panel-grid">
        <section class="cxo-panel">
            <div class="cxo-panel-head">
                <h2><i class="bi bi-bar-chart-line me-1"></i> Trạng thái Pipeline</h2>
                <span class="cxo-note">{{ number_format($pipelineStats['total'] ?? 0) }} dòng chăm sóc</span>
            </div>
            <div class="cxo-panel-body">
                @forelse($pipelineByStatus as $row)
                    <div class="cxo-progress-row">
                        <div class="cxo-progress-label">{{ $statusLabels[$row->status] ?? ($row->status ?: 'Chưa xác định') }}</div>
                        <div class="cxo-progress-count">{{ number_format($row->total) }}</div>
                    </div>
                @empty
                    <div class="cxo-note">Chưa có dữ liệu pipeline.</div>
                @endforelse
            </div>
        </section>

        <aside class="cxo-panel">
            <div class="cxo-panel-head">
                <h2><i class="bi bi-lightning-charge me-1"></i> Truy cập nhanh</h2>
            </div>
            <div class="cxo-panel-body cxo-shortcuts">
                <a class="cxo-shortcut" href="{{ route('customers.index') }}">
                    <span><i class="bi bi-people"></i> Danh sách khách hàng</span>
                    <i class="bi bi-chevron-right"></i>
                </a>
                <a class="cxo-shortcut" href="{{ route('customers.pipeline') }}">
                    <span><i class="bi bi-kanban"></i> Chăm sóc & Pipeline</span>
                    <i class="bi bi-chevron-right"></i>
                </a>
                <a class="cxo-shortcut" href="{{ route('customers.pipeline', ['quick' => 'quote_sent']) }}">
                    <span><i class="bi bi-file-earmark-check"></i> Khách đã báo giá</span>
                    <i class="bi bi-chevron-right"></i>
                </a>
                <a class="cxo-shortcut" href="{{ route('customers.pipeline', ['quick' => 'need_follow']) }}">
                    <span><i class="bi bi-alarm"></i> Khách cần follow-up</span>
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </aside>
    </div>
</div>
@endsection
