@extends('layouts.app')

@section('title', 'Lịch công tác')

@push('styles')
<style>
#egoBusinessTrips{--bt-navy:#0b2940;--bt-teal:#0b9d9a;--bt-line:#dbe6ee;--bt-muted:#6f8190;--bt-bg:#f4f8fb;max-width:1480px;margin:0 auto;padding:18px 22px 34px;color:#102d43}
#egoBusinessTrips *{box-sizing:border-box}
.bt-hero{display:flex;justify-content:space-between;gap:20px;align-items:center;padding:24px 26px;border-radius:20px;background:linear-gradient(120deg,#073a52,#0c8d91);color:#fff;box-shadow:0 16px 40px rgba(10,62,82,.15)}
.bt-hero__eyebrow{font-size:12px;font-weight:800;letter-spacing:.12em;opacity:.78}.bt-hero h1{margin:4px 0 5px;font-size:30px;font-weight:850}.bt-hero p{margin:0;opacity:.82;max-width:760px}
.bt-create-toggle{border:1px solid rgba(255,255,255,.32);background:#fff;color:#0a6172;border-radius:12px;padding:11px 16px;font-weight:800;cursor:pointer;list-style:none;white-space:nowrap}.bt-create-toggle::-webkit-details-marker{display:none}
.bt-alert{margin-top:14px;padding:12px 14px;border-radius:12px;border:1px solid}.bt-alert--ok{background:#ecfdf5;border-color:#a7f3d0;color:#067647}.bt-alert--danger{background:#fff1f2;border-color:#fecdd3;color:#b42318}
.bt-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:16px 0}.bt-stat{background:#fff;border:1px solid var(--bt-line);border-radius:16px;padding:16px;box-shadow:0 8px 24px rgba(12,47,66,.04)}.bt-stat span{display:block;font-size:12px;color:var(--bt-muted);font-weight:800;text-transform:uppercase}.bt-stat strong{display:block;margin-top:5px;font-size:26px;color:var(--bt-navy)}
.bt-panel{background:#fff;border:1px solid var(--bt-line);border-radius:18px;box-shadow:0 8px 28px rgba(12,47,66,.05)}
.bt-create{margin:16px 0}.bt-create[open] .bt-create-toggle{margin-bottom:12px}.bt-create__body{padding:20px;background:#fff;border:1px solid var(--bt-line);border-radius:18px;box-shadow:0 14px 36px rgba(8,54,73,.09)}.bt-create__head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:16px}.bt-create__head h2{margin:0;font-size:20px}.bt-create__head p{margin:4px 0 0;color:var(--bt-muted)}
.bt-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.bt-field{display:flex;flex-direction:column;gap:6px}.bt-field--2{grid-column:span 2}.bt-field--4{grid-column:span 4}.bt-field label{font-size:12px;font-weight:800;color:#38566a}.bt-field input,.bt-field select,.bt-field textarea{width:100%;border:1px solid #ccdce6;background:#fbfdff;border-radius:10px;padding:10px 11px;outline:none;color:#17364b}.bt-field input:focus,.bt-field select:focus,.bt-field textarea:focus{border-color:#55bfc2;box-shadow:0 0 0 3px rgba(13,157,154,.11)}.bt-field textarea{resize:vertical;min-height:86px}
.bt-allowance{grid-column:span 4;padding:14px;border-radius:14px;background:#f7fbfc;border:1px dashed #bfd7df}.bt-allowance__title{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}.bt-allowance__title strong{font-size:14px}.bt-allowance__total{font-size:14px;color:#087b79}.bt-money-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.bt-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:16px}.bt-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border:0;border-radius:10px;padding:9px 12px;font-weight:800;text-decoration:none;cursor:pointer}.bt-btn--primary{background:#0d9d9a;color:#fff}.bt-btn--light{background:#eef5f8;color:#24495f}.bt-btn--success{background:#e8f8ef;color:#087443}.bt-btn--danger{background:#fff0f0;color:#b42318}.bt-btn--warning{background:#fff6df;color:#8a5a00}.bt-btn--xs{padding:7px 9px;font-size:12px}
.bt-filter{display:grid;grid-template-columns:minmax(240px,1fr) 210px auto auto;gap:10px;padding:14px;margin-bottom:14px}.bt-filter input,.bt-filter select{border:1px solid #cfdee7;border-radius:10px;padding:10px 11px;background:#fff}.bt-filter .bt-btn{min-height:42px}
.bt-table-wrap{overflow:auto}.bt-table{width:100%;border-collapse:separate;border-spacing:0;min-width:1180px}.bt-table th{padding:11px 12px;background:#f7fafc;border-bottom:1px solid var(--bt-line);font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#658093;text-align:left;white-space:nowrap}.bt-table td{padding:13px 12px;border-bottom:1px solid #edf2f5;vertical-align:top;font-size:13px}.bt-table tr:last-child td{border-bottom:0}.bt-person strong,.bt-code{display:block;color:#12364e;font-weight:850}.bt-person small,.bt-sub{display:block;margin-top:3px;color:var(--bt-muted);font-size:11px}.bt-location{max-width:240px;font-weight:700}.bt-purpose{max-width:300px;color:#405c6d}.bt-money{font-weight:850;color:#087b79;white-space:nowrap}
.bt-status{display:inline-flex;align-items:center;gap:5px;padding:6px 9px;border-radius:999px;font-size:11px;font-weight:850;white-space:nowrap}.bt-status--pending{background:#fff4d8;color:#956100}.bt-status--approved{background:#e8f4ff;color:#14639b}.bt-status--in_progress{background:#e7faf2;color:#087a4b}.bt-status--completed{background:#e8f8ef;color:#087443}.bt-status--rejected{background:#fff0f0;color:#b42318}.bt-status--cancelled{background:#eef2f4;color:#617381}
.bt-row-actions{display:flex;flex-wrap:wrap;gap:6px;min-width:220px}.bt-inline-details{position:relative}.bt-inline-details summary{list-style:none}.bt-inline-details summary::-webkit-details-marker{display:none}.bt-pop{position:absolute;z-index:30;right:0;top:36px;width:290px;padding:12px;background:#fff;border:1px solid var(--bt-line);border-radius:12px;box-shadow:0 16px 36px rgba(15,49,66,.18)}.bt-pop textarea{width:100%;min-height:72px;border:1px solid #cfdee7;border-radius:9px;padding:9px;margin-bottom:8px}.bt-pop p{margin:0 0 8px;color:#4d6575;font-size:12px}
.bt-empty{text-align:center;padding:50px 20px;color:var(--bt-muted)}.bt-pagination{padding:12px 16px;border-top:1px solid var(--bt-line)}
@media(max-width:900px){#egoBusinessTrips{padding:12px}.bt-hero{align-items:flex-start;flex-direction:column}.bt-stats{grid-template-columns:repeat(2,1fr)}.bt-grid{grid-template-columns:1fr}.bt-field--2,.bt-field--4,.bt-allowance{grid-column:span 1}.bt-money-grid{grid-template-columns:1fr 1fr}.bt-filter{grid-template-columns:1fr 1fr}.bt-filter>input{grid-column:span 2}}
</style>
@endpush

@section('content')
<div id="egoBusinessTrips">
    @php
        $money = static fn ($value): string => number_format((float) $value, 0, ',', '.').' đ';
        $roleStatus = static function ($trip, string $today): array {
            if ($trip->status === 'approved' && $trip->start_date && $trip->end_date
                && $trip->start_date->toDateString() <= $today && $trip->end_date->toDateString() >= $today) {
                return ['in_progress', 'Đang công tác', 'bi-geo-alt-fill'];
            }
            return match ($trip->status) {
                'pending' => ['pending', 'Chờ duyệt', 'bi-hourglass-split'],
                'approved' => ['approved', 'Đã duyệt', 'bi-check2-circle'],
                'completed' => ['completed', 'Hoàn tất', 'bi-check-circle-fill'],
                'rejected' => ['rejected', 'Từ chối', 'bi-x-circle'],
                'cancelled' => ['cancelled', 'Đã hủy', 'bi-slash-circle'],
                default => ['cancelled', ucfirst((string) $trip->status), 'bi-circle'],
            };
        };
    @endphp

    <header class="bt-hero">
        <div>
            <div class="bt-hero__eyebrow">HÀNH CHÁNH · LỊCH NHÂN SỰ NGOÀI VĂN PHÒNG</div>
            <h1>Lịch công tác</h1>
            <p>Tạo lịch, ghi nhận địa điểm và phụ cấp, gửi phê duyệt rồi xác nhận hoàn tất trong một luồng.</p>
        </div>
        <button type="button" class="bt-create-toggle" data-open-business-trip-form><i class="bi bi-plus-circle"></i> Tạo lịch công tác</button>
    </header>

    @if(session('success'))
        <div class="bt-alert bt-alert--ok"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bt-alert bt-alert--danger">
            <strong>Chưa thể lưu:</strong>
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <section class="bt-stats">
        <div class="bt-stat"><span>Chờ duyệt</span><strong>{{ $stats['pending'] }}</strong></div>
        <div class="bt-stat"><span>Đã duyệt / sắp đi</span><strong>{{ $stats['approved'] }}</strong></div>
        <div class="bt-stat"><span>Đang công tác</span><strong>{{ $stats['in_progress'] }}</strong></div>
        <div class="bt-stat"><span>Hoàn tất</span><strong>{{ $stats['completed'] }}</strong></div>
    </section>

    <details class="bt-create" id="tao-lich-cong-tac" @if($errors->any()) open @endif>
        <summary class="bt-create-toggle"><i class="bi bi-calendar-plus"></i> Tạo lịch mới</summary>
        <div class="bt-create__body">
            <div class="bt-create__head">
                <div><h2>Thông tin công tác</h2><p>Nhập nhân viên, ngày, địa điểm, phụ cấp và người phê duyệt.</p></div>
            </div>
            <form method="POST" action="{{ route('business-trips.store') }}" data-business-trip-form>
                @csrf
                <div class="bt-grid">
                    <div class="bt-field bt-field--2">
                        <label>Nhân viên <em>*</em></label>
                        @if($canManageAll)
                            <select name="employee_id" required>
                                <option value="">Chọn nhân viên</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected((int) old('employee_id', $currentUserId) === (int) $employee->id)>
                                        {{ $employee->name }}{{ $employee->department?->name ? ' · '.$employee->department->name : '' }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input type="hidden" name="employee_id" value="{{ $currentUserId }}">
                            <input type="text" value="{{ auth()->user()->name }}" readonly>
                        @endif
                    </div>
                    <div class="bt-field bt-field--2">
                        <label>Người phê duyệt <em>*</em></label>
                        <select name="approver_id" required>
                            <option value="">Chọn người phê duyệt</option>
                            @foreach($approvers as $approver)
                                <option value="{{ $approver->id }}" @selected((int) old('approver_id') === (int) $approver->id)>
                                    {{ $approver->name }}{{ $approver->department?->name ? ' · '.$approver->department->name : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="bt-field">
                        <label>Từ ngày <em>*</em></label>
                        <input type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" required>
                    </div>
                    <div class="bt-field">
                        <label>Đến ngày <em>*</em></label>
                        <input type="date" name="end_date" value="{{ old('end_date', now()->toDateString()) }}" required>
                    </div>
                    <div class="bt-field bt-field--2">
                        <label>Địa điểm công tác <em>*</em></label>
                        <input type="text" name="location" value="{{ old('location') }}" maxlength="500" placeholder="VD: Đà Nẵng / Công trình ABC" required>
                    </div>
                    <div class="bt-field bt-field--4">
                        <label>Nội dung / mục đích công tác <em>*</em></label>
                        <textarea name="purpose" required placeholder="Khảo sát, làm việc với khách hàng, nghiệm thu, hỗ trợ công trình...">{{ old('purpose') }}</textarea>
                    </div>

                    <section class="bt-allowance">
                        <div class="bt-allowance__title">
                            <strong><i class="bi bi-cash-stack"></i> Phụ cấp / chi phí dự kiến</strong>
                            <span class="bt-allowance__total">Tổng: <b data-allowance-total>0 đ</b></span>
                        </div>
                        <div class="bt-money-grid">
                            <div class="bt-field"><label>Phụ cấp ngày</label><input type="number" min="0" step="1000" name="daily_allowance" value="{{ old('daily_allowance', 0) }}" data-allowance></div>
                            <div class="bt-field"><label>Ăn uống</label><input type="number" min="0" step="1000" name="meal_allowance" value="{{ old('meal_allowance', 0) }}" data-allowance></div>
                            <div class="bt-field"><label>Khách sạn</label><input type="number" min="0" step="1000" name="hotel_allowance" value="{{ old('hotel_allowance', 0) }}" data-allowance></div>
                            <div class="bt-field"><label>Di chuyển</label><input type="number" min="0" step="1000" name="transport_allowance" value="{{ old('transport_allowance', 0) }}" data-allowance></div>
                            <div class="bt-field"><label>Chi phí khác</label><input type="number" min="0" step="1000" name="other_allowance" value="{{ old('other_allowance', 0) }}" data-allowance></div>
                            <div class="bt-field"><label>Tạm ứng trước</label><input type="number" min="0" step="1000" name="advance_amount" value="{{ old('advance_amount', 0) }}"></div>
                        </div>
                        <div class="bt-field" style="margin-top:10px"><label>Ghi chú phụ cấp</label><textarea name="allowance_note" rows="2" placeholder="Ghi chú mức khoán, vé xe/máy bay, khách sạn...">{{ old('allowance_note') }}</textarea></div>
                    </section>
                </div>
                <div class="bt-actions">
                    <button type="submit" class="bt-btn bt-btn--primary"><i class="bi bi-send"></i> Tạo lịch & gửi duyệt</button>
                </div>
            </form>
        </div>
    </details>

    <form class="bt-panel bt-filter" method="GET" action="{{ route('business-trips.index') }}">
        <input type="search" name="q" value="{{ $keyword }}" placeholder="Mã lịch, nhân viên, địa điểm, nội dung...">
        <select name="status">
            <option value="">Tất cả trạng thái</option>
            <option value="pending" @selected($status === 'pending')>Chờ duyệt</option>
            <option value="approved" @selected($status === 'approved')>Đã duyệt</option>
            <option value="in_progress" @selected($status === 'in_progress')>Đang công tác</option>
            <option value="completed" @selected($status === 'completed')>Hoàn tất</option>
            <option value="rejected" @selected($status === 'rejected')>Từ chối</option>
            <option value="cancelled" @selected($status === 'cancelled')>Đã hủy</option>
        </select>
        <button class="bt-btn bt-btn--primary" type="submit"><i class="bi bi-funnel"></i> Lọc</button>
        <a class="bt-btn bt-btn--light" href="{{ route('business-trips.index') }}"><i class="bi bi-arrow-counterclockwise"></i> Xóa lọc</a>
    </form>

    <section class="bt-panel">
        <div class="bt-table-wrap">
            <table class="bt-table">
                <thead>
                    <tr>
                        <th>Nhân viên / mã</th>
                        <th>Ngày công tác</th>
                        <th>Địa điểm</th>
                        <th>Nội dung</th>
                        <th>Phụ cấp</th>
                        <th>Người duyệt</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($trips as $item)
                    @php
                        [$statusClass, $statusLabel, $statusIcon] = $roleStatus($item, $today);
                        $canApprove = $item->status === 'pending' && ($canManageAll || (int) $item->approver_id === $currentUserId);
                        $canComplete = $item->status === 'approved' && ($canManageAll || (int) $item->user_id === $currentUserId || (int) $item->approver_id === $currentUserId);
                        $canCancel = in_array($item->status, ['pending', 'approved'], true) && ($canManageAll || (int) $item->user_id === $currentUserId || (int) $item->created_by === $currentUserId);
                    @endphp
                    <tr>
                        <td class="bt-person">
                            <strong>{{ $item->employee?->name ?: 'Không xác định' }}</strong>
                            <small>{{ $item->employee?->department?->name ?: 'Chưa có phòng ban' }}</small>
                            <span class="bt-code">{{ $item->code }}</span>
                        </td>
                        <td>
                            <strong>{{ $item->start_date?->format('d/m/Y') }}</strong>
                            <span class="bt-sub">đến {{ $item->end_date?->format('d/m/Y') }}</span>
                        </td>
                        <td class="bt-location"><i class="bi bi-geo-alt"></i> {{ $item->location }}</td>
                        <td class="bt-purpose">{{ $item->purpose }}</td>
                        <td>
                            <span class="bt-money">{{ $money($item->allowance_total) }}</span>
                            @if((float) $item->advance_amount > 0)
                                <span class="bt-sub">Tạm ứng: {{ $money($item->advance_amount) }}</span>
                            @endif
                        </td>
                        <td>
                            <strong>{{ $item->approver?->name ?: 'Chưa chọn' }}</strong>
                            @if($item->approvedBy)
                                <span class="bt-sub">Duyệt bởi {{ $item->approvedBy->name }}</span>
                            @endif
                        </td>
                        <td><span class="bt-status bt-status--{{ $statusClass }}"><i class="bi {{ $statusIcon }}"></i>{{ $statusLabel }}</span></td>
                        <td>
                            <div class="bt-row-actions">
                                @if($canApprove)
                                    <details class="bt-inline-details">
                                        <summary class="bt-btn bt-btn--success bt-btn--xs"><i class="bi bi-check2-circle"></i>Duyệt</summary>
                                        <div class="bt-pop">
                                            <form method="POST" action="{{ route('business-trips.approve', $item) }}">
                                                @csrf
                                                <p>Xác nhận duyệt lịch {{ $item->code }}?</p>
                                                <textarea name="approval_note" placeholder="Ghi chú phê duyệt (không bắt buộc)"></textarea>
                                                <button class="bt-btn bt-btn--success bt-btn--xs" type="submit">Xác nhận duyệt</button>
                                            </form>
                                        </div>
                                    </details>
                                    <details class="bt-inline-details">
                                        <summary class="bt-btn bt-btn--danger bt-btn--xs"><i class="bi bi-x-circle"></i>Từ chối</summary>
                                        <div class="bt-pop">
                                            <form method="POST" action="{{ route('business-trips.reject', $item) }}">
                                                @csrf
                                                <p>Nhập lý do từ chối.</p>
                                                <textarea name="rejection_reason" required placeholder="Lý do từ chối"></textarea>
                                                <button class="bt-btn bt-btn--danger bt-btn--xs" type="submit">Xác nhận từ chối</button>
                                            </form>
                                        </div>
                                    </details>
                                @endif

                                @if($canComplete)
                                    <details class="bt-inline-details">
                                        <summary class="bt-btn bt-btn--primary bt-btn--xs"><i class="bi bi-check-circle"></i>Hoàn tất</summary>
                                        <div class="bt-pop">
                                            <form method="POST" action="{{ route('business-trips.complete', $item) }}">
                                                @csrf
                                                <p>Xác nhận đã kết thúc chuyến công tác.</p>
                                                <textarea name="completion_note" placeholder="Kết quả / ghi chú hoàn tất"></textarea>
                                                <button class="bt-btn bt-btn--primary bt-btn--xs" type="submit">Đóng lịch công tác</button>
                                            </form>
                                        </div>
                                    </details>
                                @endif

                                @if($canCancel)
                                    <form method="POST" action="{{ route('business-trips.cancel', $item) }}" onsubmit="return confirm('Xác nhận hủy lịch {{ $item->code }}?')">
                                        @csrf
                                        <button class="bt-btn bt-btn--light bt-btn--xs" type="submit"><i class="bi bi-slash-circle"></i>Hủy</button>
                                    </form>
                                @endif

                                @if(!$canApprove && !$canComplete && !$canCancel)
                                    <span class="bt-sub">Không có thao tác</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="bt-empty"><i class="bi bi-calendar2-x" style="font-size:30px"></i><div>Chưa có lịch công tác phù hợp.</div></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($trips->hasPages())
            <div class="bt-pagination">{{ $trips->links('pagination::bootstrap-5') }}</div>
        @endif
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var createBox = document.getElementById('tao-lich-cong-tac');
    var openButtons = document.querySelectorAll('[data-open-business-trip-form]');
    openButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (!createBox) return;
            createBox.open = true;
            window.setTimeout(function () {
                createBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var first = createBox.querySelector('select, input:not([type="hidden"]), textarea');
                if (first) first.focus({ preventScroll: true });
            }, 50);
        });
    });

    if (window.location.hash === '#tao-lich-cong-tac' && createBox) {
        createBox.open = true;
    }

    var form = document.querySelector('[data-business-trip-form]');
    if (!form) return;
    var fields = Array.from(form.querySelectorAll('[data-allowance]'));
    var output = form.querySelector('[data-allowance-total]');
    var formatter = new Intl.NumberFormat('vi-VN');
    var refresh = function () {
        var total = fields.reduce(function (sum, input) {
            return sum + (parseFloat(input.value || '0') || 0);
        }, 0);
        if (output) output.textContent = formatter.format(total) + ' đ';
    };
    fields.forEach(function (field) { field.addEventListener('input', refresh); });
    refresh();
});
</script>
@endpush
