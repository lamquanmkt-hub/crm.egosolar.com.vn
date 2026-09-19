@extends('layouts.app')
@section('title', 'Đề nghị tạm ứng & hoàn ứng')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/ego-payment-advance-modules.css') }}?v=20260826v2">
@endpush
@push('scripts')
<script src="{{ asset('js/ego-payment-advance-modules.js') }}?v=20260826v2" defer></script>
@endpush

@section('content')
@php
    $statusLabels = $statusLabels ?? [];
    $settlementLabels = $settlementLabels ?? [];

    $stageOf = function ($row) use ($statusLabels, $settlementLabels) {
        $p = (string) ($row->status ?? '');
        $s = (string) ($row->settlement_status ?? '');
        $stage = ['label' => $statusLabels[$p] ?? $p, 'tone' => 'muted', 'note' => ''];

        if ($p === 'draft') $stage = ['label' => 'Nháp', 'tone' => 'muted', 'note' => 'Chưa gửi phê duyệt'];
        elseif ($p === 'submitted') $stage = ['label' => 'Chờ QL tài chính duyệt', 'tone' => 'wait', 'note' => 'Giai đoạn tạm ứng'];
        elseif ($p === 'admin_approved') $stage = ['label' => 'Chờ kế toán chi', 'tone' => 'wait', 'note' => 'QL tài chính đã duyệt'];
        elseif ($p === 'admin_rejected') $stage = ['label' => 'QL tài chính từ chối', 'tone' => 'danger', 'note' => 'Cần chỉnh sửa / gửi lại'];
        elseif ($p === 'accounting_rejected') $stage = ['label' => 'Kế toán từ chối chi', 'tone' => 'danger', 'note' => 'Cần chỉnh sửa / gửi lại'];
        elseif ($p === 'accounting_approved') {
            if (in_array($s, ['waiting_settlement', 'draft', 'returned'], true)) {
                $overdue = 0;
                if (!empty($row->settlement_due_date)) {
                    try {
                        $due = \Carbon\Carbon::parse($row->settlement_due_date)->startOfDay();
                        if ($due->lt(now()->startOfDay())) $overdue = $due->diffInDays(now()->startOfDay());
                    } catch (\Throwable $e) {}
                }
                if ($s === 'draft') $stage = ['label' => 'Hoàn ứng nháp', 'tone' => 'blue', 'note' => 'Nhân viên chưa gửi duyệt'];
                elseif ($s === 'returned') $stage = ['label' => 'Cần bổ sung hoàn ứng', 'tone' => 'danger', 'note' => 'Hồ sơ bị trả lại'];
                elseif ($overdue > 0) $stage = ['label' => 'Quá hạn hoàn ứng '.$overdue.' ngày', 'tone' => 'danger', 'note' => 'Cần hoàn ứng ngay'];
                else $stage = ['label' => 'Cần hoàn ứng', 'tone' => 'action', 'note' => 'Kế toán đã chi tạm ứng'];
            } elseif ($s === 'submitted') $stage = ['label' => 'Chờ kế toán đối soát', 'tone' => 'wait', 'note' => 'Đã gửi hồ sơ quyết toán'];
            elseif ($s === 'accounting_checked') $stage = ['label' => 'Chờ QL tài chính duyệt hoàn ứng', 'tone' => 'wait', 'note' => 'Kế toán đã đối soát'];
            elseif ($s === 'management_approved') $stage = ['label' => 'Chờ kế toán đối soát (luồng cũ)', 'tone' => 'wait', 'note' => 'QL tài chính đã duyệt trước khi đổi quy trình'];
            elseif ($s === 'completed') $stage = ['label' => 'Đã quyết toán', 'tone' => 'success', 'note' => 'Hồ sơ đã khép kín'];
            else $stage = ['label' => $settlementLabels[$s] ?? $s, 'tone' => 'muted', 'note' => ''];
        }

        return $stage;
    };
@endphp

<div class="ego-adv-page">
    <header class="ego-adv-header">
        <div class="ego-adv-heading">
            <div class="ego-adv-head-icon"><i class="bi bi-cash-coin"></i></div>
            <div>
                <div class="ego-adv-eyebrow">TRUNG TÂM TÀI CHÍNH</div>
                <h1>Đề nghị tạm ứng &amp; hoàn ứng</h1>
                <p>Một hồ sơ xuyên suốt từ xin tạm ứng đến hoàn ứng và quyết toán.</p>
            </div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
            <button type="button" class="ego-adv-btn" data-bs-toggle="modal" data-bs-target="#createSettlementFromPaymentModal">
                <i class="bi bi-arrow-return-left"></i> Tạo hoàn ứng
            </button>
            <button type="button" class="ego-adv-btn primary" data-bs-toggle="modal" data-bs-target="#createAdvanceModal">
                <i class="bi bi-plus-lg"></i> Tạo tạm ứng
            </button>
        </div>
    </header>

    @if(session('success'))<div class="ego-adv-alert success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="ego-adv-alert danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="ego-adv-alert danger"><strong>Chưa lưu được phiếu:</strong> {{ $errors->first() }}</div>@endif

    <section class="ego-adv-kpis">
        <article class="ego-adv-kpi"><span>Tổng tạm ứng</span><strong>{{ number_format((int)($stats['total'] ?? 0)) }}</strong><small>hồ sơ trong phạm vi xem</small><div class="ico"><i class="bi bi-receipt"></i></div></article>
        <article class="ego-adv-kpi orange"><span>Chờ duyệt / xử lý</span><strong>{{ number_format((int)($stats['pending'] ?? 0)) }}</strong><small>QL tài chính hoặc kế toán cần xử lý</small><div class="ico"><i class="bi bi-hourglass-split"></i></div></article>
        <article class="ego-adv-kpi blue {{ ($stats['overdue_settlement'] ?? 0) > 0 ? 'red' : '' }}"><span>Cần hoàn ứng</span><strong>{{ number_format((int)($stats['need_settlement'] ?? 0)) }}</strong><small>@if(($stats['overdue_settlement'] ?? 0)>0){{ number_format((int)$stats['overdue_settlement']) }} phiếu quá hạn @else đã chi và chờ quyết toán @endif</small><div class="ico"><i class="bi bi-arrow-return-left"></i></div></article>
        <article class="ego-adv-kpi green"><span>Đã quyết toán</span><strong>{{ number_format((int)($stats['completed'] ?? 0)) }}</strong><small>đã hoàn ứng và đóng hồ sơ</small><div class="ico"><i class="bi bi-check2-circle"></i></div></article>
    </section>

    <section class="ego-adv-card">
        <div class="ego-adv-card-head"><div><h2><i class="bi bi-sliders2 me-1"></i>Bộ lọc & tìm kiếm</h2><p>Thu hẹp dữ liệu theo nhu cầu xử lý.</p></div></div>
        <div class="ego-adv-card-body">
            <form method="GET" class="ego-adv-filter-grid">
                <div class="ego-adv-field"><label>Tìm kiếm</label><input class="ego-adv-input" name="q" value="{{ request('q') }}" placeholder="Mã phiếu / Người nhận / Nội dung..."></div>
                <div class="ego-adv-field"><label>Trạng thái</label><select class="ego-adv-select" name="status"><option value="">Tất cả trạng thái</option><option value="submitted" @selected(request('status')==='submitted')>Chờ QL tài chính duyệt</option><option value="admin_approved" @selected(request('status')==='admin_approved')>Chờ kế toán chi</option><option value="need_settlement" @selected(request('status')==='need_settlement')>Cần hoàn ứng</option><option value="settlement:submitted" @selected(request('status')==='settlement:submitted')>Chờ kế toán đối soát</option><option value="settlement:accounting_checked" @selected(request('status')==='settlement:accounting_checked')>Chờ QL tài chính duyệt hoàn ứng</option><option value="settlement:management_approved" @selected(request('status')==='settlement:management_approved')>Luồng cũ: chờ kế toán đối soát</option><option value="completed" @selected(request('status')==='completed')>Đã quyết toán</option></select></div>
                <div class="ego-adv-field"><label>Từ ngày</label><input class="ego-adv-input" type="date" name="date_from" value="{{ request('date_from') }}"></div>
                <div class="ego-adv-field"><label>Đến ngày</label><input class="ego-adv-input" type="date" name="date_to" value="{{ request('date_to') }}"></div>
                <div class="ego-adv-filter-actions" style="grid-column:1/-1"><a class="ego-adv-btn" href="{{ route('payment_advances.index') }}"><i class="bi bi-arrow-counterclockwise"></i>Xóa lọc</a><button class="ego-adv-btn primary"><i class="bi bi-funnel"></i>Áp dụng</button></div>
            </form>
        </div>
    </section>

    <section class="ego-adv-card">
        <div class="ego-adv-card-head"><div><h2>Danh sách tạm ứng &amp; hoàn ứng</h2><p>Gồm phiếu tạm ứng tạo mới và ĐNTT đã được chọn để hoàn ứng.</p></div><span class="ego-adv-stage muted">{{ number_format($items->total()) }} phiếu</span></div>
        <div class="ego-adv-table-wrap">
            <table class="ego-adv-table">
                <colgroup><col style="width:135px"><col style="width:145px"><col style="width:210px"><col style="width:105px"><col style="width:110px"><col style="width:205px"><col style="width:175px"><col style="width:110px"><col style="width:120px"></colgroup>
                <thead><tr><th>Mã phiếu</th><th>Người nhận</th><th>Nội dung / lý do</th><th>Số tiền</th><th>Hạn hoàn ứng</th><th>Tiến độ hồ sơ</th><th>Quyết toán</th><th>Người tạo</th><th>Thao tác</th></tr></thead>
                <tbody>
                @forelse($items as $row)
                    @php
                        $stage = $stageOf($row);
                        $diff = (float)($row->difference_amount ?? 0);
                        $settled = in_array((string)$row->settlement_status, ['submitted','accounting_checked','management_approved','completed'], true) || (float)($row->actual_spent_amount ?? 0) > 0;
                    @endphp
                    <tr>
                        <td><a class="ego-adv-code" href="{{ route('payment_advances.show',$row->id) }}">{{ $row->code }}</a>@if(!str_starts_with((string)$row->code,'DNTU-'))<div><span class="ego-adv-stage blue" style="margin-top:4px">Từ ĐNTT</span></div>@endif<div class="ego-adv-muted">{{ $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('d/m/Y H:i') : '' }}</div></td>
                        <td><strong>{{ $row->receiver_name ?: '—' }}</strong><div class="ego-adv-muted">{{ $row->company ?: '—' }}</div></td>
                        <td>{{ $row->payment_content ?: $row->reason ?: '—' }}</td>
                        <td class="ego-adv-money">{{ number_format((float)$row->amount,0,',','.') }} đ</td>
                        <td>{{ $row->settlement_due_date ? \Carbon\Carbon::parse($row->settlement_due_date)->format('d/m/Y') : '—' }}</td>
                        <td><span class="ego-adv-stage {{ $stage['tone'] }}">{{ $stage['label'] }}</span>@if($stage['note'])<span class="ego-adv-stage-note">{{ $stage['note'] }}</span>@endif</td>
                        <td>
                            @if(!$settled)<span class="ego-adv-muted">Chưa phát sinh</span>
                            @elseif($diff > 0)<strong>Nhân viên hoàn {{ number_format($diff,0,',','.') }} đ</strong><div class="ego-adv-muted">Đã chi {{ number_format((float)$row->actual_spent_amount,0,',','.') }} đ</div>
                            @elseif($diff < 0)<strong>Công ty trả thêm {{ number_format(abs($diff),0,',','.') }} đ</strong><div class="ego-adv-muted">Đã chi {{ number_format((float)$row->actual_spent_amount,0,',','.') }} đ</div>
                            @else <strong>{{ (float)$row->actual_spent_amount > 0 ? 'Đã cân bằng' : '—' }}</strong>@endif
                        </td>
                        <td>{{ $row->creator_name ?: '—' }}</td>
                        <td><div class="ego-adv-actions"><a class="ego-adv-btn sm" href="{{ route('payment_advances.show',$row->id) }}"><i class="bi bi-eye"></i>Xem</a>@if((int)$row->created_by === (int)auth()->id() && $row->status === 'accounting_approved' && in_array($row->settlement_status,['waiting_settlement','draft','returned'],true))<a class="ego-adv-btn sm primary" href="{{ route('payment_advances.show',$row->id) }}#hoan-ung"><i class="bi bi-arrow-return-left"></i>Hoàn ứng</a>@endif</div></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><div class="ego-adv-empty">Chưa có hồ sơ tạm ứng.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if(method_exists($items,'links'))<div class="p-3">{{ $items->links() }}</div>@endif
    </section>
</div>

<div class="modal fade ego-adv-modal" id="createSettlementFromPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><form method="POST" action="{{ route('payment_advances.settlement.import_from_payment_request') }}">@csrf
        <div class="modal-header"><div><div class="ego-adv-eyebrow">HOÀN ỨNG TỪ ĐNTT</div><h5 class="modal-title mb-0">Tạo đề nghị hoàn ứng</h5><small class="text-muted">Chọn một ĐNTT đã được Kế toán xác nhận chi. Hệ thống lấy số tiền và thông tin người nhận từ phiếu nguồn.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="ego-adv-form-grid">
                <div class="wide"><label>Chọn đơn từ ĐNTT *</label>
                    <select class="ego-adv-select" name="payment_request_id" required>
                        <option value="">-- Chọn ĐNTT đã chi --</option>
                        @foreach(($eligiblePaymentRequests ?? collect()) as $source)
                            <option value="{{ $source->id }}">{{ $source->code }} — {{ $source->receiver_name ?: $source->creator_name ?: 'Không rõ người nhận' }} — {{ number_format((float)$source->amount,0,',','.') }} đ — {{ \Illuminate\Support\Str::limit($source->payment_content ?: $source->reason ?: '',70) }}</option>
                        @endforeach
                    </select>
                    <div class="ego-adv-help">Chỉ hiện ĐNTT đã ở trạng thái Kế toán đã chi và chưa có hồ sơ hoàn ứng.</div>
                </div>
                <div><label>Hạn hoàn ứng</label><input class="ego-adv-input" type="date" name="settlement_due_date" value="{{ old('settlement_due_date') }}"></div>
                <div><label>Nguồn dữ liệu</label><input class="ego-adv-input" value="Đề nghị thanh toán (ĐNTT)" readonly></div>
                @if(($eligiblePaymentRequests ?? collect())->isEmpty())
                    <div class="wide"><div class="ego-adv-alert info" style="margin:0">Hiện chưa có ĐNTT đã chi nào đủ điều kiện để tạo hoàn ứng.</div></div>
                @endif
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="ego-adv-btn" data-bs-dismiss="modal">Hủy</button><button class="ego-adv-btn primary" @disabled(($eligiblePaymentRequests ?? collect())->isEmpty())><i class="bi bi-arrow-return-left"></i>Tạo hồ sơ hoàn ứng</button></div>
    </form></div></div>
</div>

<div class="modal fade ego-adv-modal" id="createAdvanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><form method="POST" action="{{ route('payment_advances.store') }}" enctype="multipart/form-data">@csrf
        <div class="modal-header"><div><div class="ego-adv-eyebrow">TÀI CHÍNH NỘI BỘ</div><h5 class="modal-title mb-0">Tạo đề nghị tạm ứng</h5><small class="text-muted">Khởi tạo phiếu và gửi theo luồng phê duyệt.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="ego-adv-form-grid">
            <div><label>Người nhận tạm ứng *</label><input class="ego-adv-input" name="receiver_name" value="{{ old('receiver_name',auth()->user()->name ?? '') }}" required></div>
            <div><label>Số tiền *</label><input class="ego-adv-input" type="text" inputmode="numeric" data-money-input name="amount" value="{{ old('amount') }}" placeholder="1.000.000" required><div class="ego-adv-help">Nhập số tiền VNĐ.</div></div>
            <div><label>Ngày cần tạm ứng</label><input class="ego-adv-input" type="date" name="needed_date" value="{{ old('needed_date') }}"></div>
            <div><label>Hạn hoàn ứng</label><input class="ego-adv-input" type="date" name="settlement_due_date" value="{{ old('settlement_due_date') }}"></div>
            <div class="wide"><label>Lý do / Nội dung tạm ứng *</label><textarea class="ego-adv-textarea" rows="3" name="payment_content" required>{{ old('payment_content') }}</textarea></div>
            <div><label>Phòng ban</label><input class="ego-adv-input" name="department" value="{{ old('department',auth()->user()->department ?? '') }}"></div>
            <div><label>Thông tin nhận tiền</label><input class="ego-adv-input" name="bank_info" value="{{ old('bank_info') }}" placeholder="MB Bank - 0968... - NGUYEN VAN A"></div>
            <div class="wide"><label>Chứng từ tạm ứng</label><input class="ego-adv-input" type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx"><div class="ego-adv-help">Tối đa 10 file, 20MB/file.</div></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="ego-adv-btn" data-bs-dismiss="modal">Hủy</button><button class="ego-adv-btn primary"><i class="bi bi-save"></i>Lưu phiếu</button></div>
    </form></div></div>
</div>

@if($errors->any())
<script>document.addEventListener('DOMContentLoaded',function(){if(window.bootstrap){new bootstrap.Modal(document.getElementById('createAdvanceModal')).show();}});</script>
@endif
@endsection
