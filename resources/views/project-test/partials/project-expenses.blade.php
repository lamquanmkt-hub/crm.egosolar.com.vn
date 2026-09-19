@php
    $expenseCategories = [
        'transport' => 'Vận chuyển',
        'external_labor' => 'Thuê nhân công ngoài',
        'commission' => 'Hoa hồng / chi phí bán hàng',
        'loading' => 'Bốc xếp',
        'equipment_rental' => 'Thuê máy / cẩu / thiết bị',
        'subcontractor' => 'Nhà thầu phụ',
        'travel' => 'Ăn ở / công tác',
        'fees' => 'Phí / lệ phí',
        'other' => 'Chi phí khác',
    ];
    $expenseStatusLabels = [
        'pending' => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'rejected' => 'Bị từ chối',
        'cancelled' => 'Đã hủy',
    ];

    $projectExpenses = collect($project->expenses ?? [])->sortByDesc(function ($expense) {
        return optional($expense->expense_date)->format('Ymd').str_pad((string) $expense->id, 12, '0', STR_PAD_LEFT);
    })->values();
    $expensePendingTotal = (float) $projectExpenses->where('status', 'pending')->sum('amount');
    $expenseConfirmedTotal = (float) $projectExpenses->where('status', 'confirmed')->sum('amount');
    $expenseThisMonth = (float) $projectExpenses
        ->where('status', 'confirmed')
        ->filter(fn ($expense) => $expense->expense_date && $expense->expense_date->format('Y-m') === now()->format('Y-m'))
        ->sum('amount');
    $expensePendingCount = $projectExpenses->where('status', 'pending')->count();
    $expenseConfirmedCount = $projectExpenses->where('status', 'confirmed')->count();
    $expenseUser = auth()->user();
    $expenseCanReview = (bool) ($canReviewProjectExpenses ?? false);
    $expenseCanCreate = (bool) ($showProjectExpenseLedger ?? false);
@endphp

<section class="pt-panel" data-pt-panel="expenses">
    <article class="pt-card pt-exp" data-exp-root>
        <div class="pt-exp__head">
            <div>
                <h2><i class="bi bi-receipt-cutoff"></i> Sổ chi phí công trình</h2>
                <p>Sales và Kho ghi nhận chi phí thực tế. Admin/Kế toán xác nhận trước khi số liệu được tính vào lợi nhuận nội bộ.</p>
            </div>
            @if($expenseCanCreate)
                <button class="pt-exp-btn pt-exp-btn--primary" type="button" data-exp-open="expenseCreateDrawer">
                    <i class="bi bi-plus-lg"></i> Thêm chi phí
                </button>
            @endif
        </div>

        <div class="pt-exp-summary">
            <div class="pt-exp-stat">
                <small>Chờ xác nhận</small>
                <strong>{{ number_format($expensePendingTotal, 0, ',', '.') }} đ</strong>
                <span>{{ $expensePendingCount }} khoản</span>
            </div>
            <div class="pt-exp-stat pt-exp-stat--ok">
                <small>Đã xác nhận</small>
                <strong>{{ number_format($expenseConfirmedTotal, 0, ',', '.') }} đ</strong>
                <span>{{ $expenseConfirmedCount }} khoản</span>
            </div>
            <div class="pt-exp-stat">
                <small>Đã xác nhận tháng này</small>
                <strong>{{ number_format($expenseThisMonth, 0, ',', '.') }} đ</strong>
                <span>{{ now()->format('m/Y') }}</span>
            </div>
            <div class="pt-exp-stat pt-exp-stat--info">
                <small>Giá vốn vật tư</small>
                @if($showProjectProfit ?? false)
                    <strong>{{ number_format((float) ($financialSummary['material_cost'] ?? 0), 0, ',', '.') }} đ</strong>
                    <span>Tự lấy từ vật tư đã xuất</span>
                @else
                    <strong>Tự động</strong>
                    <span>Không nhập tay tại đây</span>
                @endif
            </div>
        </div>

        @if(($showProjectProfit ?? false) && (float) ($financialSummary['legacy_manual_cost'] ?? 0) > 0)
            <div class="pt-exp-note">
                <i class="bi bi-info-circle"></i>
                Hệ thống đang giữ <strong>{{ number_format((float) $financialSummary['legacy_manual_cost'], 0, ',', '.') }} đ</strong>
                chi phí tổng hợp từ phiên bản cũ. Khoản này vẫn được cộng vào lợi nhuận để không mất dữ liệu; các khoản mới nên nhập bằng Sổ chi phí bên dưới.
            </div>
        @endif

        <div class="pt-exp-toolbar">
            <div class="pt-exp-filters" role="group" aria-label="Lọc chi phí">
                <button class="pt-exp-filter is-active" type="button" data-exp-filter="all">Tất cả <span>{{ $projectExpenses->count() }}</span></button>
                <button class="pt-exp-filter" type="button" data-exp-filter="pending">Chờ xác nhận <span>{{ $expensePendingCount }}</span></button>
                <button class="pt-exp-filter" type="button" data-exp-filter="confirmed">Đã xác nhận <span>{{ $expenseConfirmedCount }}</span></button>
            </div>
            <div class="pt-exp-search-wrap">
                <i class="bi bi-search"></i>
                <input type="search" class="pt-exp-search" placeholder="Tìm nội dung, người nhận, mã chi phí..." data-exp-search>
            </div>
        </div>

        <div class="pt-exp-table-wrap">
            <table class="pt-exp-table">
                <thead>
                    <tr>
                        <th>Ngày / mã</th>
                        <th>Loại chi phí</th>
                        <th>Nội dung</th>
                        <th>Số tiền</th>
                        <th>Người nhập</th>
                        <th>Trạng thái</th>
                        <th>Chứng từ</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($projectExpenses as $expense)
                        @php
                            $canEditThisExpense = $expense->status === 'pending'
                                && ($expenseCanReview || (int) $expense->created_by === (int) optional($expenseUser)->id);
                            $searchText = mb_strtolower(implode(' ', [
                                $expense->expense_code,
                                $expenseCategories[$expense->category] ?? $expense->category,
                                $expense->description,
                                $expense->payee_name,
                                $expense->creator?->name,
                            ]));
                        @endphp
                        <tr data-exp-row data-status="{{ $expense->status }}" data-search="{{ $searchText }}">
                            <td data-label="Ngày / mã">
                                <strong>{{ optional($expense->expense_date)->format('d/m/Y') ?: '—' }}</strong>
                                <small>{{ $expense->expense_code }}</small>
                            </td>
                            <td data-label="Loại chi phí">
                                <span class="pt-exp-category">{{ $expenseCategories[$expense->category] ?? $expense->category }}</span>
                            </td>
                            <td data-label="Nội dung">
                                <strong>{{ $expense->description }}</strong>
                                @if($expense->payee_name)<small>Chi cho: {{ $expense->payee_name }}</small>@endif
                                @if($expense->note)<small>{{ $expense->note }}</small>@endif
                                @if($expense->rejection_reason)<small class="pt-exp-reason">Lý do từ chối: {{ $expense->rejection_reason }}</small>@endif
                                @if($expense->cancellation_reason)<small class="pt-exp-reason">Lý do hủy: {{ $expense->cancellation_reason }}</small>@endif
                            </td>
                            <td data-label="Số tiền"><strong class="pt-exp-money">{{ number_format((float) $expense->amount, 0, ',', '.') }} đ</strong></td>
                            <td data-label="Người nhập">
                                <strong>{{ $expense->creator?->name ?: 'Hệ thống' }}</strong>
                                <small>{{ optional($expense->created_at)->format('d/m H:i') }}</small>
                            </td>
                            <td data-label="Trạng thái">
                                <span class="pt-exp-status pt-exp-status--{{ $expense->status }}">{{ $expenseStatusLabels[$expense->status] ?? $expense->status }}</span>
                                @if($expense->status === 'confirmed' && $expense->confirmer)
                                    <small>{{ $expense->confirmer->name }}</small>
                                @endif
                            </td>
                            <td data-label="Chứng từ">
                                @if($expense->proof_path)
                                    <a class="pt-exp-proof" href="{{ route('project-test.expenses.proof', [$project, $expense]) }}">
                                        <i class="bi bi-paperclip"></i> Xem file
                                    </a>
                                @else
                                    <span class="pt-exp-muted">—</span>
                                @endif
                            </td>
                            <td data-label="Thao tác">
                                <div class="pt-exp-actions">
                                    @if($expenseCanReview && $expense->status === 'pending')
                                        <form method="POST" action="{{ route('project-test.expenses.confirm', [$project, $expense]) }}" onsubmit="return confirm('Xác nhận khoản chi {{ number_format((float) $expense->amount, 0, ',', '.') }} đ?')">
                                            @csrf
                                            <button class="pt-exp-icon pt-exp-icon--ok" type="submit" title="Xác nhận"><i class="bi bi-check-lg"></i></button>
                                        </form>
                                        <button
                                            class="pt-exp-icon pt-exp-icon--danger"
                                            type="button"
                                            title="Từ chối"
                                            data-exp-reject
                                            data-action="{{ route('project-test.expenses.reject', [$project, $expense]) }}"
                                            data-code="{{ $expense->expense_code }}"
                                        ><i class="bi bi-x-lg"></i></button>
                                    @endif
                                    @if($canEditThisExpense)
                                        <button
                                            class="pt-exp-icon"
                                            type="button"
                                            title="Sửa"
                                            data-exp-edit
                                            data-action="{{ route('project-test.expenses.update', [$project, $expense]) }}"
                                            data-date="{{ optional($expense->expense_date)->format('Y-m-d') }}"
                                            data-category="{{ $expense->category }}"
                                            data-description="{{ $expense->description }}"
                                            data-amount="{{ $expense->amount }}"
                                            data-payee="{{ $expense->payee_name }}"
                                            data-note="{{ $expense->note }}"
                                        ><i class="bi bi-pencil"></i></button>
                                        <button
                                            class="pt-exp-icon"
                                            type="button"
                                            title="Hủy"
                                            data-exp-cancel
                                            data-action="{{ route('project-test.expenses.cancel', [$project, $expense]) }}"
                                            data-code="{{ $expense->expense_code }}"
                                        ><i class="bi bi-slash-circle"></i></button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="pt-exp-empty"><i class="bi bi-receipt"></i><strong>Chưa có chi phí phát sinh</strong><p>Sales hoặc Kho có thể bấm “Thêm chi phí” để ghi nhận vận chuyển, nhân công ngoài, hoa hồng và các khoản phát sinh khác.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pt-exp-footnote">
            <i class="bi bi-shield-check"></i>
            Chi phí <strong>Chờ xác nhận</strong> chưa được tính vào lợi nhuận. Chỉ khoản <strong>Đã xác nhận</strong> mới được cộng vào tổng chi phí nội bộ.
        </div>
    </article>

    @if($expenseCanCreate)
        <div class="pt-exp-drawer" id="expenseCreateDrawer" hidden aria-hidden="true">
            <button class="pt-exp-drawer__backdrop" type="button" data-exp-close></button>
            <aside class="pt-exp-drawer__panel" role="dialog" aria-modal="true" aria-label="Thêm chi phí công trình">
                <div class="pt-exp-drawer__head">
                    <div><h3>Thêm chi phí công trình</h3><p>Nhập từng khoản phát sinh để dễ đối soát và tính lợi nhuận chính xác.</p></div>
                    <button class="pt-exp-close" type="button" data-exp-close><i class="bi bi-x-lg"></i></button>
                </div>
                <form method="POST" enctype="multipart/form-data" action="{{ route('project-test.expenses.store', $project) }}" class="pt-exp-form">
                    @csrf
                    <div><label>Ngày chi phí</label><input type="date" name="expense_date" value="{{ now()->format('Y-m-d') }}" required></div>
                    <div><label>Loại chi phí</label><select name="category" required>@foreach($expenseCategories as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="pt-exp-form__full"><label>Nội dung</label><input name="description" required maxlength="500" placeholder="VD: Thuê đội kéo dây DC công trình"></div>
                    <div><label>Số tiền</label><input type="number" name="amount" min="1" step="1" inputmode="numeric" required></div>
                    <div><label>Người / đơn vị nhận</label><input name="payee_name" maxlength="255" placeholder="Tên tài xế, đội thi công, CTV..."></div>
                    <div class="pt-exp-form__full"><label>Chứng từ</label><input type="file" name="proof_file" accept="image/*,.pdf,.xlsx,.xls,.doc,.docx"><small>Ảnh, PDF, Excel hoặc Word; tối đa 15 MB.</small></div>
                    <div class="pt-exp-form__full"><label>Ghi chú</label><textarea name="note" placeholder="Số xe, tuyến vận chuyển, nội dung hoa hồng, thông tin đối soát..."></textarea></div>
                    @if(!$expenseCanReview)
                        <div class="pt-exp-form__full pt-exp-form-note"><i class="bi bi-info-circle"></i> Khoản này sẽ ở trạng thái <strong>Chờ xác nhận</strong> cho đến khi Admin/Kế toán duyệt.</div>
                    @endif
                    <div class="pt-exp-form__full pt-exp-form__actions"><button class="pt-exp-btn" type="button" data-exp-close>Hủy</button><button class="pt-exp-btn pt-exp-btn--primary" type="submit">Lưu chi phí</button></div>
                </form>
            </aside>
        </div>

        <div class="pt-exp-drawer" id="expenseEditDrawer" hidden aria-hidden="true">
            <button class="pt-exp-drawer__backdrop" type="button" data-exp-close></button>
            <aside class="pt-exp-drawer__panel" role="dialog" aria-modal="true" aria-label="Sửa chi phí công trình">
                <div class="pt-exp-drawer__head"><div><h3>Sửa khoản chi phí</h3><p>Chỉ sửa được khi khoản chi vẫn đang chờ xác nhận.</p></div><button class="pt-exp-close" type="button" data-exp-close><i class="bi bi-x-lg"></i></button></div>
                <form method="POST" enctype="multipart/form-data" action="" class="pt-exp-form" data-exp-edit-form>
                    @csrf
                    @method('PUT')
                    <div><label>Ngày chi phí</label><input type="date" name="expense_date" required></div>
                    <div><label>Loại chi phí</label><select name="category" required>@foreach($expenseCategories as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="pt-exp-form__full"><label>Nội dung</label><input name="description" required maxlength="500"></div>
                    <div><label>Số tiền</label><input type="number" name="amount" min="1" step="1" inputmode="numeric" required></div>
                    <div><label>Người / đơn vị nhận</label><input name="payee_name" maxlength="255"></div>
                    <div class="pt-exp-form__full"><label>Thay chứng từ</label><input type="file" name="proof_file" accept="image/*,.pdf,.xlsx,.xls,.doc,.docx"><small>Bỏ trống để giữ chứng từ hiện tại.</small></div>
                    <div class="pt-exp-form__full"><label>Ghi chú</label><textarea name="note"></textarea></div>
                    <div class="pt-exp-form__full pt-exp-form__actions"><button class="pt-exp-btn" type="button" data-exp-close>Hủy</button><button class="pt-exp-btn pt-exp-btn--primary" type="submit">Lưu thay đổi</button></div>
                </form>
            </aside>
        </div>

        <div class="pt-exp-drawer" id="expenseReasonDrawer" hidden aria-hidden="true">
            <button class="pt-exp-drawer__backdrop" type="button" data-exp-close></button>
            <aside class="pt-exp-drawer__panel pt-exp-drawer__panel--compact" role="dialog" aria-modal="true" aria-label="Lý do xử lý chi phí">
                <div class="pt-exp-drawer__head"><div><h3 data-exp-reason-title>Xử lý chi phí</h3><p data-exp-reason-subtitle></p></div><button class="pt-exp-close" type="button" data-exp-close><i class="bi bi-x-lg"></i></button></div>
                <form method="POST" action="" class="pt-exp-form" data-exp-reason-form>
                    @csrf
                    <div class="pt-exp-form__full"><label>Lý do</label><textarea name="reason" required placeholder="Nhập lý do để lưu lịch sử đối soát"></textarea></div>
                    <div class="pt-exp-form__full pt-exp-form__actions"><button class="pt-exp-btn" type="button" data-exp-close>Đóng</button><button class="pt-exp-btn pt-exp-btn--primary" type="submit">Xác nhận</button></div>
                </form>
            </aside>
        </div>
    @endif
</section>

<style>
.pt-exp{--ex-navy:#142f49;--ex-muted:#74869a;--ex-line:#dce6ef;--ex-soft:#f6f9fc;--ex-brand:#0b9d87;--ex-green:#087f6d;--ex-orange:#a76512;--ex-red:#bf3542;padding:18px}.pt-exp *{box-sizing:border-box}.pt-exp__head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px}.pt-exp__head h2{margin:0;color:var(--ex-navy);font-size:19px}.pt-exp__head p{margin:5px 0 0;color:var(--ex-muted);font-size:11px;line-height:1.55}.pt-exp-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:38px;padding:8px 13px;border:1px solid var(--ex-line);border-radius:10px;background:#fff;color:#29445f;font-size:11px;font-weight:800;cursor:pointer}.pt-exp-btn--primary{border-color:var(--ex-brand);background:var(--ex-brand);color:#fff}.pt-exp-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}.pt-exp-stat{min-height:94px;padding:14px;border:1px solid var(--ex-line);border-radius:12px;background:#fff}.pt-exp-stat small{display:block;color:#7d8da0;font-size:9px;font-weight:800;text-transform:uppercase}.pt-exp-stat strong{display:block;margin-top:7px;color:#203b55;font-size:18px}.pt-exp-stat span{display:block;margin-top:5px;color:#8290a0;font-size:10px}.pt-exp-stat--ok{border-color:#bfe6dd;background:#f6fffc}.pt-exp-stat--ok strong{color:#09806d}.pt-exp-stat--info{background:#f7fbff}.pt-exp-note,.pt-exp-footnote{display:flex;gap:8px;align-items:flex-start;padding:10px 12px;border:1px solid #d9e6ef;border-radius:10px;background:#f7fbff;color:#5f758b;font-size:10px;line-height:1.55}.pt-exp-note{margin-bottom:14px}.pt-exp-footnote{margin-top:12px}.pt-exp-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:14px 0 10px}.pt-exp-filters{display:flex;gap:7px;flex-wrap:wrap}.pt-exp-filter{min-height:32px;padding:6px 10px;border:1px solid var(--ex-line);border-radius:999px;background:#fff;color:#60758a;font-size:10px;font-weight:800;cursor:pointer}.pt-exp-filter span{margin-left:3px}.pt-exp-filter.is-active{border-color:#80cebf;background:#edfbf8;color:#077b69}.pt-exp-search-wrap{position:relative;min-width:280px}.pt-exp-search-wrap i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#8696a8}.pt-exp-search{width:100%;height:36px;padding:7px 11px 7px 32px;border:1px solid var(--ex-line);border-radius:10px;outline:0;font-size:11px}.pt-exp-table-wrap{overflow:auto;border:1px solid var(--ex-line);border-radius:12px}.pt-exp-table{width:100%;min-width:1060px;border-collapse:collapse}.pt-exp-table th,.pt-exp-table td{padding:11px 10px;border-bottom:1px solid #edf2f6;text-align:left;vertical-align:top}.pt-exp-table th{background:#f6f9fc;color:#75879b;font-size:9px;text-transform:uppercase;letter-spacing:.035em}.pt-exp-table td{color:#314a62;font-size:10px}.pt-exp-table td strong{display:block;color:#18354f;font-size:11px}.pt-exp-table td small{display:block;margin-top:3px;color:#8090a1;line-height:1.4}.pt-exp-category{display:inline-flex;padding:5px 8px;border-radius:8px;background:#edf7f5;color:#147868;font-weight:800}.pt-exp-money{white-space:nowrap}.pt-exp-status{display:inline-flex;padding:5px 8px;border-radius:999px;font-size:9px;font-weight:850;white-space:nowrap}.pt-exp-status--pending{background:#fff3d8;color:#96600b}.pt-exp-status--confirmed{background:#e7f8f3;color:#087966}.pt-exp-status--rejected{background:#fdebee;color:#b43441}.pt-exp-status--cancelled{background:#eef2f5;color:#687a8c}.pt-exp-proof{display:inline-flex;align-items:center;gap:5px;color:#087c6b;font-weight:800;text-decoration:none}.pt-exp-muted{color:#9aa6b4}.pt-exp-reason{color:#b23a45!important}.pt-exp-actions{display:flex;align-items:center;gap:5px}.pt-exp-actions form{margin:0}.pt-exp-icon{display:inline-flex;width:30px;height:30px;align-items:center;justify-content:center;border:1px solid var(--ex-line);border-radius:8px;background:#fff;color:#4e657b;cursor:pointer}.pt-exp-icon--ok{border-color:#bae3d9;color:#087966;background:#f2fcf9}.pt-exp-icon--danger{border-color:#f0cbd0;color:#b43441;background:#fff7f8}.pt-exp-empty{padding:32px;text-align:center;color:#7a8b9e}.pt-exp-empty i{display:block;font-size:25px;margin-bottom:8px}.pt-exp-empty strong{font-size:13px}.pt-exp-empty p{margin:5px auto 0;max-width:520px;font-size:10px;line-height:1.5}.pt-exp-drawer[hidden]{display:none!important}.pt-exp-drawer{position:fixed;inset:0;z-index:1090}.pt-exp-drawer__backdrop{position:absolute;inset:0;border:0;background:rgba(8,23,40,.48)}.pt-exp-drawer__panel{position:absolute;top:0;right:0;width:min(540px,96vw);height:100%;overflow:auto;padding:20px;background:#fff;box-shadow:-14px 0 40px rgba(8,27,47,.22)}.pt-exp-drawer__panel--compact{width:min(450px,96vw)}.pt-exp-drawer__head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding-bottom:14px;border-bottom:1px solid #e5ebf1}.pt-exp-drawer__head h3{margin:0;color:var(--ex-navy);font-size:18px}.pt-exp-drawer__head p{margin:5px 0 0;color:#78899c;font-size:10px;line-height:1.5}.pt-exp-close{width:36px;height:36px;border:1px solid var(--ex-line);border-radius:10px;background:#fff;color:#556b80;cursor:pointer}.pt-exp-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px;margin-top:16px}.pt-exp-form__full{grid-column:1/-1}.pt-exp-form label{display:block;margin-bottom:5px;color:#40566c;font-size:10px;font-weight:800}.pt-exp-form input,.pt-exp-form select,.pt-exp-form textarea{width:100%;min-height:40px;padding:9px 11px;border:1px solid #d5e0ea;border-radius:10px;background:#fff;color:#18344d;font-size:11px;outline:0}.pt-exp-form textarea{min-height:90px;resize:vertical}.pt-exp-form small{display:block;margin-top:4px;color:#8291a2;font-size:9px}.pt-exp-form__actions{display:flex;justify-content:flex-end;gap:8px}.pt-exp-form-note{padding:10px 12px;border-radius:10px;background:#fff7e8;color:#8b5a17;font-size:10px;line-height:1.5}
@media(max-width:900px){.pt-exp-summary{grid-template-columns:1fr 1fr}.pt-exp-toolbar{align-items:stretch;flex-direction:column}.pt-exp-search-wrap{min-width:0;width:100%}}@media(max-width:700px){.pt-exp{padding:12px}.pt-exp__head{flex-direction:column}.pt-exp-summary{grid-template-columns:1fr 1fr}.pt-exp-table{min-width:0}.pt-exp-table thead{display:none}.pt-exp-table,.pt-exp-table tbody,.pt-exp-table tr,.pt-exp-table td{display:block;width:100%}.pt-exp-table tr{padding:12px;border-bottom:1px solid #e7edf3}.pt-exp-table td{display:grid;grid-template-columns:100px 1fr;gap:9px;padding:5px 0;border:0}.pt-exp-table td::before{content:attr(data-label);color:#7d8da0;font-size:9px;font-weight:800;text-transform:uppercase}.pt-exp-actions{justify-content:flex-start}.pt-exp-drawer__panel{top:auto;bottom:0;width:100%;height:min(92vh,760px);border-radius:18px 18px 0 0}.pt-exp-form{grid-template-columns:1fr}.pt-exp-form__full{grid-column:auto}}@media(max-width:440px){.pt-exp-summary{grid-template-columns:1fr}.pt-exp-table td{grid-template-columns:88px 1fr}.pt-exp-btn{width:100%}}
</style>

<script>
(function () {
    const root = document.querySelector('[data-exp-root]');
    if (!root) return;

    const drawers = document.querySelectorAll('.pt-exp-drawer');
    const openDrawer = (id) => {
        const drawer = document.getElementById(id);
        if (!drawer) return;
        drawer.hidden = false;
        drawer.setAttribute('aria-hidden', 'false');
        document.documentElement.style.overflow = 'hidden';
    };
    const closeDrawer = (drawer) => {
        if (!drawer) return;
        drawer.hidden = true;
        drawer.setAttribute('aria-hidden', 'true');
        document.documentElement.style.overflow = '';
    };

    document.querySelectorAll('[data-exp-open]').forEach((button) => button.addEventListener('click', () => openDrawer(button.dataset.expOpen)));
    document.querySelectorAll('[data-exp-close]').forEach((button) => button.addEventListener('click', () => closeDrawer(button.closest('.pt-exp-drawer'))));
    drawers.forEach((drawer) => drawer.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeDrawer(drawer); }));

    let activeFilter = 'all';
    const search = root.querySelector('[data-exp-search]');
    const rows = Array.from(root.querySelectorAll('[data-exp-row]'));
    const applyFilter = () => {
        const q = (search?.value || '').trim().toLocaleLowerCase('vi');
        rows.forEach((row) => {
            const statusOk = activeFilter === 'all' || row.dataset.status === activeFilter;
            const searchOk = !q || (row.dataset.search || '').toLocaleLowerCase('vi').includes(q);
            row.hidden = !(statusOk && searchOk);
        });
    };
    root.querySelectorAll('[data-exp-filter]').forEach((button) => button.addEventListener('click', () => {
        activeFilter = button.dataset.expFilter || 'all';
        root.querySelectorAll('[data-exp-filter]').forEach((item) => item.classList.toggle('is-active', item === button));
        applyFilter();
    }));
    search?.addEventListener('input', applyFilter);

    const editDrawer = document.getElementById('expenseEditDrawer');
    const editForm = editDrawer?.querySelector('[data-exp-edit-form]');
    document.querySelectorAll('[data-exp-edit]').forEach((button) => button.addEventListener('click', () => {
        if (!editDrawer || !editForm) return;
        editForm.action = button.dataset.action || '';
        editForm.querySelector('[name="expense_date"]').value = button.dataset.date || '';
        editForm.querySelector('[name="category"]').value = button.dataset.category || 'other';
        editForm.querySelector('[name="description"]').value = button.dataset.description || '';
        editForm.querySelector('[name="amount"]').value = button.dataset.amount || '';
        editForm.querySelector('[name="payee_name"]').value = button.dataset.payee || '';
        editForm.querySelector('[name="note"]').value = button.dataset.note || '';
        openDrawer('expenseEditDrawer');
    }));

    const reasonDrawer = document.getElementById('expenseReasonDrawer');
    const reasonForm = reasonDrawer?.querySelector('[data-exp-reason-form]');
    const reasonTitle = reasonDrawer?.querySelector('[data-exp-reason-title]');
    const reasonSubtitle = reasonDrawer?.querySelector('[data-exp-reason-subtitle]');
    const prepareReason = (button, mode) => {
        if (!reasonDrawer || !reasonForm) return;
        reasonForm.action = button.dataset.action || '';
        reasonForm.querySelector('[name="reason"]').value = '';
        const code = button.dataset.code || 'khoản chi phí';
        if (reasonTitle) reasonTitle.textContent = mode === 'reject' ? 'Từ chối chi phí' : 'Hủy chi phí';
        if (reasonSubtitle) reasonSubtitle.textContent = (mode === 'reject' ? 'Từ chối ' : 'Hủy ') + code + ' và lưu lý do để đối soát.';
        openDrawer('expenseReasonDrawer');
    };
    document.querySelectorAll('[data-exp-reject]').forEach((button) => button.addEventListener('click', () => prepareReason(button, 'reject')));
    document.querySelectorAll('[data-exp-cancel]').forEach((button) => button.addEventListener('click', () => prepareReason(button, 'cancel')));
})();
</script>
