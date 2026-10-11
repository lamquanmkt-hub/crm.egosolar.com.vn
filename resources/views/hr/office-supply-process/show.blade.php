@extends('layouts.app')

@section('content')
@include('hr.office-supply-process._style')

<div class="vpp-page">
    @if(session('success'))
        <div class="vpp-alert">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="vpp-error">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="vpp-error">{{ $errors->first() }}</div>
    @endif

    <div class="vpp-mini-hero">
        <div>
            <h1 class="vpp-mini-title">Chi tiết phiếu cấp phát VPP</h1>
            <p class="vpp-mini-sub">
                Mã phiếu: {{ $requestRow->code ?? '-' }}
                · Người nhận: {{ $requestRow->receiver_name ?: $requestRow->requester_name }}
                · Phòng ban: {{ $requestRow->department_name ?? '-' }}
            </p>
        </div>

        <div class="vpp-hero-actions">
            <a class="vpp-btn-outline" href="{{ url('/nhan-su/quy-trinh-phan-bo-vpp') }}">← Quay lại</a>
        </div>
    </div>

    @if(! empty($kpiTracked))
        @php
            $vf = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('H:i d/m/Y') : '—';
            $vdt = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('Y-m-d\TH:i') : '';
        @endphp
        @include('hr.kpi._source-review-panel', ['p' => [
            'id' => 'kpi-hc06',
            'title' => 'Theo dõi KPI HC06 · Cấp phát VPP theo yêu cầu',
            'row' => $kpiRow, 'review' => $kpiReview, 'events' => $kpiEvents, 'files' => $kpiFiles, 'missing' => $kpiMissing,
            'locked' => $kpiLocked, 'isOwner' => $kpiIsHandler, 'canEdit' => $kpiCanEdit, 'canReview' => $kpiCanReview,
            'checks' => $allocationChecks,
            'facts' => [
                ['Người yêu cầu', $kpiNames[(int) $requestRow->requester_user_id] ?? '—'],
                ['Người xử lý (chịu KPI)', $kpiNames[(int) $requestRow->handler_id] ?? '—'],
                ['Người kiểm', $kpiNames[(int) $requestRow->reviewer_id] ?? '—'],
                ['HCNS nhận yêu cầu', $vf($requestRow->request_received_at)],
                ['Hạn cấp phát', $vf($requestRow->due_at)],
                ['Giao thực tế', $vf($requestRow->delivered_at)],
                ['Người nhận tự xác nhận', $requestRow->receipt_confirmed_at ? ($kpiNames[(int) $requestRow->receipt_confirmed_by] ?? '—').' · '.$vf($requestRow->receipt_confirmed_at) : 'Chưa (dùng biên bản ký nhận)'],
                ['Kỳ KPI', $requestRow->due_at ? 'Tháng '.\Carbon\Carbon::parse($requestRow->due_at)->format('m/Y').' (theo tháng của hạn)' : '—'],
            ],
            'routes' => [
                'draft' => route('hr.office-supply-process.kpi.draft', $requestRow->id),
                'submit' => route('hr.office-supply-process.kpi.submit', $requestRow->id),
                'confirm' => route('hr.office-supply-process.kpi.confirm', $requestRow->id),
                'return' => route('hr.office-supply-process.kpi.return', $requestRow->id),
                'upload' => route('hr.office-supply-process.kpi.files.upload', $requestRow->id),
            ],
            'fileUrl' => fn ($f, $download) => route('hr.office-supply-process.kpi.files.show', [$requestRow->id, $f->id]).($download ? '?download=1' : ''),
            'deleteUrl' => fn ($f) => route('hr.office-supply-process.kpi.files.delete', [$requestRow->id, $f->id]),
            'how' => '<b>Ai làm gì:</b> người xử lý ghi giờ nhận yêu cầu, hạn, giờ giao thực tế, tải biên bản/ảnh ký nhận → <em>Gửi kiểm tra</em>; '
                .'người kiểm (khác người xử lý) chấm <em>Mốc đúng thực tế · Đúng loại/đủ số lượng · Người nhận đã ký/xác nhận</em>. '
                .'<b>Khi nào tính:</b> chỉ phiếu đã được kiểm và cả 3 mục đạt; giao ≤ hạn là đúng hạn. Chưa gửi/chờ kiểm chưa vào mẫu số. '
                .'<b>Chưa tính điểm HC06</b> khi cách gộp ba nhóm (H-D04) chưa chốt — chỉ là số đo nhóm cấp phát.',
        ]])

        @if($kpiCanEdit && ! $kpiLocked)
            <details class="vpp-card" style="padding:14px 16px" data-vpp-kpi-edit @if($errors->hasAny(['requester_user_id','handler_id','reviewer_id','request_received_at','due_at','delivered_at'])) open @endif>
                <summary style="cursor:pointer;font-weight:900;color:#0f3b78">Sửa mốc / người của phiếu KPI</summary>
                <form method="POST" action="{{ route('hr.office-supply-process.kpi.update', $requestRow->id) }}" style="margin-top:10px">
                    @csrf
                    @method('PUT')
                    <div class="vpp-form-grid">
                        <div><label class="vpp-label">Người yêu cầu *</label>
                            <select class="vpp-select" name="requester_user_id" required>
                                @foreach($kpiUsers as $u)<option value="{{ $u->id }}" @selected((int) old('requester_user_id', $requestRow->requester_user_id) === (int) $u->id)>{{ $u->name }}</option>@endforeach
                            </select></div>
                        <div><label class="vpp-label">Người xử lý (chịu KPI) *</label>
                            <select class="vpp-select" name="handler_id" required>
                                @foreach($kpiUsers as $u)<option value="{{ $u->id }}" @selected((int) old('handler_id', $requestRow->handler_id) === (int) $u->id)>{{ $u->name }}</option>@endforeach
                            </select></div>
                        <div><label class="vpp-label">Người kiểm (khác người xử lý) *</label>
                            <select class="vpp-select" name="reviewer_id" required>
                                @foreach($kpiUsers as $u)<option value="{{ $u->id }}" @selected((int) old('reviewer_id', $requestRow->reviewer_id) === (int) $u->id)>{{ $u->name }}</option>@endforeach
                            </select></div>
                        <div><label class="vpp-label">HCNS nhận yêu cầu lúc *</label>
                            <input class="vpp-input" type="datetime-local" name="request_received_at" value="{{ old('request_received_at', $vdt($requestRow->request_received_at)) }}" required></div>
                        <div><label class="vpp-label">Hạn cấp phát *</label>
                            <input class="vpp-input" type="datetime-local" name="due_at" value="{{ old('due_at', $vdt($requestRow->due_at)) }}" required></div>
                        <div><label class="vpp-label">Giao thực tế lúc *</label>
                            <input class="vpp-input" type="datetime-local" name="delivered_at" value="{{ old('delivered_at', $vdt($requestRow->delivered_at)) }}" required></div>
                        <div class="vpp-field-full"><label class="vpp-label">Lý do sửa</label>
                            <input class="vpp-input" name="note" maxlength="2000" placeholder="VD: ghi nhầm giờ giao"></div>
                    </div>
                    <div style="margin-top:10px"><button class="vpp-btn" type="submit">Lưu thay đổi</button>
                        <span class="vpp-muted">Nếu phiếu đã gửi/kiểm tra, xác nhận cũ hết hiệu lực — cần gửi lại.</span></div>
                </form>
            </details>
        @endif

        @if($kpiIsRequester && empty($requestRow->receipt_confirmed_at) && ! $kpiLocked)
            <form method="POST" action="{{ route('hr.office-supply-process.kpi.receipt', $requestRow->id) }}" class="vpp-card" style="padding:12px 16px">
                @csrf
                <b>Bạn là người yêu cầu phiếu này.</b> <span class="vpp-muted">Xác nhận đã nhận đủ vật phẩm.</span>
                <button class="vpp-btn" type="submit" style="margin-left:8px">Tôi đã nhận</button>
            </form>
        @endif
    @endif

    <div class="vpp-layout">
        <div>
            <div class="vpp-card">
                <div class="vpp-card-head">
                    <div>
                        <h2 class="vpp-card-title">Vật phẩm đã cấp phát</h2>
                        <div class="vpp-card-note">
                            Ngày cấp:
                            {{ !empty($requestRow->created_at) ? date('d/m/Y H:i', strtotime($requestRow->created_at)) : '-' }}
                        </div>
                    </div>
                </div>

                <div class="vpp-card-body">
                    @if(isset($items) && $items->count())
                        <div class="vpp-table-wrap">
                            <table class="vpp-table">
                                <thead>
                                    <tr>
                                        <th>Vật phẩm</th>
                                        <th>ĐVT</th>
                                        <th>SL đề nghị</th>
                                        <th>SL cấp phát</th>
                                        <th>Tồn hiện tại</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $i)
                                        <tr>
                                            <td><strong>{{ $i->item_name }}</strong></td>
                                            <td>{{ $i->unit ?: 'cái' }}</td>
                                            <td>{{ number_format($i->requested_qty ?? 0, 0) }}</td>
                                            <td>{{ number_format($i->issued_qty ?? $i->hr_qty ?? $i->requested_qty ?? 0, 0) }}</td>
                                            <td>
                                                @if(isset($i->current_stock) && $i->current_stock !== null)
                                                    {{ number_format($i->current_stock, 0) }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="vpp-empty">Phiếu này chưa có vật phẩm.</div>
                    @endif
                </div>
            </div>

            <div class="vpp-card">
                <div class="vpp-card-head">
                    <div>
                        <h2 class="vpp-card-title">Lịch sử trừ / cộng VPP riêng</h2>
                        <div class="vpp-card-note">Lịch sử này chỉ thuộc sổ VPP HR, không liên quan kho chính.</div>
                    </div>
                </div>

                <div class="vpp-card-body">
                    @if(isset($movements) && $movements->count())
                        <div class="vpp-table-wrap">
                            <table class="vpp-table">
                                <thead>
                                    <tr>
                                        <th>Thời gian</th>
                                        <th>Loại</th>
                                        <th>Vật phẩm</th>
                                        <th>SL</th>
                                        <th>Tồn trước</th>
                                        <th>Tồn sau</th>
                                        <th>Người thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($movements as $m)
                                        <tr>
                                            <td>{{ $m->moved_at ? date('d/m/Y H:i', strtotime($m->moved_at)) : '-' }}</td>
                                            <td>
                                                @if($m->type === 'in')
                                                    <span class="vpp-badge vpp-in">Nhập</span>
                                                @else
                                                    <span class="vpp-badge vpp-out">Cấp</span>
                                                @endif
                                            </td>
                                            <td>{{ $m->product_name }}</td>
                                            <td>{{ number_format($m->qty ?? 0, 0) }} {{ $m->unit }}</td>
                                            <td>{{ number_format($m->before_qty ?? 0, 0) }}</td>
                                            <td>{{ number_format($m->after_qty ?? 0, 0) }}</td>
                                            <td>{{ $m->user_name ?: 'Hệ thống' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="vpp-empty">Chưa có lịch sử xuất nhập cho phiếu này.</div>
                    @endif
                </div>
            </div>
        </div>

        <div>
            <div class="vpp-card">
                <div class="vpp-card-head">
                    <div>
                        <h2 class="vpp-card-title">Thông tin phiếu</h2>
                        <div class="vpp-card-note">Thông tin người nhận và phòng ban.</div>
                    </div>
                </div>

                <div class="vpp-card-body">
                    <table class="vpp-table">
                        <tbody>
                            <tr>
                                <th>Mã phiếu</th>
                                <td><strong>{{ $requestRow->code ?? '-' }}</strong></td>
                            </tr>
                            <tr>
                                <th>Người nhận</th>
                                <td>{{ $requestRow->receiver_name ?: $requestRow->requester_name }}</td>
                            </tr>
                            <tr>
                                <th>Phòng ban</th>
                                <td>{{ $requestRow->department_name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Mục đích</th>
                                <td>{{ $requestRow->purpose ?: '-' }}</td>
                            </tr>
                            <tr>
                                <th>Ghi chú</th>
                                <td>{{ $requestRow->note ?: '-' }}</td>
                            </tr>
                            <tr>
                                <th>Ngày tạo</th>
                                <td>{{ !empty($requestRow->created_at) ? date('d/m/Y H:i', strtotime($requestRow->created_at)) : '-' }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div style="height:10px"></div>

                    <form method="POST"
                          action="{{ url('/nhan-su/quy-trinh-phan-bo-vpp/'.$requestRow->id) }}"
                          onsubmit="return confirm('Xoá phiếu này? Nếu phiếu có cấp phát, hệ thống sẽ hoàn lại tồn VPP riêng nếu controller đang hỗ trợ hoàn kho.')">
                        @csrf
                        @method('DELETE')
                        <button class="vpp-btn-danger" type="submit">Xoá phiếu</button>
                    </form>
                </div>
            </div>

            <div class="vpp-card">
                <div class="vpp-card-head">
                    <div>
                        <h2 class="vpp-card-title">Ghi chú</h2>
                        <div class="vpp-card-note">Trang chi tiết đã bỏ biến logs cũ.</div>
                    </div>
                </div>

                <div class="vpp-card-body">
                    <div class="vpp-muted">
                        Trang này chỉ hiển thị chi tiết phiếu cấp phát và lịch sử VPP riêng.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
