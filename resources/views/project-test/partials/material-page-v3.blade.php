{{-- EGO MATERIAL PAGE V3 — chỉ thay nội dung tab Chuẩn bị vật tư, không sửa menu/layout toàn hệ thống. --}}
@php
    $selectedId = (int) request('material_request', 0);
    $materialRequest = $selectedId > 0
        ? $project->materialRequests->firstWhere('id', $selectedId)
        : $project->materialRequests->sortByDesc('id')->first();
@endphp

@if(!$materialRequest)
    <article class="pt-card emp3-empty">
        <i class="bi bi-box-seam"></i>
        <h3>Chưa có phiếu vật tư</h3>
        <p>Kỹ thuật chưa lập nhu cầu vật tư cho công trình.</p>
    </article>
@else
@php
    $items = $materialRequest->items ?? collect();
    $totalRows = $items->count();
    $checkedRows = $items->filter(fn($item) => ($item->allocations ?? collect())->isNotEmpty())->count();
    $readyRows = 0; $shortageRows = 0; $transferRows = 0; $totalCost = 0;
    foreach ($items as $it) {
        $a = ($it->allocations ?? collect())->first();
        if (!$a) continue;
        if (in_array($a->status, ['ready','reserved','issued'], true)) $readyRows++;
        elseif ($a->status === 'shortage') $shortageRows++;
        elseif ($a->status === 'transfer') $transferRows++;
        $totalCost += (float)($a->unit_cost ?? 0) * (float)($a->allocated_quantity ?? 0);
    }
    $percent = $totalRows > 0 ? (int) round($checkedRows * 100 / $totalRows) : 0;
    $isWarehouse = (bool)($can['warehouse'] ?? false);
    $isManager = auth()->user()?->hasAnyRole(['admin','management','manager']) || (bool)($can['admin'] ?? false);
    $canWarehouseEdit = $isWarehouse && in_array($materialRequest->status, ['warehouse_check','preparing'], true) && $materialRequest->warehouse_status !== 'reserved';
    $canManagerReview = $isManager && $materialRequest->status === 'pending_manager';
    $canViewCost = auth()->user()?->hasAnyRole(['admin','management','manager','warehouse','kho']) || auth()->user()?->can('finance.project_profit.view');
    $statusLabel = match($materialRequest->status) {
        'warehouse_check' => 'Kho đang kiểm tra tồn',
        'pending_manager' => 'Chờ Quản lý phê duyệt',
        'approved' => 'Đã duyệt · Chờ xuất kho',
        'preparing' => 'Kho đang chuẩn bị xuất',
        'revision' => 'Cần Sales/Kỹ thuật điều chỉnh',
        'issued' => 'Đã xuất kho',
        default => 'Chờ Kho kiểm tra',
    };
@endphp

<div class="emp3-layout" data-emp3-root>
    <div class="emp3-main">
        <article class="pt-card emp3-summary">
            <div class="emp3-identity">
                <span class="emp3-icon"><i class="bi bi-box-seam"></i></span>
                <div>
                    <small>CHUẨN BỊ VẬT TƯ · {{ $materialRequest->code }}</small>
                    <h2>{{ $project->name }}</h2>
                    <p>Ngày cần: {{ optional($materialRequest->needed_at)->format('d/m/Y') ?: '—' }} · Người lập: {{ $materialRequest->requester?->name ?: 'Kỹ thuật' }}</p>
                    <span class="emp3-badge emp3-badge--pending">{{ $statusLabel }}</span>
                </div>
            </div>
            <div class="emp3-kpis">
                <div><small>Tổng nhu cầu</small><strong>{{ $totalRows }} dòng</strong></div>
                <div><small>Đã kiểm tra</small><strong>{{ $checkedRows }}/{{ $totalRows }} dòng</strong></div>
                <div><small>Tiến độ</small><strong>{{ $percent }}%</strong><span><i style="width:{{ $percent }}%"></i></span></div>
            </div>
        </article>

        <article class="pt-card emp3-table-card">
            <nav class="emp3-subnav">
                <button type="button" class="is-active"><i class="bi bi-list-check"></i> Vật tư đề xuất ({{ $totalRows }})</button>
                <button type="button" class="{{ in_array($materialRequest->status,['approved','preparing','issued'],true) ? '' : 'is-locked' }}"><i class="bi bi-box-arrow-up-right"></i> Xuất kho</button>
                <button type="button"><i class="bi bi-arrow-return-left"></i> Thu hồi</button>
                <a href="{{ route('project-test.show',['project'=>$project->id,'tab'=>'history']) }}"><i class="bi bi-clock-history"></i> Lịch sử</a>
            </nav>
            <div class="emp3-toolbar">
                <label><i class="bi bi-search"></i><input type="search" placeholder="Tìm vật tư, mã sản phẩm, ghi chú..." data-emp3-search></label>
                <select class="pt-select" data-emp3-status><option value="">Tất cả trạng thái</option><option value="ready">Đủ hàng</option><option value="shortage">Thiếu hàng</option><option value="transfer">Cần điều chuyển</option></select>
                <span></span>
                <button type="button" class="pt-btn pt-btn--soft"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button>
            </div>

            @if($canWarehouseEdit)
            <form method="POST" action="{{ route('project-test.warehouse.mapping.save',$materialRequest) }}" data-confirm="Lưu kết quả kiểm tra tồn kho?">
            @csrf
            @endif
            <div class="emp3-table-wrap">
                <table class="emp3-table">
                    <thead><tr><th>STT</th><th>Vật tư kỹ thuật (nhu cầu)</th><th>SL yêu cầu</th><th>Sản phẩm theo yêu cầu</th>@if($canViewCost)<th>Giá vốn</th>@endif<th>Tồn khả dụng</th><th>Kho / vị trí</th><th>SL chuẩn bị</th><th>Tình trạng</th><th>Ghi chú</th></tr></thead>
                    <tbody>
                    @foreach($items as $index => $item)
                        @php
                            $a = ($item->allocations ?? collect())->first();
                            $product = $a?->product;
                            $rowStatus = $a?->status ?: '';
                            $productId = (int)($item->product_id ?: $a?->product_id ?: 0);
                            $available = (float)($a?->available_snapshot ?: 0);
                        @endphp
                        <tr data-emp3-row data-status="{{ $rowStatus }}" data-search="{{ mb_strtolower(($item->item_name ?? '').' '.($product?->name ?? '').' '.($product?->sku ?? '').' '.($a?->note ?? '')) }}">
                            <td>{{ $index+1 }}</td>
                            <td><strong>{{ $item->item_name }}</strong>@if($item->note)<small>{{ $item->note }}</small>@endif</td>
                            <td><strong>{{ rtrim(rtrim(number_format((float)$item->quantity,3,'.',''),'0'),'.') }}</strong> {{ $item->unit }}</td>
                            <td>
                                <strong>{{ $product?->name ?: 'Chưa xác nhận mã hàng' }}</strong>
                                <small>{{ $product?->sku ? 'SKU: '.$product->sku : 'Sales/Kỹ thuật phải xác nhận đúng sản phẩm' }}</small>
                                @if($canWarehouseEdit)<input type="hidden" name="items[{{ $item->id }}][product_id]" value="{{ $productId }}">@endif
                            </td>
                            @if($canViewCost)<td><strong data-emp3-cost>{{ ($a?->unit_cost ?? 0) > 0 ? number_format((float)$a->unit_cost,0,',','.') : '—' }}</strong></td>@endif
                            <td><strong class="{{ $available > 0 ? 'emp3-good' : 'emp3-bad' }}" data-emp3-available>{{ rtrim(rtrim(number_format($available,3,'.',''),'0'),'.') }} {{ $product?->unit ?: $item->unit }}</strong></td>
                            <td>
                                @if($canWarehouseEdit && $productId > 0)
                                    <select class="pt-select" name="items[{{ $item->id }}][warehouse_id]" data-emp3-warehouse data-product-id="{{ $productId }}" data-url="{{ route('project-test.warehouse.warehouses',$materialRequest) }}">
                                        @if($a?->warehouse_id)<option value="{{ $a->warehouse_id }}" selected>{{ $a->warehouse?->name ?: 'Kho #'.$a->warehouse_id }}</option>@else<option value="">-- Chọn kho --</option>@endif
                                    </select>
                                @else
                                    <strong>{{ $a?->warehouse?->name ?: '—' }}</strong><small>{{ $a?->warehouse?->location ?: '' }}</small>
                                @endif
                            </td>
                            <td>@if($canWarehouseEdit && $productId > 0)<input class="pt-input emp3-qty" type="number" min="0" step="0.001" max="{{ $item->quantity }}" name="items[{{ $item->id }}][quantity]" value="{{ $a?->allocated_quantity ?? $item->quantity }}">@else<strong>{{ rtrim(rtrim(number_format((float)($a?->allocated_quantity ?: 0),3,'.',''),'0'),'.') }} {{ $item->unit }}</strong>@endif</td>
                            <td>
                                @if($canWarehouseEdit && $productId > 0)
                                    <select class="pt-select" name="items[{{ $item->id }}][check_status]" data-emp3-row-status><option value="ready" @selected(($rowStatus ?: 'ready')==='ready')>Đủ hàng</option><option value="shortage" @selected($rowStatus==='shortage')>Thiếu hàng</option><option value="transfer" @selected($rowStatus==='transfer')>Cần điều chuyển</option><option value="waiting_purchase" @selected($rowStatus==='waiting_purchase')>Chờ mua bổ sung</option></select>
                                @else
                                    <span class="emp3-badge emp3-badge--{{ $rowStatus ?: 'neutral' }}">{{ match($rowStatus){'ready'=>'Đủ hàng','shortage'=>'Thiếu hàng','transfer'=>'Cần điều chuyển','waiting_purchase'=>'Chờ mua bổ sung','reserved'=>'Đã giữ hàng','issued'=>'Đã xuất',default=>'Chưa kiểm tra'} }}</span>
                                @endif
                            </td>
                            <td>@if($canWarehouseEdit && $productId > 0)<input class="pt-input" name="items[{{ $item->id }}][note]" value="{{ $a?->note }}" placeholder="Thiếu hàng, điều chuyển...">@else{{ $a?->note ?: '—' }}@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($canWarehouseEdit)
                <div class="emp3-actions"><div><strong>Kho chỉ kiểm tra tồn và cập nhật trạng thái</strong><small>Không được thay đổi thương hiệu, model hoặc mã sản phẩm.</small></div><button class="pt-btn pt-btn--soft" type="submit"><i class="bi bi-save"></i> Lưu kiểm tra</button></div>
            </form>
                <form method="POST" action="{{ route('project-test.warehouse.manager.submit',$materialRequest) }}" class="emp3-send" data-confirm="Gửi kết quả kiểm tra cho Quản lý phê duyệt?">@csrf<button class="pt-btn pt-btn--brand" type="submit"><i class="bi bi-send-check"></i> Gửi Quản lý phê duyệt</button></form>
            @endif
        </article>
    </div>

    <aside class="emp3-side">
        <article class="pt-card emp3-approval">
            <header><strong><i class="bi bi-shield-check"></i> DUYỆT VẬT TƯ</strong><span>{{ $statusLabel }}</span></header>
            <div class="emp3-approval-kpis"><div><small>Phiếu vật tư</small><strong>{{ $materialRequest->code }}</strong></div><div><small>Tổng nhu cầu</small><strong>{{ $totalRows }} dòng</strong></div><div><small>Đã kiểm tra</small><strong>{{ $checkedRows }} dòng ({{ $percent }}%)</strong></div></div>
            @if($canManagerReview)
            <form method="POST" action="{{ route('project-test.materials.review',[$project,$materialRequest]) }}" data-emp3-approval>@csrf
                <label class="emp3-radio"><input type="radio" name="decision" value="approve" checked><span><strong>Phê duyệt và chuyển xuất kho</strong><small>Xác nhận sản phẩm, tồn và giá vốn phù hợp</small></span></label>
                <label class="emp3-radio"><input type="radio" name="decision" value="return_warehouse"><span><strong>Yêu cầu Kho kiểm tra lại</strong><small>Sai tồn, số lượng, vị trí hoặc giá vốn</small></span></label>
                <label class="emp3-radio"><input type="radio" name="decision" value="return_technical"><span><strong>Yêu cầu Sales/Kỹ thuật điều chỉnh</strong><small>Sai thương hiệu, model hoặc vật tư hợp đồng</small></span></label>
                <label class="pt-label">Ghi chú</label><textarea class="pt-textarea" name="review_note" data-emp3-note placeholder="Nhập lý do nếu trả lại..."></textarea>
                <div class="emp3-approval-buttons"><button class="pt-btn pt-btn--danger" type="submit" data-emp3-return hidden><i class="bi bi-arrow-return-left"></i> Trả lại</button><button class="pt-btn pt-btn--brand" type="submit" data-emp3-approve><i class="bi bi-shield-check"></i> Phê duyệt & chuyển xuất kho</button></div>
            </form>
            @else
                <div class="emp3-info"><i class="bi bi-info-circle"></i><span>{{ $materialRequest->status === 'pending_manager' ? 'Tài khoản Quản lý/Admin thực hiện phê duyệt tại đây.' : 'Phiếu chưa đến bước Quản lý phê duyệt.' }}</span></div>
            @endif
        </article>
        <article class="pt-card emp3-side-card"><h3><i class="bi bi-clipboard-check"></i> VIỆC TIẾP THEO</h3><p><strong>{{ $materialRequest->status === 'pending_manager' ? 'Quản lý – Phê duyệt' : ($canWarehouseEdit ? 'Kho – Kiểm tra tồn' : $statusLabel) }}</strong></p><small>Đủ hàng: {{ $readyRows }} · Thiếu: {{ $shortageRows }} · Điều chuyển: {{ $transferRows }}</small></article>
        @if($canViewCost)<article class="pt-card emp3-side-card"><h3><i class="bi bi-cash-stack"></i> GIÁ VỐN TẠM TÍNH</h3><p class="emp3-total">{{ number_format($totalCost,0,',','.') }} đ</p><small>Chỉ hiển thị cho Kho, Quản lý và Admin.</small></article>@endif
    </aside>
</div>
@endif
