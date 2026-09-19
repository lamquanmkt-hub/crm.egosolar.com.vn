@extends('layouts.app')

@section('content')
<style>
    .hc-wrap{padding:0 4px 34px;font-family:"Be Vietnam Pro",system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
    .hc-head{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;margin-bottom:14px}
    .hc-title{margin:0;font-size:26px;font-weight:950;color:#0f172a;letter-spacing:-.04em}
    .hc-sub{margin-top:5px;font-size:13px;font-weight:750;color:#64748b;max-width:940px}
    .hc-actions,.hc-actions-row,.hc-form-foot{display:flex;gap:8px;flex-wrap:wrap}
    .hc-actions{justify-content:flex-end}
    .hc-btn,.hc-btn-outline,.hc-btn-danger{height:36px;border-radius:11px;padding:0 13px;border:1px solid transparent;display:inline-flex;align-items:center;justify-content:center;gap:6px;font-size:12px;font-weight:900;text-decoration:none;cursor:pointer;white-space:nowrap}
    .hc-btn{background:#0f766e;color:#fff;box-shadow:0 9px 18px rgba(15,118,110,.16)}
    .hc-btn-outline{background:#fff;color:#0f172a;border-color:#dbe3ef}
    .hc-btn-danger{background:#fff1f2;color:#be123c;border-color:#fecdd3}
    .hc-form-foot{margin-top:12px;justify-content:flex-end}
    .hc-stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin:12px 0 14px}
    .hc-stat{position:relative;overflow:hidden;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:14px 15px;box-shadow:0 12px 28px rgba(15,23,42,.05)}
    .hc-stat:after{content:"";position:absolute;right:-22px;top:-24px;width:78px;height:78px;border-radius:999px;background:linear-gradient(135deg,rgba(15,118,110,.12),rgba(14,165,233,.08))}
    .hc-stat-label{position:relative;z-index:1;font-size:11px;font-weight:950;text-transform:uppercase;color:#64748b}
    .hc-stat-value{position:relative;z-index:1;margin-top:7px;font-size:23px;font-weight:950;color:#0f766e}
    .hc-tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:13px}
    .hc-tab{display:inline-flex;align-items:center;gap:7px;height:38px;padding:0 14px;border-radius:999px;border:1px solid #dbe3ef;background:#fff;color:#334155;text-decoration:none;font-size:12px;font-weight:900;box-shadow:0 8px 20px rgba(15,23,42,.04)}
    .hc-tab.active{background:#0f766e;color:#fff;border-color:#0f766e;box-shadow:0 12px 26px rgba(15,118,110,.18)}
    .hc-card{background:#fff;border:1px solid #e2e8f0;border-radius:20px;box-shadow:0 16px 36px rgba(15,23,42,.055);overflow:hidden;margin-bottom:14px}
    .hc-card-head{padding:15px 17px;border-bottom:1px solid #edf2f7;background:linear-gradient(180deg,#ffffff,#f8fafc);display:flex;justify-content:space-between;gap:12px;align-items:center}
    .hc-card-title{margin:0;font-size:16px;font-weight:950;color:#0f172a}
    .hc-card-note{margin-top:3px;font-size:12px;font-weight:700;color:#64748b;line-height:1.45}
    .hc-card-body{padding:16px 17px}
    .hc-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
    .hc-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
    .hc-flow-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .hc-flow{border:1px solid #e2e8f0;background:linear-gradient(180deg,#ffffff,#f8fafc);border-radius:18px;padding:15px;min-height:158px;display:flex;flex-direction:column;justify-content:space-between}
    .hc-flow h4{margin:0;font-size:15px;font-weight:950;color:#0f172a}
    .hc-flow p{margin:7px 0 0;color:#64748b;font-size:12px;font-weight:750;line-height:1.5}
    .hc-flow ul{margin:10px 0 0;padding-left:18px;color:#334155;font-size:12px;font-weight:800;line-height:1.65}
    .hc-flow-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
    .hc-field{display:flex;flex-direction:column;gap:6px}
    .hc-field.full{grid-column:1/-1}
    .hc-label{font-size:12px;font-weight:900;color:#475569}
    .hc-input,.hc-select,.hc-textarea{width:100%;border:1px solid #dbe3ef;border-radius:12px;background:#fff;color:#0f172a;font-size:12px;font-weight:750;outline:none}
    .hc-input,.hc-select{height:38px;padding:0 11px}
    .hc-textarea{min-height:72px;padding:10px 11px;resize:vertical}
    .hc-input:focus,.hc-select:focus,.hc-textarea:focus{border-color:#14b8a6;box-shadow:0 0 0 3px rgba(20,184,166,.12)}
    .hc-table-wrap{border:1px solid #e2e8f0;border-radius:16px;overflow:auto;background:#fff}
    .hc-table{width:100%;border-collapse:collapse;min-width:1150px}
    .hc-table.sm{min-width:900px}
    .hc-table th{background:#f1f5f9;color:#334155;font-size:11px;text-transform:uppercase;letter-spacing:.03em;font-weight:950;padding:10px;border-bottom:1px solid #e2e8f0;text-align:left;white-space:nowrap}
    .hc-table td{padding:9px 10px;border-bottom:1px solid #edf2f7;vertical-align:top;font-size:12px;font-weight:700;color:#0f172a}
    .hc-table tr:last-child td{border-bottom:0}
    .hc-pill{display:inline-flex;align-items:center;height:27px;border-radius:999px;padding:0 10px;border:1px solid #ccfbf1;background:#ecfeff;color:#0f766e;font-size:11px;font-weight:950;white-space:nowrap}
    .hc-pill.red{background:#fff1f2;color:#be123c;border-color:#fecdd3}.hc-pill.yellow{background:#fffbeb;color:#b45309;border-color:#fde68a}.hc-pill.green{background:#f0fdf4;color:#15803d;border-color:#bbf7d0}.hc-pill.gray{background:#f8fafc;color:#475569;border-color:#e2e8f0}
    .hc-alert{margin-bottom:12px;padding:10px 12px;border:1px solid #bbf7d0;background:#f0fdf4;color:#166534;border-radius:14px;font-size:12px;font-weight:850}
    .hc-error{margin-bottom:12px;padding:10px 12px;border:1px solid #fecaca;background:#fff1f2;color:#be123c;border-radius:14px;font-size:12px;font-weight:850}
    .hc-empty{padding:28px 14px;border:1px dashed #cbd5e1;border-radius:14px;background:#f8fafc;color:#64748b;font-size:12px;font-weight:750;text-align:center}
    @media(max-width:1280px){.hc-stats{grid-template-columns:repeat(3,minmax(0,1fr))}.hc-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.hc-flow-grid,.hc-grid-3{grid-template-columns:1fr}}
    @media(max-width:760px){.hc-head{display:block}.hc-actions{justify-content:stretch;margin-top:10px}.hc-stats,.hc-grid{grid-template-columns:1fr}.hc-tab,.hc-btn,.hc-btn-outline,.hc-btn-danger{width:100%}.hc-tabs{display:grid;grid-template-columns:1fr}}
</style>

@php
    $tab = $tab ?? request('tab', 'overview');
    $money = fn($v) => number_format((float)$v, 0, ',', '.') . ' đ';
    $priorityClass = ['urgent' => 'red', 'normal' => 'yellow', 'low' => 'green'];
    $handoverStatusText = [
        'created' => 'Chưa gửi',
        'assigned' => 'Đang gửi',
        'sent' => 'Đang gửi',
        'received' => 'Đã gửi',
        'returned' => 'Trả lại',
        'completed' => 'Hoàn thành',
        'archived' => 'Hoàn thành',
    ];
@endphp

<div class="hc-wrap">
    <div class="hc-head">
        <div>
            <h1 class="hc-title">HC & Vận hành</h1>
            <div class="hc-sub">Gộp đúng luồng: quản lý hồ sơ, quản lý tài sản, chi phí văn phòng, phân bổ VPP, bảo trì trang thiết bị và giao nhận hồ sơ.</div>
        </div>
        <div class="hc-actions">
            <a class="hc-btn-outline" href="{{ route('hr.recruitment.index') }}">Tuyển dụng</a>
            <a class="hc-btn-outline" href="{{ route('hr.office-supply-process.index') }}">Văn phòng phẩm</a>
            <a class="hc-btn-outline" href="{{ route('hr.document-handovers.index') }}">Giao nhận hồ sơ</a>
            <a class="hc-btn-outline" href="{{ route('hr.records.index') }}">HS nhân sự</a>
        </div>
    </div>

    @if(session('success'))
        <div class="hc-alert">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="hc-error">{{ $errors->first() }}</div>
    @endif

    <div class="hc-stats">
        <div class="hc-stat"><div class="hc-stat-label">Tổng dữ liệu</div><div class="hc-stat-value">{{ $stats['total_rows'] ?? 0 }}</div></div>
        <div class="hc-stat"><div class="hc-stat-label">Chi phí tháng</div><div class="hc-stat-value">{{ $money($stats['month_expense'] ?? 0) }}</div></div>
        <div class="hc-stat"><div class="hc-stat-label">Tài sản</div><div class="hc-stat-value">{{ $stats['assets'] ?? 0 }}</div></div>
        <div class="hc-stat"><div class="hc-stat-label">Bảo trì thiết bị</div><div class="hc-stat-value">{{ $stats['maintenance'] ?? 0 }}</div></div>
        <div class="hc-stat"><div class="hc-stat-label">Việc đang xử lý</div><div class="hc-stat-value">{{ $stats['processing_tasks'] ?? 0 }}</div></div>
        <div class="hc-stat"><div class="hc-stat-label">Việc quá hạn</div><div class="hc-stat-value">{{ $stats['overdue_tasks'] ?? 0 }}</div></div>
    </div>

    <div class="hc-tabs">
        <a class="hc-tab {{ $tab === 'overview' ? 'active' : '' }}" href="{{ route('hr.operations.index', ['tab' => 'overview']) }}">Tổng quan HC</a>
        <a class="hc-tab {{ $tab === 'expenses' ? 'active' : '' }}" href="{{ route('hr.operations.index', ['tab' => 'expenses']) }}">Chi phí văn phòng</a>
        <a class="hc-tab {{ $tab === 'assets' ? 'active' : '' }}" href="{{ route('hr.operations.index', ['tab' => 'assets']) }}">Tài sản công ty</a>
        <a class="hc-tab {{ $tab === 'maintenance' ? 'active' : '' }}" href="{{ route('hr.operations.index', ['tab' => 'maintenance']) }}">Bảo trì thiết bị</a>
        <a class="hc-tab {{ $tab === 'suppliers' ? 'active' : '' }}" href="{{ route('hr.operations.index', ['tab' => 'suppliers']) }}">Nhà cung cấp</a>
        <a class="hc-tab {{ $tab === 'tasks' ? 'active' : '' }}" href="{{ route('hr.operations.index', ['tab' => 'tasks']) }}">Việc phát sinh</a>
    </div>

    @if($tab === 'overview')
        <div class="hc-flow-grid">
            <div class="hc-flow">
                <div>
                    <h4>1. Quản lý hồ sơ</h4>
                    <p>Theo dõi hồ sơ nội bộ theo mã đơn, người gửi, người nhận, trạng thái, ngày gửi và hoàn thành.</p>
                    <ul><li>Hồ sơ tuyển dụng</li><li>Hồ sơ lao động</li><li>Hồ sơ lương / hồ sơ nhân sự</li></ul>
                </div>
                <div class="hc-flow-actions"><a class="hc-btn" href="{{ route('hr.document-handovers.index') }}">Mở giao nhận HS</a><a class="hc-btn-outline" href="{{ route('hr.records.index') }}">HS nhân sự</a></div>
            </div>
            <div class="hc-flow">
                <div>
                    <h4>2. Quản lý tài sản</h4>
                    <p>Ghi nhận tài sản mới/cũ, người đang sử dụng, tình trạng, bảo trì và thanh lý.</p>
                    <ul><li>Máy tính, laptop, điện thoại</li><li>Máy in, thiết bị văn phòng</li><li>Thiết bị cần bảo trì / thay thế</li></ul>
                </div>
                <div class="hc-flow-actions"><a class="hc-btn" href="{{ route('hr.operations.index', ['tab' => 'assets']) }}">Mở tài sản</a><a class="hc-btn-outline" href="{{ route('hr.operations.index', ['tab' => 'maintenance']) }}">Bảo trì</a></div>
            </div>
            <div class="hc-flow">
                <div>
                    <h4>3. Chi phí văn phòng</h4>
                    <p>Tách rõ chi phí cố định và chi phí phát sinh để dễ báo cáo, duyệt chi và đối chiếu.</p>
                    <ul><li>Chi phí cố định: {{ $money($stats['fixed_expense'] ?? 0) }}</li><li>Chi phí phát sinh: {{ $money($stats['arising_expense'] ?? 0) }}</li><li>Nước, Internet, VPP, sửa chữa, dịch vụ</li></ul>
                </div>
                <div class="hc-flow-actions"><a class="hc-btn" href="{{ route('hr.operations.index', ['tab' => 'expenses']) }}">Mở chi phí</a></div>
            </div>
            <div class="hc-flow">
                <div>
                    <h4>4. Văn phòng phẩm</h4>
                    <p>Cấp phát văn phòng phẩm theo phòng ban, ghi nhận văn phòng được cấp và định kỳ 1 tháng cấp 1 lần.</p>
                    <ul><li>Cấp phát cho phòng ban nào</li><li>Ghi nhận vật phẩm được cấp</li><li>Theo dõi đã duyệt, đã xuất, đã nhận</li></ul>
                </div>
                <div class="hc-flow-actions"><a class="hc-btn" href="{{ route('hr.office-supply-process.index') }}">Mở phân bổ VPP</a></div>
            </div>
        </div>

        <div class="hc-card">
            <div class="hc-card-head">
                <div>
                    <h3 class="hc-card-title">Quy trình giao nhận hồ sơ</h3>
                    <div class="hc-card-note">Bảng nhanh đúng các cột: Tên HS, Mã đơn, Người gửi, Người nhận, Trạng thái, Ngày gửi, Hoàn thành.</div>
                </div>
                <a class="hc-btn-outline" href="{{ route('hr.document-handovers.index') }}">Xem đầy đủ</a>
            </div>
            <div class="hc-card-body">
                @if($documentHandovers->count())
                    <div class="hc-table-wrap">
                        <table class="hc-table sm">
                            <thead><tr><th>Tên HS</th><th>Mã đơn</th><th>Người gửi</th><th>Người nhận</th><th>Trạng thái</th><th>Ngày gửi</th><th>Hoàn thành</th></tr></thead>
                            <tbody>
                            @foreach($documentHandovers as $row)
                                <tr>
                                    <td>{{ $row->title }}</td>
                                    <td>{{ $row->code ?: '—' }}</td>
                                    <td>{{ $row->current_holder ?: ($row->sender_name ?? '—') }}</td>
                                    <td>{{ $row->receiver_name ?? ($row->assigned_to ?: '—') }}</td>
                                    <td><span class="hc-pill {{ in_array($row->status, ['completed','archived'], true) ? 'green' : (in_array($row->status, ['created'], true) ? 'gray' : 'yellow') }}">{{ $handoverStatusText[$row->status] ?? $row->status }}</span></td>
                                    <td>{{ !empty($row->sent_at) ? \Illuminate\Support\Carbon::parse($row->sent_at)->format('d/m/Y') : '—' }}</td>
                                    <td>{{ !empty($row->completed_at) ? \Illuminate\Support\Carbon::parse($row->completed_at)->format('d/m/Y') : '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="hc-empty">Chưa có hồ sơ giao nhận. Bấm “Xem đầy đủ” để tạo mới.</div>
                @endif
            </div>
        </div>
    @endif

    @if($tab === 'expenses')
        <div class="hc-card">
            <div class="hc-card-head"><div><h3 class="hc-card-title">Chi phí văn phòng</h3><div class="hc-card-note">Quản lý chi phí cố định, chi phí phát sinh, nhà cung cấp, phụ trách và trạng thái thanh toán.</div></div></div>
            <div class="hc-card-body">
                <form method="POST" action="{{ route('hr.operations.expenses.store') }}">
                    @csrf
                    <div class="hc-grid">
                        <div class="hc-field"><label class="hc-label">Mới / Cũ</label><select class="hc-select" name="item_condition">@foreach($conditions as $condition)<option value="{{ $condition }}">{{ $condition }}</option>@endforeach</select></div>
                        <div class="hc-field"><label class="hc-label">Loại chi phí</label><select class="hc-select" name="category">@foreach($expenseCategories as $cat)<option value="{{ $cat }}">{{ $cat }}</option>@endforeach</select></div>
                        <div class="hc-field"><label class="hc-label">Nội dung *</label><input class="hc-input" name="content" placeholder="VD: Tiền nước tháng 6" required></div>
                        <div class="hc-field"><label class="hc-label">Số tiền</label><input class="hc-input" type="number" step="1000" name="amount" placeholder="250000"></div>
                        <div class="hc-field"><label class="hc-label">Nhà cung cấp</label><input class="hc-input" name="supplier_name" placeholder="Tên NCC"></div>
                        <div class="hc-field"><label class="hc-label">Phụ trách</label><input class="hc-input" name="person_in_charge" placeholder="Người phụ trách"></div>
                        <div class="hc-field"><label class="hc-label">Thanh toán</label><select class="hc-select" name="payment_status">@foreach($paymentStatuses as $st)<option value="{{ $st }}">{{ $st }}</option>@endforeach</select></div>
                        <div class="hc-field"><label class="hc-label">Ngày ghi nhận</label><input class="hc-input" type="date" name="expense_date" value="{{ now()->toDateString() }}"></div>
                        <div class="hc-field full"><label class="hc-label">Ghi chú</label><textarea class="hc-textarea" name="note"></textarea></div>
                    </div>
                    <div class="hc-form-foot"><button class="hc-btn" type="submit">+ Thêm chi phí</button></div>
                </form>
            </div>
        </div>

        <div class="hc-card"><div class="hc-card-head"><h3 class="hc-card-title">Danh sách chi phí văn phòng</h3></div><div class="hc-card-body">
            @if($expenses->count())
                <div class="hc-table-wrap"><table class="hc-table"><thead><tr><th>Mới/Cũ</th><th>Loại chi phí</th><th>Nội dung</th><th>Số tiền</th><th>Nhà cung cấp</th><th>Phụ trách</th><th>Thanh toán</th><th>Ngày</th><th>Ghi chú</th><th>Thao tác</th></tr></thead><tbody>
                @foreach($expenses as $row)
                    <tr>
                        <td><select form="exp{{ $row->id }}" class="hc-select" name="item_condition">@foreach($conditions as $condition)<option value="{{ $condition }}" @selected(($row->item_condition ?? '') === $condition)>{{ $condition }}</option>@endforeach</select></td>
                        <td><form id="exp{{ $row->id }}" method="POST" action="{{ route('hr.operations.expenses.update', $row->id) }}">@csrf @method('PUT')<select class="hc-select" name="category">@foreach($expenseCategories as $cat)<option value="{{ $cat }}" @selected(($row->category ?? '') === $cat)>{{ $cat }}</option>@endforeach</select></form></td>
                        <td><input form="exp{{ $row->id }}" class="hc-input" name="content" value="{{ $row->content }}" required></td>
                        <td><input form="exp{{ $row->id }}" class="hc-input" type="number" step="1000" name="amount" value="{{ (float)$row->amount }}"></td>
                        <td><input form="exp{{ $row->id }}" class="hc-input" name="supplier_name" value="{{ $row->supplier_name }}"></td>
                        <td><input form="exp{{ $row->id }}" class="hc-input" name="person_in_charge" value="{{ $row->person_in_charge }}"></td>
                        <td><select form="exp{{ $row->id }}" class="hc-select" name="payment_status">@foreach($paymentStatuses as $st)<option value="{{ $st }}" @selected(($row->payment_status ?? '') === $st)>{{ $st }}</option>@endforeach</select></td>
                        <td><input form="exp{{ $row->id }}" class="hc-input" type="date" name="expense_date" value="{{ $row->expense_date }}"></td>
                        <td><textarea form="exp{{ $row->id }}" class="hc-textarea" name="note">{{ $row->note }}</textarea></td>
                        <td><div class="hc-actions-row"><button form="exp{{ $row->id }}" class="hc-btn" type="submit">Lưu</button><form method="POST" action="{{ route('hr.operations.expenses.destroy', $row->id) }}" onsubmit="return confirm('Xoá chi phí này?')">@csrf @method('DELETE')<button class="hc-btn-danger">Xoá</button></form></div></td>
                    </tr>
                @endforeach
                </tbody></table></div>
            @else
                <div class="hc-empty">Chưa có chi phí văn phòng.</div>
            @endif
        </div></div>
    @endif

    @if($tab === 'assets')
        <div class="hc-card"><div class="hc-card-head"><div><h3 class="hc-card-title">Quản lý tài sản công ty</h3><div class="hc-card-note">Theo dõi tài sản mới/cũ, ngày mua, giá trị, người sử dụng, tình trạng.</div></div></div><div class="hc-card-body">
            <form method="POST" action="{{ route('hr.operations.assets.store') }}">@csrf
                <div class="hc-grid">
                    <div class="hc-field"><label class="hc-label">Mới / Cũ</label><select class="hc-select" name="item_condition">@foreach($conditions as $condition)<option value="{{ $condition }}">{{ $condition }}</option>@endforeach</select></div>
                    <div class="hc-field"><label class="hc-label">Tên tài sản *</label><input class="hc-input" name="asset_name" required></div>
                    <div class="hc-field"><label class="hc-label">Loại tài sản</label><input class="hc-input" name="asset_type" placeholder="Laptop, điện thoại..."></div>
                    <div class="hc-field"><label class="hc-label">Ngày mua</label><input class="hc-input" type="date" name="purchase_date"></div>
                    <div class="hc-field"><label class="hc-label">Giá trị</label><input class="hc-input" type="number" step="1000" name="value"></div>
                    <div class="hc-field"><label class="hc-label">Người sử dụng</label><input class="hc-input" name="assigned_to"></div>
                    <div class="hc-field"><label class="hc-label">Tình trạng</label><select class="hc-select" name="status">@foreach($assetStatuses as $st)<option value="{{ $st }}">{{ $st }}</option>@endforeach</select></div>
                    <div class="hc-field full"><label class="hc-label">Ghi chú</label><textarea class="hc-textarea" name="note"></textarea></div>
                </div><div class="hc-form-foot"><button class="hc-btn" type="submit">+ Thêm tài sản</button></div>
            </form>
        </div></div>
        <div class="hc-card"><div class="hc-card-head"><h3 class="hc-card-title">Danh sách tài sản</h3></div><div class="hc-card-body">
            @if($assets->count())
            <div class="hc-table-wrap"><table class="hc-table"><thead><tr><th>Mới/Cũ</th><th>Tên tài sản</th><th>Loại</th><th>Ngày mua</th><th>Giá trị</th><th>Người sử dụng</th><th>Tình trạng</th><th>Ghi chú</th><th>Thao tác</th></tr></thead><tbody>
            @foreach($assets as $row)
                <tr>
                    <td><select form="asset{{ $row->id }}" class="hc-select" name="item_condition">@foreach($conditions as $condition)<option value="{{ $condition }}" @selected(($row->item_condition ?? '') === $condition)>{{ $condition }}</option>@endforeach</select></td>
                    <td><form id="asset{{ $row->id }}" method="POST" action="{{ route('hr.operations.assets.update', $row->id) }}">@csrf @method('PUT')<input class="hc-input" name="asset_name" value="{{ $row->asset_name }}" required></form></td>
                    <td><input form="asset{{ $row->id }}" class="hc-input" name="asset_type" value="{{ $row->asset_type }}"></td>
                    <td><input form="asset{{ $row->id }}" class="hc-input" type="date" name="purchase_date" value="{{ $row->purchase_date }}"></td>
                    <td><input form="asset{{ $row->id }}" class="hc-input" type="number" step="1000" name="value" value="{{ (float)$row->value }}"></td>
                    <td><input form="asset{{ $row->id }}" class="hc-input" name="assigned_to" value="{{ $row->assigned_to }}"></td>
                    <td><select form="asset{{ $row->id }}" class="hc-select" name="status">@foreach($assetStatuses as $st)<option value="{{ $st }}" @selected(($row->status ?? '') === $st)>{{ $st }}</option>@endforeach</select></td>
                    <td><textarea form="asset{{ $row->id }}" class="hc-textarea" name="note">{{ $row->note }}</textarea></td>
                    <td><div class="hc-actions-row"><button form="asset{{ $row->id }}" class="hc-btn" type="submit">Lưu</button><form method="POST" action="{{ route('hr.operations.assets.destroy', $row->id) }}" onsubmit="return confirm('Xoá tài sản này?')">@csrf @method('DELETE')<button class="hc-btn-danger">Xoá</button></form></div></td>
                </tr>
            @endforeach
            </tbody></table></div>
            @else <div class="hc-empty">Chưa có tài sản.</div> @endif
        </div></div>
    @endif

    @if($tab === 'maintenance')
        <div class="hc-card"><div class="hc-card-head"><div><h3 class="hc-card-title">Quy trình bảo trì trang thiết bị</h3><div class="hc-card-note">Ghi nhận tình trạng, yêu cầu sửa chữa, lý do, người xử lý và trạng thái hoàn thành.</div></div></div><div class="hc-card-body">
            <form method="POST" action="{{ route('hr.operations.maintenance.store') }}">@csrf
                <div class="hc-grid">
                    <div class="hc-field"><label class="hc-label">Tình trạng</label><select class="hc-select" name="item_condition">@foreach($maintenanceConditions as $condition)<option value="{{ $condition }}">{{ $condition }}</option>@endforeach</select></div>
                    <div class="hc-field"><label class="hc-label">Y/c sửa chữa *</label><input class="hc-input" name="content" placeholder="VD: Kiểm tra máy in, sửa máy lạnh..." required></div>
                    <div class="hc-field"><label class="hc-label">Bộ phận yêu cầu</label><select class="hc-select" name="department">@foreach($departments as $dep)<option value="{{ $dep }}">{{ $dep }}</option>@endforeach</select></div>
                    <div class="hc-field"><label class="hc-label">Lý do</label><input class="hc-input" name="note" placeholder="Lý do sửa chữa / thay thế"></div>
                    <div class="hc-field"><label class="hc-label">Mức độ ưu tiên</label><select class="hc-select" name="priority">@foreach($priorities as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="hc-field"><label class="hc-label">Ngày cần xong</label><input class="hc-input" type="date" name="deadline"></div>
                    <div class="hc-field"><label class="hc-label">Người xử lý</label><input class="hc-input" name="assignee"></div>
                    <div class="hc-field"><label class="hc-label">Trạng thái</label><select class="hc-select" name="status">@foreach($taskStatuses as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                </div><div class="hc-form-foot"><button class="hc-btn" type="submit">+ Thêm yêu cầu bảo trì</button></div>
            </form>
        </div></div>
        <div class="hc-card"><div class="hc-card-head"><h3 class="hc-card-title">Danh sách bảo trì trang thiết bị</h3></div><div class="hc-card-body">
            @if($maintenanceTasks->count())
            <div class="hc-table-wrap"><table class="hc-table"><thead><tr><th>Tình trạng</th><th>Y/c sửa chữa</th><th>Bộ phận</th><th>Lý do</th><th>Ưu tiên</th><th>Ngày cần xong</th><th>Người xử lý</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
            @foreach($maintenanceTasks as $row)
                <tr>
                    <td><select form="mnt{{ $row->id }}" class="hc-select" name="item_condition">@foreach($maintenanceConditions as $condition)<option value="{{ $condition }}" @selected(($row->item_condition ?? '') === $condition)>{{ $condition }}</option>@endforeach</select></td>
                    <td><form id="mnt{{ $row->id }}" method="POST" action="{{ route('hr.operations.maintenance.update', $row->id) }}">@csrf @method('PUT')<input class="hc-input" name="content" value="{{ $row->content }}" required></form></td>
                    <td><select form="mnt{{ $row->id }}" class="hc-select" name="department">@foreach($departments as $dep)<option value="{{ $dep }}" @selected(($row->department ?? '') === $dep)>{{ $dep }}</option>@endforeach</select></td>
                    <td><input form="mnt{{ $row->id }}" class="hc-input" name="note" value="{{ $row->note }}"></td>
                    <td><select form="mnt{{ $row->id }}" class="hc-select" name="priority">@foreach($priorities as $key => $label)<option value="{{ $key }}" @selected(($row->priority ?? '') === $key)>{{ $label }}</option>@endforeach</select><div style="margin-top:6px;"><span class="hc-pill {{ $priorityClass[$row->priority] ?? 'yellow' }}">{{ $priorities[$row->priority] ?? $row->priority }}</span></div></td>
                    <td><input form="mnt{{ $row->id }}" class="hc-input" type="date" name="deadline" value="{{ $row->deadline }}"></td>
                    <td><input form="mnt{{ $row->id }}" class="hc-input" name="assignee" value="{{ $row->assignee }}"></td>
                    <td><select form="mnt{{ $row->id }}" class="hc-select" name="status">@foreach($taskStatuses as $key => $label)<option value="{{ $key }}" @selected(($row->status ?? '') === $key)>{{ $label }}</option>@endforeach</select></td>
                    <td><div class="hc-actions-row"><button form="mnt{{ $row->id }}" class="hc-btn" type="submit">Lưu</button><form method="POST" action="{{ route('hr.operations.maintenance.destroy', $row->id) }}" onsubmit="return confirm('Xoá yêu cầu bảo trì này?')">@csrf @method('DELETE')<button class="hc-btn-danger">Xoá</button></form></div></td>
                </tr>
            @endforeach
            </tbody></table></div>
            @else <div class="hc-empty">Chưa có yêu cầu bảo trì trang thiết bị.</div> @endif
        </div></div>
    @endif

    @if($tab === 'suppliers')
        <div class="hc-card"><div class="hc-card-head"><div><h3 class="hc-card-title">Nhà cung cấp</h3><div class="hc-card-note">Quản lý đơn vị cung cấp, người liên hệ, số điện thoại, dịch vụ và trạng thái thanh toán.</div></div></div><div class="hc-card-body">
            <form method="POST" action="{{ route('hr.operations.suppliers.store') }}">@csrf
                <div class="hc-grid">
                    <div class="hc-field"><label class="hc-label">Mới / Cũ</label><select class="hc-select" name="item_condition">@foreach($conditions as $condition)<option value="{{ $condition }}">{{ $condition }}</option>@endforeach</select></div>
                    <div class="hc-field"><label class="hc-label">Tên NCC *</label><input class="hc-input" name="supplier_name" required></div>
                    <div class="hc-field"><label class="hc-label">Người liên hệ</label><input class="hc-input" name="contact_name"></div>
                    <div class="hc-field"><label class="hc-label">SĐT</label><input class="hc-input" name="phone"></div>
                    <div class="hc-field"><label class="hc-label">Dịch vụ cung cấp</label><input class="hc-input" name="service"></div>
                    <div class="hc-field"><label class="hc-label">Thanh toán</label><select class="hc-select" name="payment_status">@foreach($paymentStatuses as $st)<option value="{{ $st }}">{{ $st }}</option>@endforeach</select></div>
                    <div class="hc-field full"><label class="hc-label">Ghi chú</label><textarea class="hc-textarea" name="note"></textarea></div>
                </div><div class="hc-form-foot"><button class="hc-btn" type="submit">+ Thêm NCC</button></div>
            </form>
        </div></div>
        <div class="hc-card"><div class="hc-card-head"><h3 class="hc-card-title">Danh sách nhà cung cấp</h3></div><div class="hc-card-body">
            @if($suppliers->count())
            <div class="hc-table-wrap"><table class="hc-table"><thead><tr><th>Mới/Cũ</th><th>Tên NCC</th><th>Liên hệ</th><th>SĐT</th><th>Dịch vụ</th><th>Thanh toán</th><th>Ghi chú</th><th>Thao tác</th></tr></thead><tbody>
            @foreach($suppliers as $row)
                <tr>
                    <td><select form="sup{{ $row->id }}" class="hc-select" name="item_condition">@foreach($conditions as $condition)<option value="{{ $condition }}" @selected(($row->item_condition ?? '') === $condition)>{{ $condition }}</option>@endforeach</select></td>
                    <td><form id="sup{{ $row->id }}" method="POST" action="{{ route('hr.operations.suppliers.update', $row->id) }}">@csrf @method('PUT')<input class="hc-input" name="supplier_name" value="{{ $row->supplier_name }}" required></form></td>
                    <td><input form="sup{{ $row->id }}" class="hc-input" name="contact_name" value="{{ $row->contact_name }}"></td><td><input form="sup{{ $row->id }}" class="hc-input" name="phone" value="{{ $row->phone }}"></td>
                    <td><input form="sup{{ $row->id }}" class="hc-input" name="service" value="{{ $row->service }}"></td><td><select form="sup{{ $row->id }}" class="hc-select" name="payment_status">@foreach($paymentStatuses as $st)<option value="{{ $st }}" @selected(($row->payment_status ?? '') === $st)>{{ $st }}</option>@endforeach</select></td>
                    <td><textarea form="sup{{ $row->id }}" class="hc-textarea" name="note">{{ $row->note }}</textarea></td>
                    <td><div class="hc-actions-row"><button form="sup{{ $row->id }}" class="hc-btn" type="submit">Lưu</button><form method="POST" action="{{ route('hr.operations.suppliers.destroy', $row->id) }}" onsubmit="return confirm('Xoá NCC này?')">@csrf @method('DELETE')<button class="hc-btn-danger">Xoá</button></form></div></td>
                </tr>
            @endforeach
            </tbody></table></div>
            @else <div class="hc-empty">Chưa có nhà cung cấp.</div> @endif
        </div></div>
    @endif

    @if($tab === 'tasks')
        <div class="hc-card"><div class="hc-card-head"><div><h3 class="hc-card-title">Việc HC phát sinh</h3><div class="hc-card-note">Ghi nhận các việc phát sinh như mua VPP, làm thẻ nhân viên, đặt xe/công tác, họp...</div></div></div><div class="hc-card-body">
            <form method="POST" action="{{ route('hr.operations.tasks.store') }}">@csrf
                <div class="hc-grid">
                    <div class="hc-field"><label class="hc-label">Mới / Cũ</label><select class="hc-select" name="item_condition">@foreach($conditions as $condition)<option value="{{ $condition }}">{{ $condition }}</option>@endforeach</select></div>
                    <div class="hc-field"><label class="hc-label">Loại yêu cầu</label><select class="hc-select" name="task_type">@foreach($taskTypes as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></div>
                    <div class="hc-field"><label class="hc-label">Nội dung yêu cầu *</label><input class="hc-input" name="content" required></div>
                    <div class="hc-field"><label class="hc-label">Bộ phận</label><select class="hc-select" name="department">@foreach($departments as $dep)<option value="{{ $dep }}">{{ $dep }}</option>@endforeach</select></div>
                    <div class="hc-field"><label class="hc-label">Mức độ ưu tiên</label><select class="hc-select" name="priority">@foreach($priorities as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="hc-field"><label class="hc-label">Deadline mong muốn</label><input class="hc-input" type="date" name="deadline"></div>
                    <div class="hc-field"><label class="hc-label">Người xử lý</label><input class="hc-input" name="assignee"></div>
                    <div class="hc-field"><label class="hc-label">Trạng thái</label><select class="hc-select" name="status">@foreach($taskStatuses as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="hc-field full"><label class="hc-label">Ghi chú</label><textarea class="hc-textarea" name="note"></textarea></div>
                </div><div class="hc-form-foot"><button class="hc-btn" type="submit">+ Thêm việc HC</button></div>
            </form>
        </div></div>
        <div class="hc-card"><div class="hc-card-head"><h3 class="hc-card-title">Danh sách việc HC phát sinh</h3></div><div class="hc-card-body">
            @if($tasks->count())
            <div class="hc-table-wrap"><table class="hc-table"><thead><tr><th>Mới/Cũ</th><th>Loại yêu cầu</th><th>Nội dung</th><th>Bộ phận</th><th>Ưu tiên</th><th>Deadline</th><th>Người xử lý</th><th>Trạng thái</th><th>Ghi chú</th><th>Thao tác</th></tr></thead><tbody>
            @foreach($tasks as $row)
                <tr>
                    <td><select form="task{{ $row->id }}" class="hc-select" name="item_condition">@foreach($conditions as $condition)<option value="{{ $condition }}" @selected(($row->item_condition ?? '') === $condition)>{{ $condition }}</option>@endforeach</select></td>
                    <td><form id="task{{ $row->id }}" method="POST" action="{{ route('hr.operations.tasks.update', $row->id) }}">@csrf @method('PUT')<select class="hc-select" name="task_type">@foreach($taskTypes as $type)<option value="{{ $type }}" @selected(($row->task_type ?? '') === $type)>{{ $type }}</option>@endforeach</select></form></td>
                    <td><input form="task{{ $row->id }}" class="hc-input" name="content" value="{{ $row->content }}" required></td>
                    <td><select form="task{{ $row->id }}" class="hc-select" name="department">@foreach($departments as $dep)<option value="{{ $dep }}" @selected(($row->department ?? '') === $dep)>{{ $dep }}</option>@endforeach</select></td>
                    <td><select form="task{{ $row->id }}" class="hc-select" name="priority">@foreach($priorities as $key => $label)<option value="{{ $key }}" @selected(($row->priority ?? '') === $key)>{{ $label }}</option>@endforeach</select><div style="margin-top:6px;"><span class="hc-pill {{ $priorityClass[$row->priority] ?? 'yellow' }}">{{ $priorities[$row->priority] ?? $row->priority }}</span></div></td>
                    <td><input form="task{{ $row->id }}" class="hc-input" type="date" name="deadline" value="{{ $row->deadline }}"></td>
                    <td><input form="task{{ $row->id }}" class="hc-input" name="assignee" value="{{ $row->assignee }}"></td>
                    <td><select form="task{{ $row->id }}" class="hc-select" name="status">@foreach($taskStatuses as $key => $label)<option value="{{ $key }}" @selected(($row->status ?? '') === $key)>{{ $label }}</option>@endforeach</select></td>
                    <td><textarea form="task{{ $row->id }}" class="hc-textarea" name="note">{{ $row->note }}</textarea></td>
                    <td><div class="hc-actions-row"><button form="task{{ $row->id }}" class="hc-btn" type="submit">Lưu</button><form method="POST" action="{{ route('hr.operations.tasks.destroy', $row->id) }}" onsubmit="return confirm('Xoá việc này?')">@csrf @method('DELETE')<button class="hc-btn-danger">Xoá</button></form></div></td>
                </tr>
            @endforeach
            </tbody></table></div>
            @else <div class="hc-empty">Chưa có việc HC phát sinh.</div> @endif
        </div></div>
    @endif
</div>
@endsection
