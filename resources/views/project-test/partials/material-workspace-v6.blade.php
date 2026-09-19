{{--
    EGO Material Workspace V6
    Phạm vi: Trang chi tiết Công trình -> Chuẩn bị vật tư.
    Không thay menu, topbar, layout chung, route hay dữ liệu hiện hữu.
--}}
@php
    $selectedMaterialRequestId = (int) request('material_request', 0);
    $materialRequest = $selectedMaterialRequestId > 0
        ? $project->materialRequests->firstWhere('id', $selectedMaterialRequestId)
        : $project->materialRequests->sortByDesc('id')->first();
@endphp

@if (! $materialRequest)
    <article class="pt-card emw6-empty">
        <span><i class="bi bi-box-seam"></i></span>
        <div>
            <h2>Chưa có phiếu vật tư</h2>
            <p>Kỹ thuật chưa lập nhu cầu vật tư cho công trình này.</p>
        </div>
    </article>
@else
    @php
        $items = $materialRequest->items ?? collect();
        $totalRows = $items->count();
        $isWarehouseUser = (bool) ($can['warehouse'] ?? false)
            || auth()->user()?->hasAnyRole(['warehouse', 'kho']);
        $isManagerUser = (bool) ($can['admin'] ?? false)
            || auth()->user()?->hasAnyRole(['admin', 'management', 'manager']);
        $canViewCost = $isWarehouseUser
            || $isManagerUser
            || auth()->user()?->can('finance.project_profit.view');
        $legacyApprovedNeedsCheck = $materialRequest->status === 'approved'
            && blank($materialRequest->warehouse_status);
        $canWarehouseEdit = $isWarehouseUser
            && (in_array($materialRequest->status, ['warehouse_check', 'preparing'], true) || $legacyApprovedNeedsCheck)
            && $materialRequest->warehouse_status !== 'reserved';
        $canManagerReview = $isManagerUser && $materialRequest->status === 'pending_manager';

        $statusLabels = [
            'warehouse_check' => 'Kho đang kiểm tra tồn',
            'pending_manager' => 'Chờ Quản lý phê duyệt',
            'approved' => 'Đã duyệt · Chờ xuất kho',
            'preparing' => 'Kho đang chuẩn bị xuất',
            'revision' => 'Chờ Sales/Kỹ thuật điều chỉnh',
            'issued' => 'Đã xuất kho',
        ];
        $requestStatusLabel = $legacyApprovedNeedsCheck
            ? 'Kho đang kiểm tra tồn'
            : ($statusLabels[$materialRequest->status] ?? 'Chờ Kho kiểm tra');

        $statusMeta = [
            'ready' => ['label' => 'Đủ hàng', 'tone' => 'success', 'icon' => 'bi-check-circle-fill'],
            'reserved' => ['label' => 'Đã giữ hàng', 'tone' => 'success', 'icon' => 'bi-lock-fill'],
            'issued' => ['label' => 'Đã xuất', 'tone' => 'success', 'icon' => 'bi-box-arrow-up-right'],
            'shortage' => ['label' => 'Thiếu hàng', 'tone' => 'danger', 'icon' => 'bi-x-circle-fill'],
            'transfer' => ['label' => 'Cần điều chuyển', 'tone' => 'warning', 'icon' => 'bi-arrow-left-right'],
            'waiting_purchase' => ['label' => 'Chờ mua bổ sung', 'tone' => 'pending', 'icon' => 'bi-cart-plus-fill'],
            'matched' => ['label' => 'Chưa đủ serial', 'tone' => 'warning', 'icon' => 'bi-upc-scan'],
            '' => ['label' => 'Chưa kiểm tra', 'tone' => 'neutral', 'icon' => 'bi-clock'],
        ];

        $checkedRows = 0;
        $readyRows = 0;
        $shortageRows = 0;
        $transferRows = 0;
        $waitingRows = 0;
        $totalCost = 0.0;
        $missingCostRows = 0;
        $unconfirmedRows = $items->filter(function ($materialItem) {
            $allocation = ($materialItem->allocations ?? collect())->first();
            return (int) ($materialItem->product_id ?: $allocation?->product_id ?: 0) <= 0;
        })->count();

        foreach ($items as $materialItem) {
            $allocation = ($materialItem->allocations ?? collect())->first();
            if ($allocation) {
                $checkedRows++;
                if (in_array($allocation->status, ['ready', 'reserved', 'issued'], true)) {
                    $readyRows++;
                } elseif ($allocation->status === 'shortage') {
                    $shortageRows++;
                } elseif ($allocation->status === 'transfer') {
                    $transferRows++;
                } elseif ($allocation->status === 'waiting_purchase') {
                    $waitingRows++;
                }

                $allocatedQuantity = (float) ($allocation->allocated_quantity ?? 0);
                if ($allocation->unit_cost !== null && (float) $allocation->unit_cost > 0) {
                    $totalCost += (float) $allocation->unit_cost * $allocatedQuantity;
                } else {
                    $missingCostRows++;
                }
            }
        }

        $checkedPercent = $totalRows > 0 ? (int) round(($checkedRows / $totalRows) * 100) : 0;
        $materialView = request('material_view');
        if (! in_array($materialView, ['needs', 'stock', 'issue', 'return'], true)) {
            $materialView = $isWarehouseUser || $isManagerUser ? 'stock' : 'needs';
        }
    @endphp

    <div
        class="emw6-workspace"
        data-emw6-root
        data-material-view="{{ $materialView }}"
        data-can-edit="{{ $canWarehouseEdit ? '1' : '0' }}"
        data-page-size="10"
    >
        <section class="pt-card emw6-summary">
            <div class="emw6-summary__identity">
                <span class="emw6-summary__icon"><i class="bi bi-box-seam"></i></span>
                <div>
                    <small>CHUẨN BỊ VẬT TƯ · {{ $materialRequest->code }}</small>
                    <h2>{{ $project->name }}</h2>
                    <p>
                        Ngày cần: {{ optional($materialRequest->needed_at)->format('d/m/Y') ?: '—' }}
                        · Người lập: {{ $materialRequest->requester?->name ?: 'Kỹ thuật' }}
                    </p>
                    <span class="emw6-request-status">{{ $requestStatusLabel }}</span>
                </div>
            </div>
            <div class="emw6-summary__kpis">
                <div><small>Tổng nhu cầu</small><strong>{{ $totalRows }} dòng</strong></div>
                <div><small>Đã kiểm tra</small><strong data-emw6-kpi-checked>{{ $checkedRows }}/{{ $totalRows }} dòng</strong></div>
                <div class="emw6-progress-card">
                    <small>Tiến độ</small>
                    <strong data-emw6-kpi-percent>{{ $checkedPercent }}%</strong>
                    <span><i data-emw6-progress style="width: {{ $checkedPercent }}%"></i></span>
                </div>
            </div>
        </section>

        <div class="emw6-layout">
            <main class="emw6-main">
                <article class="pt-card emw6-table-card" data-emw6-stock-panel>
                    <header class="emw6-section-head">
                        <div>
                            <h2><i class="bi bi-clipboard2-check"></i> KIỂM TRA TỒN KHO</h2>
                            <p>Kiểm tra khả năng đáp ứng đúng vật tư do Sales/Kỹ thuật xác nhận.</p>
                        </div>
                        @if ($canWarehouseEdit)
                            <button type="button" class="pt-btn pt-btn--soft" data-emw6-auto-warehouse>
                                <i class="bi bi-diagram-3"></i> Đề xuất kho tự động
                            </button>
                        @endif
                    </header>

                    @if($unconfirmedRows > 0)
                        <div class="emw6-contract-alert">
                            <i class="bi bi-exclamation-triangle"></i>
                            <div>
                                <strong>{{ $unconfirmedRows }} dòng chưa gắn đúng SKU theo hợp đồng/phương án.</strong>
                                <small>Sales/Kỹ thuật phải xác nhận mã hàng trước. Kho không được tự chọn hoặc thay đổi thương hiệu, model và SKU.</small>
                            </div>
                        </div>
                    @endif

                    <div class="emw6-toolbar">
                        <label class="emw6-search">
                            <i class="bi bi-search"></i>
                            <input type="search" placeholder="Tìm vật tư, mã sản phẩm hoặc SKU..." data-emw6-search>
                        </label>
                        <select class="pt-select" data-emw6-warehouse-filter>
                            <option value="">Tất cả kho</option>
                            @foreach($items->flatMap(fn ($item) => $item->allocations ?? collect())->pluck('warehouse')->filter()->unique('id') as $warehouseFilter)
                                <option value="{{ $warehouseFilter->id }}">{{ $warehouseFilter->name }}</option>
                            @endforeach
                        </select>
                        <select class="pt-select" data-emw6-status-filter>
                            <option value="">Tất cả trạng thái</option>
                            <option value="ready">Đủ hàng</option>
                            <option value="shortage">Thiếu hàng</option>
                            <option value="transfer">Cần điều chuyển</option>
                            <option value="waiting_purchase">Chờ mua bổ sung</option>
                            <option value="unchecked">Chưa kiểm tra</option>
                        </select>
                        <button type="button" class="pt-btn pt-btn--soft" data-emw6-filter-button>
                            <i class="bi bi-funnel"></i> Bộ lọc
                        </button>
                        <button type="button" class="pt-btn pt-btn--soft emw6-export" data-emw6-export>
                            <i class="bi bi-file-earmark-excel"></i> Xuất Excel
                        </button>
                    </div>

                    @if($canWarehouseEdit)
                        <form
                            id="emw6-stock-form"
                            method="POST"
                            action="{{ route('project-test.warehouse.mapping.save', $materialRequest) }}"
                            data-confirm="Lưu kết quả kiểm tra tồn kho?"
                        >
                            @csrf
                    @endif

                    <div class="emw6-table-scroll">
                        <table class="emw6-table" data-emw6-table>
                            <thead>
                                <tr>
                                    <th>STT</th>
                                    <th>Vật tư kỹ thuật yêu cầu</th>
                                    <th>SL yêu cầu</th>
                                    <th>Sản phẩm theo yêu cầu</th>
                                    @if($canViewCost)<th>Giá vốn</th>@endif
                                    <th>Tồn khả dụng</th>
                                    <th>Kho / vị trí</th>
                                    <th>SL chuẩn bị</th>
                                    <th>Tình trạng</th>
                                    <th>Ghi chú</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($items as $index => $item)
                                    @php
                                        $allocation = ($item->allocations ?? collect())->first();
                                        $product = $item->product ?: $allocation?->product;
                                        $productId = (int) ($item->product_id ?: $allocation?->product_id ?: 0);
                                        $warehouse = $allocation?->warehouse;
                                        $available = (float) ($allocation?->available_snapshot ?? 0);
                                        $prepared = (float) ($allocation?->allocated_quantity ?? 0);
                                        $rowStatus = (string) ($allocation?->status ?? '');
                                        $normalizedStatus = $rowStatus === '' ? 'unchecked' : $rowStatus;
                                        $meta = $statusMeta[$rowStatus] ?? $statusMeta[''];
                                        $unitCost = $allocation?->unit_cost !== null ? (float) $allocation->unit_cost : null;
                                        $requiredQuantity = (float) $item->quantity;
                                        $shortageQuantity = max(0, $requiredQuantity - min($available, $prepared));
                                        $searchText = mb_strtolower(implode(' ', array_filter([
                                            $item->item_name,
                                            $item->note,
                                            $product?->name,
                                            $product?->sku,
                                            $warehouse?->name,
                                            $allocation?->note,
                                        ])));
                                    @endphp
                                    <tr
                                        class="emw6-item-row"
                                        data-emw6-row
                                        data-row-index="{{ $index + 1 }}"
                                        data-search="{{ $searchText }}"
                                        data-status="{{ $normalizedStatus }}"
                                        data-warehouse="{{ $warehouse?->id ?: '' }}"
                                        data-required="{{ $requiredQuantity }}"
                                        data-unit="{{ $product?->unit ?: $item->unit }}"
                                        data-unit-cost="{{ $unitCost ?: 0 }}"
                                        data-selected-warehouse-id="{{ $warehouse?->id ?: '' }}"
                                    >
                                        <td class="emw6-stt">{{ $index + 1 }}</td>
                                        <td class="emw6-need-cell">
                                            <span class="emw6-product-thumb"><i class="bi bi-box"></i></span>
                                            <div>
                                                <strong>{{ $item->item_name }}</strong>
                                                <small>{{ $item->note ?: 'Theo danh mục đã xác nhận trong hợp đồng/phương án' }}</small>
                                            </div>
                                        </td>
                                        <td class="emw6-number-cell">
                                            <strong>{{ rtrim(rtrim(number_format($requiredQuantity, 3, '.', ''), '0'), '.') }}</strong>
                                            <small>{{ $item->unit }}</small>
                                        </td>
                                        <td class="emw6-product-cell">
                                            <strong>{{ $product?->name ?: 'Chưa xác nhận mã hàng' }}</strong>
                                            <small>{{ $product?->sku ? 'SKU: '.$product->sku : 'Sales/Kỹ thuật phải xác nhận đúng SKU trước khi Kho xử lý' }}</small>
                                            @if($canWarehouseEdit)
                                                <input type="hidden" name="items[{{ $item->id }}][product_id]" value="{{ $productId }}">
                                            @endif
                                        </td>
                                        @if($canViewCost)
                                            <td class="emw6-money-cell">
                                                <strong data-emw6-unit-cost>{{ $unitCost && $unitCost > 0 ? number_format($unitCost, 0, ',', '.') : '—' }}</strong>
                                                <small>VND/ĐVT</small>
                                            </td>
                                        @endif
                                        <td class="emw6-stock-cell">
                                            <strong class="{{ $available > 0 ? 'is-positive' : 'is-zero' }}" data-emw6-available>
                                                {{ rtrim(rtrim(number_format($available, 3, '.', ''), '0'), '.') }} {{ $product?->unit ?: $item->unit }}
                                            </strong>
                                            <small data-emw6-stock-help>{{ $available > 0 ? 'Tồn có thể cấp tại kho chọn' : 'Chưa có tồn khả dụng' }}</small>
                                        </td>
                                        <td class="emw6-warehouse-cell">
                                            @if($canWarehouseEdit && $productId > 0)
                                                <select
                                                    class="pt-select"
                                                    name="items[{{ $item->id }}][warehouse_id]"
                                                    data-emw6-warehouse-select
                                                    data-product-id="{{ $productId }}"
                                                    data-url="{{ route('project-test.warehouse.warehouses', $materialRequest) }}"
                                                >
                                                    @if($warehouse)
                                                        <option value="{{ $warehouse->id }}" selected>
                                                            {{ $warehouse->name }}{{ $warehouse->location ? ' · '.$warehouse->location : '' }}
                                                        </option>
                                                    @else
                                                        <option value="">-- Chọn kho cấp hàng --</option>
                                                    @endif
                                                </select>
                                                <small data-emw6-location>{{ $warehouse?->location ?: 'Chọn kho để xem tồn và vị trí' }}</small>
                                            @else
                                                <strong>{{ $warehouse?->name ?: '—' }}</strong>
                                                <small>{{ $warehouse?->location ?: 'Chưa xác định vị trí' }}</small>
                                            @endif
                                        </td>
                                        <td class="emw6-prepare-cell">
                                            @if($canWarehouseEdit && $productId > 0)
                                                <div class="emw6-qty-control">
                                                    <input
                                                        class="pt-input"
                                                        type="number"
                                                        min="0"
                                                        max="{{ $requiredQuantity }}"
                                                        step="0.001"
                                                        name="items[{{ $item->id }}][quantity]"
                                                        value="{{ $allocation ? $prepared : $requiredQuantity }}"
                                                        data-emw6-quantity
                                                    >
                                                    <span>{{ $item->unit }}</span>
                                                </div>
                                                <small data-emw6-qty-error></small>
                                            @else
                                                <strong>{{ rtrim(rtrim(number_format($prepared, 3, '.', ''), '0'), '.') }} {{ $item->unit }}</strong>
                                            @endif
                                        </td>
                                        <td class="emw6-status-cell">
                                            @if($canWarehouseEdit && $productId > 0)
                                                <select
                                                    class="pt-select emw6-status-select"
                                                    name="items[{{ $item->id }}][check_status]"
                                                    data-emw6-row-status
                                                >
                                                    <option value="ready" @selected(in_array($rowStatus, ['', 'ready', 'reserved', 'issued'], true))>Đủ hàng</option>
                                                    <option value="shortage" @selected($rowStatus === 'shortage')>Thiếu hàng</option>
                                                    <option value="transfer" @selected($rowStatus === 'transfer')>Cần điều chuyển</option>
                                                    <option value="waiting_purchase" @selected($rowStatus === 'waiting_purchase')>Chờ mua bổ sung</option>
                                                </select>
                                            @else
                                                <span class="emw6-badge emw6-badge--{{ $meta['tone'] }}">
                                                    <i class="bi {{ $meta['icon'] }}"></i> {{ $meta['label'] }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="emw6-note-cell">
                                            @if($canWarehouseEdit && $productId > 0)
                                                <input
                                                    class="pt-input"
                                                    name="items[{{ $item->id }}][note]"
                                                    value="{{ $allocation?->note }}"
                                                    maxlength="1000"
                                                    placeholder="Điều chuyển, mua bổ sung..."
                                                    data-emw6-row-note
                                                >
                                            @else
                                                <span>{{ $allocation?->note ?: '—' }}</span>
                                            @endif
                                        </td>
                                        <td class="emw6-action-cell">
                                            <button type="button" class="pt-btn pt-btn--soft pt-btn--sm" data-emw6-row-detail>
                                                {{ $canWarehouseEdit ? 'Cập nhật' : 'Xem' }}
                                            </button>
                                        </td>
                                    </tr>
                                    <tr
                                        class="emw6-warning-row"
                                        data-emw6-warning-row
                                        data-parent-index="{{ $index + 1 }}"
                                        @if(! in_array($rowStatus, ['shortage', 'transfer', 'waiting_purchase'], true)) hidden @endif
                                    >
                                        <td></td>
                                        <td colspan="{{ $canViewCost ? 10 : 9 }}">
                                            <div class="emw6-warning-box emw6-warning-box--{{ $meta['tone'] }}">
                                                <i class="bi {{ $meta['icon'] }}"></i>
                                                <div>
                                                    <strong data-emw6-warning-title>
                                                        @if($rowStatus === 'transfer')
                                                            Cần điều chuyển từ kho khác
                                                        @elseif($rowStatus === 'waiting_purchase')
                                                            Chờ mua bổ sung
                                                        @else
                                                            Thiếu {{ rtrim(rtrim(number_format($shortageQuantity, 3, '.', ''), '0'), '.') }} {{ $item->unit }}
                                                        @endif
                                                    </strong>
                                                    <small data-emw6-warning-detail>{{ $allocation?->note ?: 'Kho cập nhật phương án xử lý và gửi Quản lý phê duyệt.' }}</small>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ $canViewCost ? 11 : 10 }}"><div class="emw6-empty-row">Phiếu chưa có dòng vật tư.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($canWarehouseEdit)
                        </form>
                    @endif

                    <footer class="emw6-table-footer">
                        <span data-emw6-result-summary>Hiển thị {{ min(10, $totalRows) }} trong tổng số {{ $totalRows }} dòng</span>
                        <div class="emw6-pagination" data-emw6-pagination></div>
                        <label>
                            <select class="pt-select" data-emw6-page-size>
                                <option value="5">5 / trang</option>
                                <option value="10" selected>10 / trang</option>
                                <option value="20">20 / trang</option>
                                <option value="50">50 / trang</option>
                            </select>
                        </label>
                    </footer>
                </article>

                <article class="pt-card emw6-stage-panel" data-emw6-stage="needs" hidden>
                    <i class="bi bi-list-check"></i>
                    <div>
                        <h3>Nhu cầu kỹ thuật</h3>
                        <p>Danh sách trên được khóa theo sản phẩm, thương hiệu, model và SKU mà Sales/Kỹ thuật đã xác nhận.</p>
                    </div>
                </article>

                <article class="pt-card emw6-stage-panel" data-emw6-stage="issue" hidden>
                    <i class="bi bi-box-arrow-up-right"></i>
                    <div>
                        <h3>Xuất kho</h3>
                        @if(in_array($materialRequest->status, ['approved', 'preparing', 'issued'], true))
                            <p>Quản lý đã phê duyệt. Kho có thể giữ hàng và thực hiện nghiệp vụ xuất kho theo phiếu.</p>
                            @if($isWarehouseUser)
                                <a class="pt-btn pt-btn--brand" href="{{ route('project-test.warehouse.show', $materialRequest) }}">Mở nghiệp vụ xuất kho</a>
                            @endif
                        @else
                            <p>Chức năng xuất kho đang khóa. Kho phải hoàn tất kiểm tra tồn và được Quản lý phê duyệt trước.</p>
                        @endif
                    </div>
                </article>

                <article class="pt-card emw6-stage-panel" data-emw6-stage="return" hidden>
                    <i class="bi bi-arrow-return-left"></i>
                    <div>
                        <h3>Thu hồi vật tư</h3>
                        <p>Chỉ mở nghiệp vụ thu hồi sau khi đã phát sinh xuất kho. Mọi điều chỉnh phải được lưu lịch sử.</p>
                    </div>
                </article>
            </main>

            <aside class="emw6-side">
                <article class="pt-card emw6-side-card emw6-status-card">
                    <header><h3>TRẠNG THÁI KIỂM TRA TỒN</h3></header>
                    <div class="emw6-status-list">
                        <div><span class="is-neutral"><i class="bi bi-list-ul"></i></span><small>Tổng nhu cầu</small><strong>{{ $totalRows }} dòng</strong></div>
                        <div><span class="is-info"><i class="bi bi-clipboard-check"></i></span><small>Đã kiểm tra</small><strong data-emw6-side-checked>{{ $checkedRows }} dòng</strong></div>
                        <div><span class="is-success"><i class="bi bi-check-circle"></i></span><small>Đủ hàng</small><strong data-emw6-side-ready>{{ $readyRows }} dòng</strong></div>
                        <div><span class="is-danger"><i class="bi bi-x-circle"></i></span><small>Thiếu hàng</small><strong data-emw6-side-shortage>{{ $shortageRows }} dòng</strong></div>
                        <div><span class="is-warning"><i class="bi bi-arrow-left-right"></i></span><small>Cần điều chuyển</small><strong data-emw6-side-transfer>{{ $transferRows }} dòng</strong></div>
                        <div><span class="is-pending"><i class="bi bi-cart-plus"></i></span><small>Chờ mua bổ sung</small><strong data-emw6-side-waiting>{{ $waitingRows }} dòng</strong></div>
                    </div>
                </article>

                @if($canViewCost)
                    <article class="pt-card emw6-side-card emw6-cost-card">
                        <header><h3>GIÁ VỐN TẠM TÍNH</h3></header>
                        <p data-emw6-total-cost>{{ number_format($totalCost, 0, ',', '.') }} đ</p>
                        <small>Chưa bao gồm VAT nếu lô hàng chưa cập nhật chi phí sau VAT.</small>
                        @if($missingCostRows > 0)
                            <div class="emw6-cost-warning"><i class="bi bi-exclamation-triangle"></i> {{ $missingCostRows }} dòng chưa có giá vốn.</div>
                        @endif
                    </article>
                @endif

                @if($canWarehouseEdit)
                    <article class="pt-card emw6-side-card">
                        <header><h3>GHI CHÚ CHUNG</h3></header>
                        <textarea
                            class="pt-textarea"
                            name="warehouse_note"
                            form="emw6-stock-form"
                            maxlength="500"
                            placeholder="Ghi chú tình trạng chuẩn bị hàng..."
                            data-emw6-general-note
                        >{{ $materialRequest->issue_note }}</textarea>
                        <small class="emw6-char-count"><span data-emw6-note-count>{{ mb_strlen((string) $materialRequest->issue_note) }}</span>/500</small>
                    </article>
                @endif

                <article class="pt-card emw6-side-card emw6-action-card">
                    <header><h3>THAO TÁC</h3></header>

                    @if($canWarehouseEdit)
                        <button class="pt-btn pt-btn--soft emw6-full-button" type="submit" form="emw6-stock-form" @disabled($unconfirmedRows > 0)>
                            <i class="bi bi-floppy"></i> Lưu tạm
                        </button>
                        <form method="POST" action="{{ route('project-test.warehouse.manager.submit', $materialRequest) }}" data-confirm="Gửi kết quả kiểm tra tồn cho Quản lý phê duyệt?">
                            @csrf
                            <button class="pt-btn pt-btn--brand emw6-full-button" type="submit" data-emw6-submit-manager @disabled($unconfirmedRows > 0)>
                                <i class="bi bi-send-check"></i> Gửi Quản lý phê duyệt
                            </button>
                        </form>
                        @if($unconfirmedRows > 0)
                            <small class="emw6-action-warning">Chưa thể lưu/gửi duyệt cho tới khi Sales/Kỹ thuật xác nhận đủ SKU.</small>
                        @endif
                    @elseif($canManagerReview)
                        <form method="POST" action="{{ route('project-test.materials.review', [$project, $materialRequest]) }}" data-emw6-approval-form>
                            @csrf
                            <label class="emw6-decision">
                                <input type="radio" name="decision" value="approve" checked>
                                <span><strong>Phê duyệt và chuyển xuất kho</strong><small>Xác nhận tồn, số lượng và giá vốn phù hợp.</small></span>
                            </label>
                            <label class="emw6-decision">
                                <input type="radio" name="decision" value="return_warehouse">
                                <span><strong>Trả Kho kiểm tra lại</strong><small>Sai tồn, kho/vị trí, số lượng hoặc giá vốn.</small></span>
                            </label>
                            <label class="emw6-decision">
                                <input type="radio" name="decision" value="return_technical">
                                <span><strong>Trả Sales/Kỹ thuật điều chỉnh</strong><small>Sai vật tư, thương hiệu, model hoặc SKU hợp đồng.</small></span>
                            </label>
                            <textarea class="pt-textarea" name="review_note" maxlength="3000" placeholder="Nhập lý do khi trả lại..." data-emw6-review-note></textarea>
                            <div class="emw6-approval-actions">
                                <button class="pt-btn pt-btn--danger" type="submit" data-emw6-return-button hidden><i class="bi bi-arrow-return-left"></i> Trả lại</button>
                                <button class="pt-btn pt-btn--brand" type="submit" data-emw6-approve-button><i class="bi bi-shield-check"></i> Phê duyệt & chuyển xuất kho</button>
                            </div>
                        </form>
                    @else
                        <div class="emw6-readonly-state">
                            <i class="bi bi-info-circle"></i>
                            <div><strong>{{ $requestStatusLabel }}</strong><small>Tài khoản hiện tại chỉ được theo dõi trạng thái phiếu.</small></div>
                        </div>
                    @endif

                    <button type="button" class="pt-btn pt-btn--soft emw6-full-button" data-emw6-export>
                        <i class="bi bi-file-earmark-excel"></i> Xuất Excel
                    </button>
                </article>
            </aside>
        </div>
    </div>
@endif
