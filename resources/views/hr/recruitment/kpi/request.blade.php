@extends('layouts.app')

@section('content')
@include('hr.recruitment.kpi._style')
@php
    $fmt = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('H:i d/m/Y') : '—';
    $fmtD = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('d/m/Y') : '—';
    $dt = fn ($t) => $t ? \Carbon\Carbon::parse($t)->format('Y-m-d\TH:i') : '';
    $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', '.').' đ';
    $num = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $stateLabel = ['counted' => 'Được tính', 'earned' => 'Được tính', 'lost' => 'Không đạt', 'pending' => 'Chờ kiểm / gửi lại', 'waiting' => 'Chưa tính', 'excluded' => 'Không tính', 'missing' => 'Chưa tính — thiếu'];
    $approved = in_array((string) $req->approval_status, ['approved', 'closed'], true);
@endphp
<div class="rk-wrap" data-rk-request="{{ $req->id }}">
    <div class="rk-head">
        <div>
            <h1>KPI tuyển dụng · {{ $req->position }}</h1>
            <p>{{ $req->department }} · {{ $req->quantity }} người · {{ $approvalStatuses[$req->approval_status] ?? $req->approval_status }}
                @if($req->approved_at) ({{ $names[(int) $req->approved_by] ?? '—' }} · {{ $fmt($req->approved_at) }}) @endif</p>
        </div>
        <div class="rk-actions">
            <a class="rk-btn outline" href="{{ route('hr.recruitment.requests') }}#rec-request-{{ $req->id }}">← Yêu cầu tuyển dụng</a>
            <a class="rk-btn outline" href="{{ route('hr.recruitment.onboarding') }}">Tiếp nhận NS mới</a>
        </div>
    </div>

    @if(session('success'))<div class="rk-alert ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rk-alert err">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rk-alert err" data-rk-error>{{ $errors->first() }}</div>@endif
    @if($req->cancelled_at)<div class="rk-alert err">Đợt tuyển đã hủy {{ $fmt($req->cancelled_at) }} bởi {{ $names[(int) $req->cancelled_by] ?? '—' }}: {{ $req->cancel_reason }}</div>@endif
    @if(! $approved)<div class="rk-alert info" data-rk-not-approved>Yêu cầu chưa được duyệt — chưa thêm lượt tuyển theo dõi KPI. Duyệt ở tab “Y/c tuyển dụng” (hệ thống ghi người/giờ duyệt).</div>@endif

    <div class="rk-how">
        <b>Ai làm gì:</b> HCNS phụ trách bật theo dõi KPI, ghi <em>giờ thật</em> HCNS tiếp nhận yêu cầu đã duyệt → thêm lượt tuyển cho ứng viên của yêu cầu → khi ứng viên đi làm ghi
        <em>ngày nhận việc thực tế</em>, nối tài khoản nhân viên, tải chứng cứ → <em>Gửi kiểm tra</em>; người kiểm (khác người phụ trách) xác nhận. Chi phí: gắn chứng từ ĐNTT/phiếu chi/tạm ứng/hoàn tiền đang có và số phân bổ.
        <b>Khi nào tính:</b> HC01 = số ngày từ mốc tiếp nhận tới ngày nhận việc thực tế của lượt đã xác nhận; HC02 = chi phí đã thực chi đã xác nhận ÷ vị trí tuyển thành công;
        HC03 đọc kết quả thử việc ở từng lượt. Ngưỡng/cohort/quy đổi chưa chốt → <b>chỉ số đo và xem trước, chưa phải điểm chính thức</b>.
    </div>

    <div class="rk-card">
        <h2>Phần KPI của yêu cầu</h2>
        <div class="rk-facts">
            <div class="rk-fact"><span>Theo dõi KPI</span><b data-rk-tracked="{{ (int) $req->kpi_tracked }}">{{ $req->kpi_tracked ? 'Đang theo dõi' : 'Chưa bật' }}</b></div>
            <div class="rk-fact"><span>HCNS phụ trách (chịu KPI)</span><b>{{ $names[(int) $req->owner_id] ?? 'Chưa chọn' }}</b></div>
            <div class="rk-fact"><span>Người kiểm</span><b>{{ $names[(int) $req->reviewer_id] ?? 'Chưa chọn' }}</b></div>
            <div class="rk-fact"><span>HCNS tiếp nhận yêu cầu</span><b>{{ $fmt($req->request_received_at) }}</b></div>
            <div class="rk-fact"><span>Suất đang dùng / số lượng</span><b>{{ $activeHires }} / {{ $req->quantity }}</b></div>
            <div class="rk-fact"><span>HC01 bình quân (lượt đã xác nhận)</span><b>{{ $summary['avg_days'] === null ? '—' : $num($summary['avg_days']).' ngày · '.$summary['counted'].' lượt' }}</b></div>
            <div class="rk-fact"><span>HC02 chi phí hợp lệ / vị trí</span><b>{{ $money($summary['cost_total']) }} · {{ $summary['cost_per_hire'] === null ? 'chưa có vị trí' : $money($summary['cost_per_hire']).'/vị trí' }}</b></div>
        </div>
        <p class="rk-note" style="margin-top:8px">Số trên là theo riêng yêu cầu này để đối chiếu. KPI tháng tính theo người phụ trách và tháng nhận việc/thực chi (xem checklist trang KPI).</p>
        @if($canEdit && ! $req->cancelled_at)
            <details @if($errors->hasAny(['owner_id','reviewer_id','request_received_at','kpi_tracked'])) open @endif data-rk-kpi-form>
                <summary style="cursor:pointer;font-weight:900;color:#0f3b78">Sửa phần KPI (người phụ trách, người kiểm, mốc tiếp nhận)</summary>
                <form method="POST" action="{{ route('hr.recruitment.requests.kpi.update', $req->id) }}" style="margin-top:10px">
                    @csrf @method('PUT')
                    <label class="rk-check"><input type="checkbox" name="kpi_tracked" value="1" @checked(old('kpi_tracked', $req->kpi_tracked))> Theo dõi KPI HC01–HC03 cho yêu cầu này</label>
                    <div class="rk-grid" style="margin-top:8px">
                        <div class="rk-field"><label>HCNS phụ trách *</label><select class="rk-input" name="owner_id" required><option value="">-- Chọn --</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected((int) old('owner_id', $req->owner_id ?: auth()->id()) === (int) $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                        <div class="rk-field"><label>Người kiểm (khác người phụ trách) *</label><select class="rk-input" name="reviewer_id" required><option value="">-- Chọn --</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected((int) old('reviewer_id', $req->reviewer_id) === (int) $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                        <div class="rk-field"><label>HCNS tiếp nhận yêu cầu lúc (giờ thật) *</label><input class="rk-input" type="datetime-local" name="request_received_at" value="{{ old('request_received_at', $dt($req->request_received_at)) }}" required></div>
                        <div class="rk-field full"><label>Ghi chú thay đổi</label><input class="rk-input" name="note" maxlength="2000" placeholder="VD: nhận yêu cầu qua email ngày …"></div>
                    </div>
                    <div style="margin-top:10px"><button class="rk-btn" type="submit">Lưu phần KPI</button>
                        <span class="rk-note">Không dùng ngày tạo yêu cầu làm mốc tiếp nhận. Đổi mốc sau khi đã gửi kiểm → các lượt phải gửi lại.</span></div>
                </form>
            </details>
        @endif
    </div>

    <div class="rk-card" data-rk-hires>
        <h2>Lượt tuyển (HC01 nhận việc · HC03 thử việc) — {{ $hireRows->count() }}</h2>
        @if($hireRows->isNotEmpty())
            <div class="rk-table-wrap rk-responsive"><table class="rk-table"><thead><tr><th>Lượt · ứng viên</th><th>Trạng thái</th><th>Dự kiến / thực tế</th><th>Số ngày</th><th>KPI HC01</th><th></th></tr></thead><tbody>
                @foreach($hireRows as $h)
                    <tr data-rk-hire="{{ $h['source_id'] }}" data-rk-state="{{ $h['kpi_state'] }}">
                        <td><b>{{ $h['title'] }}</b><small>Phụ trách: {{ $h['owner_name'] }} · kiểm: {{ $h['reviewer_name'] ?? '—' }}</small></td>
                        <td>{{ $h['work_label'] }}</td>
                        <td>{{ $fmtD($h['actual_at']) }}</td>
                        <td>{{ $h['days'] ?? '—' }}</td>
                        <td><span class="rk-pill {{ $h['kpi_state'] }}">{{ $h['state_label'] ?? $stateLabel[$h['kpi_state']] }}</span><small>{{ $h['reason'] }}</small></td>
                        <td><a class="rk-btn outline" href="{{ $h['url'] }}">Mở</a></td>
                    </tr>
                @endforeach
            </tbody></table></div>
            <div class="rk-cards-mobile">
                @foreach($hireRows as $h)
                    <a class="rk-mcard" href="{{ $h['url'] }}" style="text-decoration:none;color:inherit"><b>{{ $h['title'] }}</b><span class="rk-pill {{ $h['kpi_state'] }}">{{ $h['state_label'] ?? $stateLabel[$h['kpi_state']] }}</span><small>{{ $h['work_label'] }} · {{ $h['reason'] }}</small></a>
                @endforeach
            </div>
        @else
            <p class="rk-note">Chưa có lượt tuyển.</p>
        @endif
        @if($canEdit && $approved && $req->kpi_tracked && ! $req->cancelled_at)
            <form method="POST" action="{{ route('hr.recruitment.hires.store', $req->id) }}" class="rk-grid" style="margin-top:10px" data-rk-hire-create>
                @csrf
                <div class="rk-field"><label>Thêm lượt tuyển cho ứng viên của yêu cầu này</label>
                    <select class="rk-input" name="candidate_id" required><option value="">-- Chọn ứng viên --</option>@foreach($candidates as $c)<option value="{{ $c->id }}">{{ $c->full_name }} ({{ $c->status }})</option>@endforeach</select></div>
                <div class="rk-field" style="justify-content:flex-end"><button class="rk-btn" type="submit">Thêm lượt tuyển</button></div>
                <div class="rk-field"><span class="rk-note">Ứng viên phải được gắn yêu cầu này ở bước Sàng lọc. Không vượt {{ $req->quantity }} suất; “không nhận việc”/“hủy” trả lại suất.</span></div>
            </form>
        @endif
    </div>

    <div class="rk-card" data-rk-costs>
        <h2>Chi phí tuyển dụng phân bổ (HC02) — {{ $costRows->count() }}</h2>
        <p class="rk-note">Chỉ tính phần đã thực chi: ĐNTT/phiếu chi kế toán đã duyệt; tạm ứng chỉ tính số đã quyết toán; hoàn tiền trừ ra. Một chứng từ không phân bổ vượt số thực chi cho nhiều đợt.</p>
        @if($costRows->isNotEmpty())
            <div class="rk-table-wrap rk-responsive"><table class="rk-table"><thead><tr><th>Chứng từ</th><th>Loại / thực chi</th><th>Phân bổ</th><th>KPI HC02</th><th></th></tr></thead><tbody>
                @foreach($costRows as $k)
                    <tr data-rk-cost="{{ $k['source_id'] }}" data-rk-state="{{ $k['kpi_state'] }}">
                        <td><b>{{ $k['title'] }}</b><small>Phụ trách: {{ $k['owner_name'] }} · kiểm: {{ $k['reviewer_name'] ?? '—' }}</small></td>
                        <td>{{ $k['work_label'] }}<small>{{ $fmt($k['actual_at']) }}</small></td>
                        <td>{{ $money($k['amount']) }}</td>
                        <td><span class="rk-pill {{ $k['kpi_state'] }}">{{ $k['state_label'] ?? $stateLabel[$k['kpi_state']] }}</span><small>{{ $k['reason'] }}</small></td>
                        <td><a class="rk-btn outline" href="{{ $k['url'] }}">Mở</a></td>
                    </tr>
                @endforeach
            </tbody></table></div>
            <div class="rk-cards-mobile">
                @foreach($costRows as $k)
                    <a class="rk-mcard" href="{{ $k['url'] }}" style="text-decoration:none;color:inherit"><b>{{ $k['title'] }}</b><span class="rk-pill {{ $k['kpi_state'] }}">{{ $k['state_label'] ?? $stateLabel[$k['kpi_state']] }}</span><small>{{ $money($k['amount']) }} · {{ $k['reason'] }}</small></a>
                @endforeach
            </div>
        @else
            <p class="rk-note">Chưa gắn chứng từ chi phí.</p>
        @endif
        @if($canEdit && $req->kpi_tracked && ! $req->cancelled_at)
            <form method="POST" action="{{ route('hr.recruitment.costs.store', $req->id) }}" class="rk-grid" style="margin-top:10px" data-rk-cost-create>
                @csrf
                <div class="rk-field"><label>Chứng từ (ĐNTT/phiếu chi/tạm ứng/hoàn tiền) *</label>
                    <select class="rk-input" name="payment_request_id" required><option value="">-- Chọn chứng từ --</option>
                        @foreach($documents as $d)<option value="{{ $d->id }}" @selected((int) old('payment_request_id') === (int) $d->id)>{{ $d->code ?: '#'.$d->id }} · {{ \App\Services\Hr\Kpi\RecruitmentKpi::DOC_TYPES[$d->doc_type ?: 'payment_request'] ?? $d->doc_type }} · {{ $money($d->amount) }} · {{ $d->status }} · {{ \Illuminate\Support\Str::limit($d->payment_content ?: $d->reason, 40) }}</option>@endforeach
                    </select></div>
                <div class="rk-field"><label>Số phân bổ cho đợt này (đ) *</label><input class="rk-input" type="number" min="1" step="1" name="allocated_amount" value="{{ old('allocated_amount') }}" required></div>
                <div class="rk-field"><label>Ghi chú</label><input class="rk-input" name="note" maxlength="2000" value="{{ old('note') }}" placeholder="VD: phí đăng tin TopCV tháng 10, chia 2 vị trí"></div>
                <div class="rk-field full"><button class="rk-btn" type="submit" style="max-width:220px">Gắn chi phí</button></div>
            </form>
        @endif
    </div>

    @if($canEdit && ! $req->cancelled_at)
        <details class="rk-card" data-rk-cancel>
            <summary style="cursor:pointer;font-weight:900;color:#be123c">Hủy đợt tuyển</summary>
            <form method="POST" action="{{ route('hr.recruitment.requests.kpi.cancel', $req->id) }}" style="margin-top:10px;display:grid;gap:6px" onsubmit="return confirm('Hủy đợt tuyển? Lượt chưa nhận việc sẽ chuyển “đã hủy”.')">
                @csrf
                <input class="rk-input" name="cancel_reason" minlength="5" required placeholder="Lý do hủy (bắt buộc)">
                <button class="rk-btn danger" type="submit" style="max-width:220px">Hủy đợt tuyển</button>
                <small class="rk-note">Lượt chưa nhận việc chuyển “đã hủy” (không vào bình quân); lượt đã nhận việc giữ nguyên.</small>
            </form>
        </details>
    @endif

    @if($logs->isNotEmpty())
        <div class="rk-card rk-logs" data-rk-logs>
            <h2>Lịch sử phần KPI ({{ $logs->count() }})</h2>
            @foreach($logs as $log)<div>{{ $fmt($log->created_at) }} · {{ $log->actor_name ?: 'Hệ thống' }} · <b>{{ \App\Services\Hr\Kpi\RecruitmentKpi::LOG_LABELS[$log->action] ?? $log->action }}</b>@if($log->note) — {{ $log->note }}@endif</div>@endforeach
        </div>
    @endif
</div>
@endsection
