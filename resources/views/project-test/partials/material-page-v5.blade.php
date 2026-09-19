{{--
    EGO MATERIAL WORKSPACE V5
    Chỉ áp dụng trong tab Chuẩn bị vật tư của trang chi tiết Công trình.
    Không thay menu trái, topbar, layout chung hoặc các trang khác.
--}}
@php
    $selectedMaterialRequestId = (int) request('material_request', 0);
    $materialRequest = $selectedMaterialRequestId > 0
        ? $project->materialRequests->firstWhere('id', $selectedMaterialRequestId)
        : $project->materialRequests->sortByDesc('id')->first();
@endphp

@if(! $materialRequest)
    <article class="pt-card emw5-empty">
        <span><i class="bi bi-box-seam"></i></span>
        <h3>Chưa có phiếu vật tư</h3>
        <p>Kỹ thuật chưa lập danh sách vật tư cho công trình này.</p>
    </article>
@else
@php
    $items = collect($materialRequest->items ?? []);
    $totalRows = $items->count();

    $productIds = $items
        ->flatMap(function ($item) {
            $allocationProductIds = collect($item->allocations ?? [])->pluck('product_id');
            return $allocationProductIds->push($item->product_id);
        })
        ->filter(fn ($id) => (int) $id > 0)
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->values();

    $catalogProducts = $productIds->isEmpty()
        ? collect()
        : \Illuminate\Support\Facades\DB::table('crm_product_catalog')
            ->whereIn('id', $productIds->all())
            ->get(['id', 'name', 'sku', 'unit', 'is_serialized'])
            ->keyBy('id');

    $checkedRows = 0;
    $readyRows = 0;
    $shortageRows = 0;
    $transferRows = 0;
    $waitingPurchaseRows = 0;
    $unconfirmedRows = 0;
    $totalCost = 0.0;

    foreach ($items as $item) {
        $allocation = collect($item->allocations ?? [])->first();
        $productId = (int) ($item->product_id ?: ($allocation?->product_id ?? 0));
        if ($productId <= 0) {
            $unconfirmedRows++;
        }
        if (! $allocation) {
            continue;
        }
        $checkedRows++;
        $rowStatus = (string) ($allocation->status ?? '');
        if (in_array($rowStatus, ['ready', 'reserved', 'issued'], true)) {
            $readyRows++;
        } elseif ($rowStatus === 'transfer') {
            $transferRows++;
        } elseif ($rowStatus === 'waiting_purchase') {
            $waitingPurchaseRows++;
        } elseif ($rowStatus === 'shortage') {
            $shortageRows++;
        }
        $totalCost += (float) ($allocation->unit_cost ?? 0) * (float) ($allocation->allocated_quantity ?? 0);
    }

    $percent = $totalRows > 0 ? (int) round(($checkedRows / $totalRows) * 100) : 0;
    $isWarehouse = (bool) ($can['warehouse'] ?? false);
    $isAdmin = (bool) ($can['admin'] ?? false);
    $isManager = $isAdmin || (bool) auth()->user()?->hasAnyRole(['admin', 'management', 'manager']);
    $canWarehouseEdit = $isWarehouse
        && in_array($materialRequest->status, ['warehouse_check', 'preparing'], true)
        && $materialRequest->warehouse_status !== 'reserved';
    $canManagerReview = $isManager && $materialRequest->status === 'pending_manager';
    $canViewCost = (bool) auth()->user()?->hasAnyRole(['admin', 'management', 'manager', 'warehouse', 'kho'])
        || (bool) auth()->user()?->can('finance.project_profit.view');

    $statusLabel = match ((string) $materialRequest->status) {
        'warehouse_check' => 'Kho đang kiểm tra tồn',
        'pending_manager' => 'Chờ Quản lý phê duyệt',
        'approved' => 'Đã duyệt · Chờ xuất kho',
        'preparing' => $materialRequest->warehouse_status === 'reserved' ? 'Kho đã giữ hàng' : 'Kho đang chuẩn bị xuất',
        'revision' => 'Chờ Sales/Kỹ thuật điều chỉnh',
        'issued' => 'Đã xuất kho',
        default => 'Chờ Kho kiểm tra',
    };

    $statusTone = match ((string) $materialRequest->status) {
        'approved', 'issued' => 'success',
        'revision' => 'danger',
        'pending_manager' => 'warning',
        default => 'info',
    };

    $warehouseEndpoint = route('project-test.warehouse.warehouses', $materialRequest);
@endphp

<div class="emw5" data-emw5-root data-emw5-default-material="1">
    <section class="pt-card emw5-summary">
        <div class="emw5-summary__identity">
            <span class="emw5-summary__icon"><i class="bi bi-box-seam"></i></span>
            <div>
                <small>CHUẨN BỊ VẬT TƯ · {{ $materialRequest->code }}</small>
                <h2>{{ $project->name }}</h2>
                <p>
                    Ngày cần: {{ optional($materialRequest->needed_at)->format('d/m/Y') ?: '—' }}
                    · Người lập: {{ $materialRequest->requester?->name ?: 'Kỹ thuật' }}
                </p>
                <span class="emw5-status emw5-status--{{ $statusTone }}">{{ $statusLabel }}</span>
            </div>
        </div>
        <div class="emw5-summary__kpis">
            <div><small>Tổng nhu cầu</small><strong>{{ $totalRows }} dòng</strong></div>
            <div><small>Đã kiểm tra</small><strong>{{ $checkedRows }}/{{ $totalRows }} dòng</strong></div>
            <div>
                <small>Tiến độ</small><strong>{{ $percent }}%</strong>
                <span class="emw5-progress"><i style="width: {{ $percent }}%"></i></span>
            </div>
        </div>
    </section>

    @if($unconfirmedRows > 0)
        <div class="emw5-alert emw5-alert--warning">
            <i class="bi bi-exclamation-triangle"></i>
            <div>
                <strong>Còn {{ $unconfirmedRows }} dòng chưa gắn đúng mã sản phẩm.</strong>
                <span>Sales/Kỹ thuật phải xác nhận thương hiệu, model và SKU trước khi Kho kiểm tra tồn.</span>
            </div>
        </div>
    @endif

    <div class="emw5-grid">
        <main class="emw5-main">
            <section class="pt-card emw5-workspace">
                <header class="emw5-workspace__head">
                    <div>
                        <h3><i class="bi bi-shield-check"></i> KIỂM TRA TỒN KHO</h3>
                        <p>Kho xác nhận tồn, vị trí và số lượng chuẩn bị đúng theo mã hàng Sales/Kỹ thuật đã yêu cầu.</p>
                    </div>
                    @if($canWarehouseEdit)
                        <button type="button" class="emw5-btn emw5-btn--outline" data-emw5-auto>
                            <i class="bi bi-diagram-3"></i> Đề xuất kho tự động
                        </button>
                    @endif
                </header>

                <nav class="emw5-tabs" aria-label="Nghiệp vụ vật tư">
                    <button type="button" class="is-active"><i class="bi bi-list-check"></i> Vật tư đề xuất ({{ $totalRows }})</button>
                    <button type="button" class="{{ in_array($materialRequest->status, ['approved', 'preparing', 'issued'], true) ? '' : 'is-locked' }}"><i class="bi bi-box-arrow-up-right"></i> Xuất kho</button>
                    <button type="button"><i class="bi bi-arrow-return-left"></i> Thu hồi</button>
                    <a href="{{ route('project-test.show', ['project' => $project->id, 'tab' => 'history']) }}"><i class="bi bi-clock-history"></i> Lịch sử</a>
                </nav>

                <div class="emw5-toolbar">
                    <label class="emw5-search">
                        <i class="bi bi-search"></i>
                        <input type="search" placeholder="Tìm vật tư, mã, SKU, ghi chú..." data-emw5-search>
                    </label>
                    <select data-emw5-warehouse-filter>
                        <option value="">Tất cả kho</option>
                    </select>
                    <select data-emw5-status-filter>
                        <option value="">Tất cả trạng thái</option>
                        <option value="ready">Đủ hàng</option>
                        <option value="shortage">Thiếu hàng</option>
                        <option value="transfer">Cần điều chuyển</option>
                        <option value="waiting_purchase">Chờ mua bổ sung</option>
                        <option value="unchecked">Chưa kiểm tra</option>
                    </select>
                    <button type="button" class="emw5-btn emw5-btn--outline" data-emw5-reset-filter><i class="bi bi-funnel"></i> Bỏ lọc</button>
                </div>

                @if($canWarehouseEdit)
                    <form method="POST" action="{{ route('project-test.warehouse.mapping.save', $materialRequest) }}" data-emw5-form data-confirm="Lưu kết quả kiểm tra tồn kho?">
                        @csrf
                @endif

                <div class="emw5-table-wrap">
                    <table class="emw5-table">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>Vật tư kỹ thuật (nhu cầu)</th>
                                <th>SL yêu cầu</th>
                                <th>Sản phẩm theo yêu cầu</th>
                                @if($canViewCost)<th>Giá vốn (VND)</th>@endif
                                <th>Kho / vị trí</th>
                                <th>Tồn khả dụng</th>
                                <th>SL chuẩn bị</th>
                                <th>Tình trạng</th>
                                <th>Ghi chú</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($items as $index => $item)
                            @php
                                $allocation = collect($item->allocations ?? [])->first();
                                $productId = (int) ($item->product_id ?: ($allocation?->product_id ?? 0));
                                $product = $catalogProducts->get($productId) ?: $allocation?->product;
                                $rowStatus = (string) ($allocation?->status ?: 'unchecked');
                                $available = (float) ($allocation?->available_snapshot ?? 0);
                                $allocatedQuantity = (float) ($allocation?->allocated_quantity ?? 0);
                                $unitCost = $allocation?->unit_cost !== null ? (float) $allocation->unit_cost : null;
                                $warehouseName = $allocation?->warehouse?->name ?: '';
                                $warehouseLocation = $allocation?->warehouse?->location ?: '';
                                $statusText = match ($rowStatus) {
                                    'ready' => 'Đủ hàng',
                                    'reserved' => 'Đã giữ hàng',
                                    'issued' => 'Đã xuất kho',
                                    'shortage' => 'Thiếu hàng',
                                    'transfer' => 'Cần điều chuyển',
                                    'waiting_purchase' => 'Chờ mua bổ sung',
                                    'matched' => 'Thiếu serial',
                                    default => 'Chưa kiểm tra',
                                };
                            @endphp
                            <tr
                                data-emw5-row
                                data-page-row
                                data-status="{{ $rowStatus }}"
                                data-warehouse="{{ mb_strtolower($warehouseName) }}"
                                data-search="{{ mb_strtolower(trim(($item->item_name ?? '').' '.($item->note ?? '').' '.($product->name ?? '').' '.($product->sku ?? '').' '.($allocation?->note ?? ''))) }}"
                            >
                                <td class="emw5-center">{{ $index + 1 }}</td>
                                <td class="emw5-need">
                                    <strong>{{ $item->item_name }}</strong>
                                    @if($item->note)<small>{{ $item->note }}</small>@endif
                                </td>
                                <td class="emw5-nowrap"><strong>{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }}</strong> {{ $item->unit }}</td>
                                <td class="emw5-product">
                                    <strong>{{ $product->name ?? 'Chưa xác nhận mã hàng' }}</strong>
                                    <small>{{ !empty($product->sku) ? 'SKU: '.$product->sku : 'Sales/Kỹ thuật cần xác nhận đúng sản phẩm' }}</small>
                                    @if($canWarehouseEdit)
                                        <input type="hidden" name="items[{{ $item->id }}][product_id]" value="{{ $productId }}">
                                    @endif
                                </td>
                                @if($canViewCost)
                                    <td class="emw5-money">{{ $unitCost !== null && $unitCost > 0 ? number_format($unitCost, 0, ',', '.') : '—' }}</td>
                                @endif
                                <td class="emw5-warehouse-cell">
                                    @if($canWarehouseEdit && $productId > 0)
                                        <select
                                            name="items[{{ $item->id }}][warehouse_id]"
                                            data-emw5-warehouse
                                            data-product-id="{{ $productId }}"
                                            data-needed="{{ (float) $item->quantity }}"
                                            data-endpoint="{{ $warehouseEndpoint }}"
                                            required
                                        >
                                            @if($allocation?->warehouse_id)
                                                <option value="{{ $allocation->warehouse_id }}" data-available="{{ $available }}" selected>
                                                    {{ $warehouseName ?: 'Kho #'.$allocation->warehouse_id }} · Tồn {{ rtrim(rtrim(number_format($available, 3, '.', ''), '0'), '.') }}
                                                </option>
                                            @else
                                                <option value="">-- Chọn kho --</option>
                                            @endif
                                        </select>
                                        <small data-emw5-location>{{ $warehouseLocation ?: 'Chọn kho để xem tồn khả dụng' }}</small>
                                    @else
                                        <strong>{{ $warehouseName ?: '—' }}</strong>
                                        <small>{{ $warehouseLocation }}</small>
                                    @endif
                                </td>
                                <td class="emw5-stock {{ $available > 0 ? 'is-good' : 'is-bad' }}" data-emw5-stock>
                                    {{ rtrim(rtrim(number_format($available, 3, '.', ''), '0'), '.') }} {{ $product->unit ?? $item->unit }}
                                </td>
                                <td class="emw5-qty-cell">
                                    @if($canWarehouseEdit && $productId > 0)
                                        <input
                                            type="number"
                                            min="0"
                                            max="{{ (float) $item->quantity }}"
                                            step="0.001"
                                            name="items[{{ $item->id }}][quantity]"
                                            value="{{ $allocation ? $allocatedQuantity : (float) $item->quantity }}"
                                            data-emw5-quantity
                                            required
                                        >
                                        <small>{{ $item->unit }}</small>
                                    @else
                                        <strong>{{ rtrim(rtrim(number_format($allocatedQuantity, 3, '.', ''), '0'), '.') }}</strong> {{ $item->unit }}
                                    @endif
                                </td>
                                <td>
                                    @if($canWarehouseEdit && $productId > 0)
                                        <select name="items[{{ $item->id }}][check_status]" data-emw5-row-status>
                                            <option value="ready" @selected(in_array($rowStatus, ['ready', 'reserved', 'issued', 'unchecked'], true))>Đủ hàng</option>
                                            <option value="shortage" @selected($rowStatus === 'shortage')>Thiếu hàng</option>
                                            <option value="transfer" @selected($rowStatus === 'transfer')>Cần điều chuyển</option>
                                            <option value="waiting_purchase" @selected($rowStatus === 'waiting_purchase')>Chờ mua bổ sung</option>
                                        </select>
                                    @else
                                        <span class="emw5-row-status emw5-row-status--{{ $rowStatus }}">{{ $statusText }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($canWarehouseEdit && $productId > 0)
                                        <input name="items[{{ $item->id }}][note]" value="{{ $allocation?->note }}" placeholder="Thiếu hàng, điều chuyển...">
                                    @else
                                        {{ $allocation?->note ?: '—' }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canViewCost ? 10 : 9 }}" class="emw5-no-data">Phiếu chưa có dòng vật tư.</td></tr>
                        @endforelse
                        </tbody>
                        @if($canViewCost)
                            <tfoot><tr><td colspan="4"><strong>Tổng giá vốn tạm tính</strong></td><td class="emw5-money"><strong>{{ number_format($totalCost, 0, ',', '.') }} đ</strong></td><td colspan="5"></td></tr></tfoot>
                        @endif
                    </table>
                </div>

                <footer class="emw5-table-footer">
                    <span data-emw5-result-count>Hiển thị {{ min($totalRows, 10) }} / {{ $totalRows }} dòng</span>
                    <div class="emw5-pagination" data-emw5-pagination></div>
                    <label>Hiển thị
                        <select data-emw5-page-size><option value="5">5 dòng</option><option value="10" selected>10 dòng</option><option value="20">20 dòng</option><option value="50">50 dòng</option></select>
                    </label>
                </footer>

                @if($canWarehouseEdit)
                    <div class="emw5-inline-actions">
                        <div>
                            <strong>Kho chỉ kiểm tra đúng mã hàng đã được yêu cầu.</strong>
                            <span>Không được thay đổi thương hiệu, model hoặc chủng loại.</span>
                        </div>
                        <button type="submit" class="emw5-btn emw5-btn--soft"><i class="bi bi-floppy"></i> Lưu kiểm tra</button>
                    </div>
                    </form>
                @endif
            </section>
        </main>

        <aside class="emw5-side">
            @if($canManagerReview)
                <section class="pt-card emw5-side-card emw5-approval">
                    <header><h3><i class="bi bi-shield-check"></i> QUẢN LÝ PHÊ DUYỆT</h3><span class="emw5-status emw5-status--warning">Chờ phê duyệt</span></header>
                    <div class="emw5-mini-kpis">
                        <div><small>Phiếu vật tư</small><strong>{{ $materialRequest->code }}</strong></div>
                        <div><small>Tổng nhu cầu</small><strong>{{ $totalRows }} dòng</strong></div>
                        <div><small>Đã kiểm tra</small><strong>{{ $checkedRows }} dòng</strong></div>
                    </div>
                    <form method="POST" action="{{ route('project-test.materials.review', [$project, $materialRequest]) }}" data-emw5-review-form>
                        @csrf
                        <label class="emw5-radio"><input type="radio" name="decision" value="approve" checked><span><strong>Phê duyệt và chuyển xuất kho</strong><small>Xác nhận số lượng, tồn và giá vốn phù hợp</small></span></label>
                        <label class="emw5-radio"><input type="radio" name="decision" value="return_warehouse"><span><strong>Trả Kho kiểm tra lại</strong><small>Sai tồn, vị trí, số lượng chuẩn bị hoặc giá vốn</small></span></label>
                        <label class="emw5-radio"><input type="radio" name="decision" value="return_technical"><span><strong>Trả Sales/Kỹ thuật điều chỉnh</strong><small>Sai mã hàng, thương hiệu, model hoặc số lượng hợp đồng</small></span></label>
                        <label class="emw5-label">Ghi chú <small>(bắt buộc nếu trả lại)</small></label>
                        <textarea name="review_note" placeholder="Nhập lý do, ghi chú chi tiết..." data-emw5-review-note></textarea>
                        <div class="emw5-review-actions">
                            <button type="submit" class="emw5-btn emw5-btn--danger" data-emw5-return hidden><i class="bi bi-arrow-return-left"></i> Trả lại</button>
                            <button type="submit" class="emw5-btn emw5-btn--brand" data-emw5-approve><i class="bi bi-shield-check"></i> Phê duyệt & chuyển xuất kho</button>
                        </div>
                    </form>
                </section>
            @else
                <section class="pt-card emw5-side-card">
                    <header><h3><i class="bi bi-bar-chart"></i> TRẠNG THÁI KIỂM TRA</h3></header>
                    <div class="emw5-state-list">
                        <div><i class="bi bi-list-check"></i><span>Tổng nhu cầu</span><strong>{{ $totalRows }} dòng</strong></div>
                        <div><i class="bi bi-clipboard-check"></i><span>Đã kiểm tra</span><strong>{{ $checkedRows }} dòng ({{ $percent }}%)</strong></div>
                        <div class="is-success"><i class="bi bi-box-seam"></i><span>Đủ hàng</span><strong>{{ $readyRows }} dòng</strong></div>
                        <div class="is-danger"><i class="bi bi-exclamation-square"></i><span>Thiếu hàng</span><strong>{{ $shortageRows + $waitingPurchaseRows }} dòng</strong></div>
                        <div class="is-warning"><i class="bi bi-arrow-left-right"></i><span>Cần điều chuyển</span><strong>{{ $transferRows }} dòng</strong></div>
                    </div>
                </section>
            @endif

            @if($canViewCost)
                <section class="pt-card emw5-side-card emw5-cost">
                    <header><h3><i class="bi bi-cash-stack"></i> GIÁ VỐN TẠM TÍNH</h3></header>
                    <strong>{{ number_format($totalCost, 0, ',', '.') }} đ</strong>
                    <small>Chỉ hiển thị cho Kho, Quản lý và Admin.</small>
                </section>
            @endif

            <section class="pt-card emw5-side-card">
                <header><h3><i class="bi bi-chat-left-text"></i> GHI CHÚ PHIẾU</h3></header>
                <p class="emw5-request-note">{{ $materialRequest->request_note ?: 'Chưa có ghi chú chung từ Kỹ thuật.' }}</p>
            </section>

            @if($canWarehouseEdit)
                <section class="pt-card emw5-side-card emw5-actions-card">
                    <header><h3><i class="bi bi-lightning-charge"></i> THAO TÁC</h3></header>
                    <button type="button" class="emw5-btn emw5-btn--soft" data-emw5-save-trigger><i class="bi bi-floppy"></i> Lưu kiểm tra</button>
                    <form method="POST" action="{{ route('project-test.warehouse.manager.submit', $materialRequest) }}" data-confirm="Gửi kết quả kiểm tra tồn cho Quản lý phê duyệt?">
                        @csrf
                        <button type="submit" class="emw5-btn emw5-btn--brand" {{ $unconfirmedRows > 0 ? 'disabled' : '' }}><i class="bi bi-send"></i> Gửi Quản lý phê duyệt</button>
                    </form>
                    @if($unconfirmedRows > 0)<small class="emw5-blocked-note">Chưa thể gửi vì còn dòng chưa xác nhận đúng SKU.</small>@endif
                </section>
            @elseif(! $canManagerReview)
                <section class="pt-card emw5-side-card">
                    <header><h3><i class="bi bi-clipboard-check"></i> VIỆC TIẾP THEO</h3></header>
                    <p><strong>{{ $statusLabel }}</strong></p>
                    <small>{{ $materialRequest->status === 'pending_manager' ? 'Quản lý/Admin kiểm tra và thực hiện phê duyệt.' : 'Theo dõi trạng thái phiếu tại đây.' }}</small>
                </section>
            @endif
        </aside>
    </div>
</div>
@endif
