<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Hóa đơn - {{ $item->code ?? ('PR-'.$item->id) }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 13px; color:#111; }
        .wrap { width: 720px; margin: 0 auto; }
        .header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 14px; }
        .title { font-size: 18px; font-weight: 700; margin:0; }
        .small { color:#555; font-size: 12px; margin-top: 4px; }
        .box { border:1px solid #ddd; border-radius: 10px; padding: 14px; }
        .row { display:flex; gap: 18px; }
        .col { flex: 1; }
        .label { color:#666; font-size: 12px; margin-bottom: 4px; }
        .value { font-weight: 600; }
        .hr { height:1px; background:#e6e6e6; margin: 12px 0; }
        .badge { display:inline-block; padding:4px 10px; border-radius: 999px; font-size: 12px; background:#eef2ff; }
        table { width:100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border:1px solid #e6e6e6; padding: 8px; text-align:left; }
        th { background:#f6f7f9; }
        .right { text-align:right; }
        .muted { color:#666; }
    </style>
</head>
<body>
@php
    $statusText = [
        'draft' => 'Nháp',
        'submitted' => 'Đã gửi duyệt',
        'admin_approved' => 'Admin đã duyệt',
        'admin_rejected' => 'Admin từ chối',
        'accounting_approved' => 'Kế toán đã duyệt',
        'accounting_rejected' => 'Kế toán từ chối',
    ][$item->status ?? 'draft'] ?? ($item->status ?? '');

    $creatorName = null;
    try { $creatorName = optional($item->creator)->name; } catch (\Throwable $e) {}
@endphp

<div class="wrap">
    <div class="header">
        <div>
            <p class="title">HÓA ĐƠN / PHIẾU ĐỀ NGHỊ THANH TOÁN</p>
            <div class="small">
                Mã: <b>{{ $item->code ?? ('PR-'.$item->id) }}</b> —
                Ngày tạo: <b>{{ optional($item->created_at)->format('d/m/Y H:i') }}</b>
            </div>
        </div>
        <div class="badge">{{ $statusText }}</div>
    </div>

    <div class="box">
        <div class="row">
            <div class="col">
                <div class="label">Công ty</div>
                <div class="value">{{ $item->company ?? '-' }}</div>
            </div>
            <div class="col">
                <div class="label">Người tạo</div>
                <div class="value">{{ $creatorName ?: ($item->created_by ?? '-') }}</div>
            </div>
        </div>

        <div class="hr"></div>

        <div class="row">
            <div class="col">
                <div class="label">Người nhận</div>
                <div class="value">{{ $item->receiver_name ?? '-' }}</div>
            </div>
            <div class="col">
                <div class="label">Bộ phận</div>
                <div class="value">{{ $item->department ?? '-' }}</div>
            </div>
        </div>

        <div class="hr"></div>

        <div class="row">
            <div class="col">
                <div class="label">Lý do</div>
                <div class="value">{{ $item->reason ?? '-' }}</div>
            </div>
        </div>

        <div class="hr"></div>

        <table>
            <thead>
            <tr>
                <th>Nội dung</th>
                <th class="right">Số tiền (VND)</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td class="muted">Thanh toán theo phiếu {{ $item->code ?? ('PR-'.$item->id) }}</td>
                <td class="right"><b>{{ number_format((int)($item->amount ?? 0)) }} đ</b></td>
            </tr>
            </tbody>
        </table>

        <div class="hr"></div>

        <div class="row">
            <div class="col">
                <div class="label">Thông tin chuyển khoản</div>
                <div class="value">{{ $item->bank_info ?? '-' }}</div>
            </div>
        </div>

        <div class="hr"></div>

        <div class="row">
            <div class="col">
                <div class="label">Ghi chú Admin</div>
                <div class="value">{{ $item->admin_note ?? '-' }}</div>
            </div>
            <div class="col">
                <div class="label">Ghi chú Kế toán</div>
                <div class="value">{{ $item->accounting_note ?? '-' }}</div>
            </div>
        </div>

        <div class="hr"></div>

        <div class="row">
            <div class="col">
                <div class="label">Admin duyệt</div>
                <div class="value">
                    {{ $item->admin_approved_by ? ('#'.$item->admin_approved_by) : '-' }}
                    @if($item->admin_approved_at)
                        — {{ \Carbon\Carbon::parse($item->admin_approved_at)->format('d/m/Y H:i') }}
                    @endif
                </div>
            </div>
            <div class="col">
                <div class="label">Kế toán duyệt</div>
                <div class="value">
                    {{ $item->accounting_approved_by ? ('#'.$item->accounting_approved_by) : '-' }}
                    @if($item->accounting_approved_at)
                        — {{ \Carbon\Carbon::parse($item->accounting_approved_at)->format('d/m/Y H:i') }}
                    @endif
                </div>
            </div>
        </div>

    </div>
</div>

@includeIf('payment-requests._buibichthao_actions')
</body>
</html>
