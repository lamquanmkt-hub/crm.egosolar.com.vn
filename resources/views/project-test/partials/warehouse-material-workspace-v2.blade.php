{{-- EGO_PROJECT_WAREHOUSE_EMBEDDED_V2 --}}
@php
    $warehouseUser = auth()->user();
    $warehouseCanOperate = (bool) ($can['warehouse'] ?? false);
    $warehouseIsAdmin = (bool) ($can['admin'] ?? false);
    $warehouseStandaloneMode = (bool) ($warehouseOnlyMode ?? false);
    $warehouseRequestId = (int) request('material_request', 0);
    $warehouseRequests = $project->materialRequests ?? collect();
    $materialRequest = $warehouseRequestId > 0
        ? $warehouseRequests->firstWhere('id', $warehouseRequestId)
        : $warehouseRequests->first();
@endphp

@if(!$materialRequest)
    <article class="pt-card pt-section ego-wh-embedded-empty">
        <div class="pt-empty">
            <i class="bi bi-box-seam"></i>
            <h3>Chưa có phiếu vật tư</h3>
            <p>Công trình chưa có nhu cầu vật tư để Kho xử lý.</p>
        </div>
    </article>
@else
    @php
        $warehouseLocked = $materialRequest->status === 'issued';
        $warehouseReserved = $materialRequest->warehouse_status === 'reserved';
        $warehouseApproved = in_array($materialRequest->status, ['approved', 'preparing', 'issued'], true);
        $warehouseCanCheck = in_array($materialRequest->status, ['warehouse_check', 'preparing'], true);
        $warehouseItems = $materialRequest->items ?? collect();
        $warehouseTotal = $warehouseItems->count();
        $warehouseMapped = $warehouseItems->filter(fn ($item) => ($item->allocations ?? collect())->isNotEmpty())->count();
        $warehouseShortage = $warehouseItems->filter(function ($item) {
            $allocation = ($item->allocations ?? collect())->first();
            return $allocation && $allocation->status === 'shortage';
        })->count();
        $warehouseSerialRequired = $warehouseItems->sum(function ($item) {
            $allocation = ($item->allocations ?? collect())->first();
            return $allocation && $allocation->is_serialized
                ? (int) ceil((float) ($allocation->allocated_quantity ?: 0))
                : 0;
        });
        $warehouseSerialSelected = $warehouseItems->sum(function ($item) {
            $allocation = ($item->allocations ?? collect())->first();
            return $allocation ? count((array) ($allocation->selected_serial_unit_ids ?: [])) : 0;
        });
        $warehouseReadyRows = $warehouseItems->filter(function ($item) {
            $allocation = ($item->allocations ?? collect())->first();
            return $allocation && in_array($allocation->status, ['ready', 'reserved', 'issued'], true);
        })->count();

        if ($warehouseLocked) {
            $warehouseState = 'issued';
            $warehouseStateLabel = 'Đã xuất kho';
        } elseif ($warehouseReserved) {
            $warehouseState = 'reserved';
            $warehouseStateLabel = 'Đã giữ hàng';
        } elseif (!$warehouseApproved) {
            $warehouseState = 'pending';
            $warehouseStateLabel = $materialRequest->status === 'revision' ? 'Bị trả lại bổ sung' : 'Chờ Admin duyệt';
        } elseif ($warehouseShortage > 0) {
            $warehouseState = 'shortage';
            $warehouseStateLabel = 'Thiếu hàng';
        } elseif ($warehouseTotal > 0 && $warehouseReadyRows === $warehouseTotal) {
            $warehouseState = 'ready';
            $warehouseStateLabel = 'Sẵn sàng giữ hàng';
        } elseif ($warehouseMapped > 0) {
            $warehouseState = 'matching';
            $warehouseStateLabel = 'Đang kiểm tra tồn';
        } else {
            $warehouseState = 'waiting_match';
            $warehouseStateLabel = 'Chờ kiểm tra tồn';
        }

        $warehousePercent = $warehouseTotal > 0 ? (int) round(($warehouseMapped / $warehouseTotal) * 100) : 0;

        // EGO_MATERIAL_LAYOUT_V4:
        // - Phê duyệt được xử lý ở đầu tab Chuẩn bị vật tư.
        // - Kiểm tra tồn chỉ xuất hiện trong không gian làm việc riêng của Kho.
        $allowedWarehouseSubtabs = ['need', 'issue', 'returns', 'history'];
        if ($warehouseStandaloneMode) {
            array_splice($allowedWarehouseSubtabs, 1, 0, ['matching']);
        }

        $defaultWarehouseSubtab = request('material_tab');
        if ($defaultWarehouseSubtab === 'approval') {
            $defaultWarehouseSubtab = 'need';
        }
        if ($defaultWarehouseSubtab === 'matching' && ! $warehouseStandaloneMode) {
            $defaultWarehouseSubtab = 'need';
        }
        if (!in_array($defaultWarehouseSubtab, $allowedWarehouseSubtabs, true)) {
            $defaultWarehouseSubtab = $warehouseLocked
                ? 'issue'
                : ($warehouseStandaloneMode && $warehouseCanCheck ? 'matching' : 'need');
        }
        $warehouseHistories = ($project->histories ?? collect())
            ->filter(function ($history) {
                $text = mb_strtolower((string) ($history->action ?: $history->description ?: $history->note ?: ''));
                foreach (['vật tư', 'kho', 'sku', 'serial', 'xuất', 'giữ hàng', 'bàn giao'] as $keyword) {
                    if (str_contains($text, $keyword)) return true;
                }
                return false;
            })
            ->take(50);
    @endphp

    <div class="ego-wh-embedded"
         data-warehouse-v2-root
         data-warehouse-embedded-root
         data-default-subtab="{{ $defaultWarehouseSubtab }}"
         data-products-url="{{ route('project-test.warehouse.products', $materialRequest) }}"
         data-warehouses-url="{{ route('project-test.warehouse.warehouses', $materialRequest) }}"
         data-serials-url="{{ route('project-test.warehouse.serials', $materialRequest) }}">

        @if($warehouseRequests->count() > 1)
            <section class="pt-card ego-wh-request-switcher">
                <div>
                    <small>Phiếu vật tư đang xử lý</small>
                    <strong>{{ $materialRequest->code }}</strong>
                </div>
                <select class="pt-select" data-warehouse-request-switcher>
                    @foreach($warehouseRequests as $requestOption)
                        <option value="{{ route('project-test.show', ['project' => $project->id, 'tab' => 'materials', 'material_tab' => $defaultWarehouseSubtab, 'material_request' => $requestOption->id]) }}" @selected($requestOption->id === $materialRequest->id)>
                            {{ $requestOption->code }} · {{ optional($requestOption->needed_at)->format('d/m/Y') ?: 'Chưa có ngày cần' }}
                        </option>
                    @endforeach
                </select>
            </section>
        @endif

        <section class="pt-card ego-wh-command">
            <div class="ego-wh-command__main">
                <div class="ego-wh-command__icon"><i class="bi bi-box-seam"></i></div>
                <div>
                    <div class="ego-wh-command__kicker">CHUẨN BỊ VẬT TƯ · {{ $materialRequest->code }}</div>
                    <h2>{{ $project->name }}</h2>
                    <p>Ngày cần: <strong>{{ optional($materialRequest->needed_at)->format('d/m/Y') ?: 'Chưa xác định' }}</strong> · Kỹ thuật phụ trách: <strong>{{ $project->leadTechnician?->name ?: 'Chưa phân công' }}</strong></p>
                </div>
            </div>
            <div class="ego-wh-command__stats">
                <div><small>Trạng thái</small><strong class="ego-wh-state ego-wh-state--{{ $warehouseState }}">{{ $warehouseStateLabel }}</strong></div>
                <div><small>Đã kiểm tra</small><strong>{{ $warehouseMapped }}/{{ $warehouseTotal }} dòng</strong></div>
                <div><small>Tiến độ</small><strong>{{ $warehousePercent }}%</strong></div>
            </div>
            <div class="ego-wh-command__progress"><span style="width:{{ $warehousePercent }}%"></span></div>
        </section>

        @include('project-test.partials.material-cost-panel')

        <nav class="ego-wh-subtabs" aria-label="Nghiệp vụ chuẩn bị vật tư">
            <button type="button" data-warehouse-subtab="need"><i class="bi bi-list-check"></i><span>Nhu cầu</span></button>
            @if($warehouseStandaloneMode)
                <button type="button" data-warehouse-subtab="matching" class="{{ !$warehouseCanCheck ? 'is-locked' : '' }}">
                    <i class="bi {{ !$warehouseApproved ? 'bi-lock' : 'bi-boxes' }}"></i>
                    <span>Kiểm tra tồn</span>
                    <b>{{ $warehouseMapped }}/{{ $warehouseTotal }}</b>
                </button>
            @endif
            <button type="button" data-warehouse-subtab="issue" class="{{ !$warehouseApproved ? 'is-locked' : '' }}">
                <i class="bi {{ !$warehouseApproved ? 'bi-lock' : 'bi-box-arrow-up-right' }}"></i>
                <span>Xuất kho</span>
            </button>
            <button type="button" data-warehouse-subtab="returns"><i class="bi bi-arrow-return-left"></i><span>Thu hồi</span></button>
            <button type="button" data-warehouse-subtab="history"><i class="bi bi-clock-history"></i><span>Lịch sử</span></button>
        </nav>

        <section class="ego-wh-subpanel" data-warehouse-subpanel="need">
            <article class="pt-card pt-section">
                <div class="pt-section__head">
                    <div><h2>Nhu cầu Kỹ thuật</h2><p>Kho chỉ đọc nội dung đã được Kỹ thuật lập; không thay đổi tên, số lượng hoặc thông số yêu cầu.</p></div>
                    <span class="pt-status">{{ $warehouseTotal }} dòng</span>
                </div>
                <div class="ego-wh-table-wrap">
                    <table class="pt-material-table ego-wh-need-table">
                        <thead><tr><th>Vật tư yêu cầu</th><th>SL cần</th><th>Thông số / ghi chú</th><th>Hàng thực tế đã ghép</th><th>Trạng thái</th></tr></thead>
                        <tbody>
                        @foreach($warehouseItems as $item)
                            @php($allocation = ($item->allocations ?? collect())->first())
                            <tr>
                                <td><strong>{{ $item->item_name }}</strong></td>
                                <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }} {{ $item->unit }}</td>
                                <td>{{ $item->note ?: '—' }}</td>
                                <td>
                                    @if($allocation)
                                        <strong>{{ $allocation->product?->name ?: 'Chưa xác định' }}</strong>
                                        <small>{{ $allocation->product?->sku ?: 'Không có SKU' }} · {{ $allocation->warehouse?->name ?: 'Chưa chọn kho' }}</small>
                                    @else
                                        <span class="pt-muted">Chưa kiểm tra sản phẩm thật</span>
                                    @endif
                                </td>
                                <td><span class="ego-wh-state ego-wh-state--{{ $allocation?->status ?: 'waiting_match' }}">{{ $allocation ? ($allocation->status === 'shortage' ? 'Thiếu hàng' : 'Đã kiểm tra') : 'Chờ ghép' }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        @if($warehouseStandaloneMode)
        <section class="ego-wh-subpanel" data-warehouse-subpanel="matching">
            @if(!$warehouseCanCheck)
                <article class="pt-card pt-section"><div class="ego-wh-lock-message"><i class="bi bi-lock"></i><div><strong>Kiểm tra tồn đang bị khóa</strong><p>Phiếu chưa ở bước Kho kiểm tra tồn.</p><button type="button" class="pt-btn pt-btn--light" data-open-warehouse-subtab="need">Xem nhu cầu vật tư</button></div></div></article>
            @elseif(!$warehouseLocked)
                <form method="POST" action="{{ route('project-test.warehouse.mapping.save', $materialRequest) }}" class="pt-card pt-wh-v2-match-form ego-wh-match-form" data-warehouse-mapping-form>
                    @csrf
                    <div class="ego-wh-sticky-actions">
                        <div><strong>Kiểm tra tồn kho theo đúng mã hàng</strong><small>{{ $warehouseMapped }}/{{ $warehouseTotal }} dòng đã ghép · {{ $warehouseShortage }} dòng thiếu</small></div>
                        @if(!$warehouseReserved)<button class="pt-btn pt-btn--brand"><i class="bi bi-save"></i> Lưu kiểm tra tồn</button>@else<span class="ego-wh-state ego-wh-state--reserved">Đang giữ hàng · bỏ giữ để sửa</span>@endif
                    </div>
                    <div class="pt-wh-v2-order-note"><span><b>1</b> Chọn sản phẩm</span><i class="bi bi-arrow-right"></i><span><b>2</b> Chọn kho có tồn</span><i class="bi bi-arrow-right"></i><span><b>3</b> Chọn số lượng & serial</span></div>
                    <div class="pt-wh-v2-items">
                        @foreach($warehouseItems as $item)
                            @php($allocation = ($item->allocations ?? collect())->first())
                            <article class="pt-wh-v2-item" data-allocation-row data-item-id="{{ $item->id }}" data-row-state="{{ $allocation?->status ?: 'waiting_match' }}" data-row-locked="{{ $warehouseReserved ? '1' : '0' }}" data-selected-warehouse-id="{{ $allocation?->warehouse_id }}" data-selected-serials='@json($allocation?->selected_serial_unit_ids ?: [])'>
                                <section class="pt-wh-v2-request-side">
                                    <div class="pt-wh-v2-side-label">Kỹ thuật yêu cầu</div>
                                    <h3>{{ $item->item_name }}</h3>
                                    <div class="pt-wh-v2-request-qty"><strong>{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }}</strong> {{ $item->unit }}</div>
                                    <p>{{ $item->note ?: 'Không có yêu cầu thông số bổ sung.' }}</p>
                                </section>
                                <section class="pt-wh-v2-stock-side">
                                    <div class="pt-wh-v2-side-label">Kho kiểm tra cấp hàng</div>
                                    <div class="pt-wh-v2-picker-step">
                                        <div class="pt-wh-v2-picker-step__head"><span>1</span><div><strong>Sản phẩm theo yêu cầu</strong><small>Kho chỉ kiểm tra tồn, không được đổi thương hiệu/model.</small></div></div>
                                        <input type="hidden" name="items[{{ $item->id }}][product_id]" value="{{ $item->product_id }}" data-product-id>
                                        <div class="pt-wh-v2-selected-product">
                                            <div><strong>{{ $allocation?->product?->name ?: $item->item_name }}</strong><small>{{ $allocation?->product?->sku ?: 'Mã hàng đã được Kỹ thuật chốt' }}</small></div>
                                        </div>
                                    </div>
                                    <div class="pt-wh-v2-picker-step {{ $allocation ? '' : 'is-disabled' }}" data-warehouse-step>
                                        <div class="pt-wh-v2-picker-step__head"><span>2</span><div><strong>Chọn kho đang có hàng</strong><small>Hệ thống xếp kho còn nhiều hàng lên trước.</small></div></div>
                                        <input type="hidden" name="items[{{ $item->id }}][warehouse_id]" value="{{ $allocation?->warehouse_id }}" data-row-warehouse-id>
                                        <select class="pt-select" data-row-warehouse {{ $warehouseReserved ? 'disabled' : '' }}>@if($allocation)<option value="{{ $allocation->warehouse_id }}" selected>{{ $allocation->warehouse?->name }} · Có thể cấp: {{ rtrim(rtrim(number_format((float) $allocation->available_snapshot, 3, '.', ''), '0'), '.') }}</option>@else<option value="">-- Chọn sản phẩm trước --</option>@endif</select>
                                        <div class="pt-wh-v2-warehouse-stock {{ $allocation ? '' : 'is-empty' }}" data-warehouse-stock>@if($allocation)<span>Kho đã chọn</span><strong>{{ $allocation->warehouse?->name }}</strong><small>Tồn khả dụng lúc ghép: {{ rtrim(rtrim(number_format((float) $allocation->available_snapshot, 3, '.', ''), '0'), '.') }}</small>@else<span>Chưa có kho để hiển thị</span>@endif</div>
                                    </div>
                                    <div class="pt-wh-v2-picker-step {{ $allocation ? '' : 'is-disabled' }}" data-quantity-step>
                                        <div class="pt-wh-v2-picker-step__head"><span>3</span><div><strong>Số lượng và serial</strong><small>Không vượt số lượng Kỹ thuật yêu cầu hoặc tồn khả dụng.</small></div></div>
                                        <div class="pt-wh-v2-allocation-fields">
                                            <label><span>Số lượng cấp</span><input class="pt-input" type="number" step="0.001" min="0" max="{{ $item->quantity }}" name="items[{{ $item->id }}][quantity]" value="{{ $allocation?->allocated_quantity ?: $item->quantity }}" data-allocated-qty required {{ $warehouseReserved ? 'readonly' : '' }}></label>
                                            <label><span>Ghi chú Kho</span><input class="pt-input" name="items[{{ $item->id }}][note]" value="{{ $allocation?->note }}" placeholder="Thiếu hàng, cần điều chuyển, chờ nhập..." {{ $warehouseReserved ? 'readonly' : '' }}></label>
                                        </div>
                                        <div class="pt-wh-v2-serial-box" data-serial-box @if(!$allocation?->is_serialized) hidden @endif><div class="pt-wh-v2-serial-head"><strong>Chọn serial thật trong kho</strong><span data-serial-count>{{ count((array) ($allocation?->selected_serial_unit_ids ?: [])) }}/{{ (int) ceil((float) ($allocation?->allocated_quantity ?: 0)) }}</span></div><div class="pt-wh-v2-serial-list" data-serial-list>@if($allocation?->selected_serial_codes)<div class="pt-help">Đã chọn: {!! nl2br(e($allocation->selected_serial_codes)) !!}</div>@else<div class="pt-help">Chọn sản phẩm và kho để tải serial khả dụng.</div>@endif</div></div>
                                    </div>
                                    <div class="pt-wh-v2-row-result" data-row-result>@if($allocation)<span class="pt-wh-v2-state pt-wh-v2-state--{{ $allocation->status }}">{{ $allocation->status === 'shortage' ? 'Thiếu hàng' : ($allocation->status === 'reserved' ? 'Đã giữ' : 'Đã kiểm tra SKU & kho') }}</span>@else<span class="pt-wh-v2-state pt-wh-v2-state--waiting_match">Chưa kiểm tra</span>@endif</div>
                                </section>
                            </article>
                        @endforeach
                    </div>
                </form>
                <form method="POST" action="{{ route('project-test.warehouse.manager.submit', $materialRequest) }}" class="pt-card pt-section" data-confirm="Gửi kết quả kiểm tra tồn cho Quản lý phê duyệt?">
                    @csrf
                    <button class="pt-btn pt-btn--brand ego-wh-full"><i class="bi bi-send-check"></i> Gửi Quản lý phê duyệt</button>
                </form>
            @else
                <article class="pt-card pt-section"><div class="pt-alert pt-alert--success"><i class="bi bi-check-circle"></i> Phiếu đã xuất kho và khóa chỉnh sửa kiểm tra tồn.</div></article>
            @endif
        </section>

        @endif

        <section class="ego-wh-subpanel" data-warehouse-subpanel="issue">
            <div class="ego-wh-issue-grid">
                <article class="pt-card pt-section">
                    <div class="pt-section__head"><div><h2>Kiểm tra & xuất kho</h2><p>Chỉ xuất khi mọi dòng đã có SKU, kho, số lượng và serial hợp lệ.</p></div></div>
                    <div class="ego-wh-summary-grid" data-live-summary>
                        <div><small>Dòng yêu cầu</small><strong data-summary-total>{{ $warehouseTotal }}</strong></div>
                        <div><small>Đã kiểm tra tồn</small><strong data-summary-mapped>{{ $warehouseMapped }}/{{ $warehouseTotal }}</strong></div>
                        <div><small>Dòng thiếu hàng</small><strong data-summary-shortage>{{ $warehouseShortage }}</strong></div>
                        <div><small>Serial đã chọn</small><strong data-summary-serial>{{ $warehouseSerialSelected }}/{{ $warehouseSerialRequired }}</strong></div>
                    </div>
                    <div class="pt-wh-v2-ready-banner {{ in_array($warehouseState, ['ready', 'reserved', 'issued'], true) ? 'is-ready' : '' }}" data-ready-banner><i class="bi {{ in_array($warehouseState, ['ready', 'reserved', 'issued'], true) ? 'bi-check-circle' : 'bi-exclamation-circle' }}"></i><div><strong>{{ in_array($warehouseState, ['ready', 'reserved', 'issued'], true) ? 'Hàng đã sẵn sàng' : 'Chưa đủ điều kiện' }}</strong><small>{{ $warehouseStateLabel }}</small></div></div>
                </article>
                <article class="pt-card pt-section ego-wh-issue-actions">
                    @if(!$warehouseApproved)
                        <div class="ego-wh-lock-message"><i class="bi bi-lock"></i><div><strong>Xuất kho đang bị khóa</strong><p>Cần Admin duyệt phiếu trước.</p></div></div>
                    @elseif(!$warehouseLocked)
                        @if(!$warehouseReserved)
                            <form method="POST" action="{{ route('project-test.warehouse.reserve', $materialRequest) }}" data-confirm="Giữ số hàng đã ghép cho công trình này?">@csrf<button class="pt-btn pt-btn--dark ego-wh-full" @disabled($warehouseState !== 'ready')><i class="bi bi-bookmark-check"></i> Giữ hàng</button></form>
                        @else
                            <form method="POST" action="{{ route('project-test.warehouse.release', $materialRequest) }}" data-confirm="Bỏ giữ toàn bộ hàng của phiếu này?">@csrf<button class="pt-btn pt-btn--light ego-wh-full"><i class="bi bi-bookmark-x"></i> Bỏ giữ hàng</button></form>
                        @endif
                        <form method="POST" action="{{ route('project-test.warehouse.issue', $materialRequest) }}" data-confirm="Xác nhận xuất kho và bàn giao cho Kỹ thuật? Tồn kho thật sẽ được trừ." class="ego-wh-issue-form">@csrf
                            <label class="pt-label">Người nhận hàng</label>
                            <select class="pt-select" name="receiver_id" required @disabled(!$warehouseReserved)><option value="">-- Chọn Kỹ thuật nhận --</option>@foreach($technicians as $receiver)<option value="{{ $receiver->id }}" @selected($materialRequest->receiver_id == $receiver->id || (!$materialRequest->receiver_id && $project->lead_technician_id == $receiver->id))>{{ $receiver->name }}</option>@endforeach</select>
                            <label class="pt-label" style="margin-top:10px">Ghi chú bàn giao</label><textarea class="pt-textarea" name="issue_note" placeholder="Tình trạng hàng, số kiện, người vận chuyển..." @disabled(!$warehouseReserved)>{{ $materialRequest->issue_note }}</textarea>
                            <button class="pt-btn pt-btn--brand ego-wh-full" style="margin-top:10px" @disabled(!$warehouseReserved)><i class="bi bi-box-arrow-up-right"></i> Xác nhận xuất kho & bàn giao</button>
                        </form>
                    @else
                        <div class="pt-alert pt-alert--success"><strong>Đã xuất kho:</strong> {{ optional($materialRequest->handed_over_at ?: $materialRequest->issued_at)->format('d/m/Y H:i') ?: '—' }}<br>Người nhận: {{ $materialRequest->receiver?->name ?: '—' }}<br>Người xuất: {{ $materialRequest->issuer?->name ?: '—' }}</div>
                    @endif
                </article>
            </div>
        </section>

        <section class="ego-wh-subpanel" data-warehouse-subpanel="returns">
            <article class="pt-card pt-section"><div class="pt-section__head"><div><h2>Thu hồi vật tư</h2><p>Quản lý vật tư dư, thiết bị lỗi và hàng hoàn về từ công trình.</p></div></div><div class="pt-empty"><i class="bi bi-arrow-return-left"></i><h3>Chưa có phiếu thu hồi</h3><p>Khi công trình phát sinh vật tư dư hoặc thiết bị lỗi, phiếu thu hồi sẽ hiển thị tại đây. Phiên bản này không tạo dữ liệu thu hồi giả.</p></div></article>
        </section>

        <section class="ego-wh-subpanel" data-warehouse-subpanel="history">
            <article class="pt-card pt-section">
                <div class="pt-section__head"><div><h2>Lịch sử Kho & vật tư</h2><p>Nhật ký chỉ đọc, giữ lại toàn bộ thao tác liên quan đến vật tư công trình.</p></div></div>
                @forelse($warehouseHistories as $history)
                    <div class="pt-history"><span class="pt-history__dot"><i class="bi bi-box-seam"></i></span><div><strong>{{ $history->action ?: $history->description ?: 'Cập nhật vật tư' }}</strong><p>{{ $history->user?->name ?: 'Hệ thống' }} · {{ optional($history->created_at)->format('d/m/Y H:i') }}</p></div></div>
                @empty
                    <div class="pt-alert pt-alert--info">Chưa có lịch sử Kho hoặc vật tư được ghi nhận.</div>
                @endforelse
            </article>
        </section>
    </div>
@endif
