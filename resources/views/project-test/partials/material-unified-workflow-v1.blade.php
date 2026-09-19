@php
    $workflowUser = auth()->user();
    $requestStatus = (string) ($materialRequest?->status ?? '');

    /*
     * FastFlow V12:
     * - Khi chưa có phiếu hoặc phiếu bị trả Kỹ thuật: chỉ Kỹ thuật được sửa.
     * - Khi phiếu đã gửi Kho (warehouse_check): Admin/Kho chuyển sang đúng bảng đối chiếu.
     * Tránh tài khoản Admin vẫn nhìn form Kỹ thuật sau khi đã gửi Kho.
     */
    $isTechnicalEditing = (bool) (
        ($canCreateTechnicalRequest || $canEditTechnicalRequest)
        && (! $materialRequest || $requestStatus === 'revision')
    );

    $hasWarehouseRole = (bool) (
        $workflowUser
        && (
            $workflowUser->hasAnyRole(['admin', 'warehouse', 'kho'])
            || $workflowUser->can('project-test.warehouse')
        )
    );

    $isWarehouseEditing = (bool) (
        $materialRequest
        && in_array($requestStatus, ['warehouse_check', 'approved'], true)
        && (string) ($materialRequest->warehouse_status ?? '') !== 'reserved'
        && ($canWarehouseEdit || $hasWarehouseRole)
    );

    $canViewCost = (bool) (
        $canViewCost
        || ($workflowUser && $workflowUser->hasAnyRole(['admin', 'management', 'manager', 'warehouse', 'kho', 'accounting']))
    );

    $managerReviewerName = data_get($materialRequest, 'reviewer.name') ?: 'Admin / Quản lý';
    $managerReviewedAt = optional($materialRequest?->reviewed_at)->format('d/m/Y H:i');
@endphp

<section class="emw9-view" data-emw9-view="proposal">
    @if($materialRequest?->review_note)
        <div class="emw9-feedback {{ $materialRequest->status === 'revision' ? 'is-technical' : 'is-warehouse' }}">
            <i class="bi bi-chat-left-text-fill"></i>
            <div><strong>{{ $materialRequest->status === 'revision' ? 'Yêu cầu Kỹ thuật điều chỉnh' : 'Phản hồi của Admin / Quản lý' }}</strong><span>{{ $materialRequest->review_note }}</span><small>{{ $managerReviewerName }}{{ $managerReviewedAt ? ' · '.$managerReviewedAt : '' }} · Chỉ dòng Kỹ thuật thay đổi mới phải Kho đối chiếu lại.</small></div>
        </div>
    @endif

    @if($canCreateTechnicalRequest || $materialRequest)
        <article class="pt-card emw9-card">
            <header class="emw9-card__head">
                <div><h3>Đề nghị & đối chiếu vật tư</h3><p>Kỹ thuật nhập tay hoặc tải Excel; Kho ghép SKU, kiểm tra tồn và giá vốn ở bước sau.</p></div>
                <div class="emw9-badges"><span>{{ $totalRows }} dòng</span><span>{{ $checkedRows }}/{{ $totalRows }} đã kiểm tra</span><span>{{ $readyRows }}/{{ $totalRows }} đủ</span></div>
            </header>

            @if($isTechnicalEditing)
                <form id="emw9-technical-form" method="POST" action="{{ $technicalFormAction }}" data-emw9-tech-form>@csrf
                    <div class="emw9-meta">
                        <label><span>Ngày cần vật tư</span><input class="pt-input" type="date" name="needed_at" value="{{ old('needed_at', optional($materialRequest?->needed_at)->format('Y-m-d') ?: now()->format('Y-m-d')) }}" required></label>
                        <label><span>Ghi chú chung</span><input class="pt-input" name="request_note" value="{{ old('request_note', $materialRequest?->request_note) }}" placeholder="Thời gian giao, phạm vi, lưu ý hợp đồng..."></label>
                    </div>
                    @if($materialRequest?->status === 'revision')
                        <div class="emw9-notice is-good"><i class="bi bi-shield-check"></i> Hệ thống giữ nguyên các dòng Kho đã kiểm tra. Chỉ dòng sửa, thêm hoặc xóa mới bị đưa về “Chờ Kho”.</div>
                    @endif
                    <div class="emw9-table-wrap">
                        <table class="emw9-table is-technical-table">
                            <thead><tr><th>Vật tư / model</th><th>SL / ĐVT</th><th>Thông số / lưu ý</th><th>Đối chiếu hiện tại</th><th></th></tr></thead>
                            <tbody data-emw9-tech-body>
                                @foreach($technicalRows as $row)
                                    @php
                                        $rowId = (int) data_get($row, 'id', 0);
                                        $rowProductId = (int) data_get($row, 'product_id', 0);
                                        $rowProduct = $productsById->get($rowProductId) ?: data_get($row, 'product');
                                        $rowAllocation = collect(data_get($row, 'allocations', []))->first();
                                        $rowStatus = (string) ($rowAllocation?->status ?? '');
                                        $rowMeta = $rowStatusMeta[$rowStatus] ?? $rowStatusMeta[''];
                                    @endphp
                                    <tr data-emw9-tech-row>
                                        <td class="emw9-material-cell">
                                            <input type="hidden" name="item_id[]" value="{{ $rowId ?: '' }}">
                                            <input class="pt-input" name="item_name[]" value="{{ old('item_name.'.$loop->index, data_get($row,'item_name', data_get($rowProduct,'name',''))) }}" list="emw9-product-suggestions" placeholder="Tên vật tư / model" required data-emw9-tech-name>
                                            <input type="hidden" name="product_id[]" value="{{ $rowProductId ?: '' }}" data-emw9-tech-product-id>
                                            @if($rowProduct)<small>{{ data_get($rowProduct,'name') }} · SKU {{ data_get($rowProduct,'sku') ?: '—' }}</small>@endif
                                        </td>
                                        <td><div class="emw9-qty"><input class="pt-input" type="number" min="0.001" step="0.001" name="quantity[]" value="{{ data_get($row,'quantity',1) }}" required><input class="pt-input" name="unit[]" value="{{ data_get($row,'unit','cái') ?: 'cái' }}" required data-emw9-tech-unit></div></td>
                                        <td><input class="pt-input" name="item_note[]" value="{{ data_get($row,'note') }}" placeholder="Thông số, vị trí lắp..."></td>
                                        <td>
                                            @if($rowAllocation)
                                                <span class="emw9-status {{ $rowMeta[1] }}"><i class="bi {{ $rowMeta[2] }}"></i>{{ $rowMeta[0] }}</span>
                                                <small>{{ $rowAllocation->product?->name ?: 'Đã ghép SKU' }} · {{ $rowAllocation->warehouse?->name ?: 'Kho' }}</small>
                                            @else
                                                <span class="emw9-status is-neutral"><i class="bi bi-clock"></i>Chờ Kho</span>
                                            @endif
                                        </td>
                                        <td><button type="button" class="emw9-remove" data-emw9-remove-row title="Xóa dòng"><i class="bi bi-x-lg"></i></button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="emw9-importbar">
                        <input type="file" accept=".xlsx,.xls,.csv" hidden data-emw11-excel-input data-url="{{ route('project-test.materials.import-excel', $project) }}">
                        <button type="button" class="pt-btn pt-btn--soft" data-emw9-add-row><i class="bi bi-plus-lg"></i> Thêm vật tư</button>
                        <button type="button" class="pt-btn pt-btn--soft" data-emw11-excel-button><i class="bi bi-file-earmark-excel"></i> Tải danh sách Excel</button>
                        <span class="emw11-import-status" data-emw11-import-status>Hỗ trợ .xlsx, .xls, .csv · tối đa 300 dòng</span>
                        <button type="submit"
                                form="emw9-technical-form"
                                class="pt-btn pt-btn--brand"
                                data-emw11-submit-materials>
                            <i class="bi bi-send-check"></i>
                            {{ $materialRequest?->status === 'revision'
                                ? 'Cập nhật & gửi lại Kho'
                                : 'Gửi đề nghị cấp vật tư'
                            }}
                        </button>
                    </div>
                </form>
            @else
                @if($materialRequest)
                    <div class="emw9-meta-readonly"><span><small>Ngày cần</small><b>{{ optional($materialRequest->needed_at)->format('d/m/Y') ?: '—' }}</b></span><span><small>Ghi chú Kỹ thuật</small><b>{{ $materialRequest->request_note ?: 'Không có' }}</b></span></div>
                @endif

                @if($materialRequest && $items->isNotEmpty())
                    @if($isWarehouseEditing)<form method="POST" action="{{ route('project-test.warehouse.mapping.save', $materialRequest) }}" id="emw9-stock-form" data-emw9-stock-form>@csrf
                    @endif
                    <div class="emw9-table-wrap">
                        <table class="emw9-table">
                            <thead><tr><th>Vật tư</th><th>SL / ĐVT</th><th>Thông số</th><th>Sản phẩm / Kho</th><th>Tồn khả dụng</th>@if($canViewCost)<th>Giá vốn</th>@endif<th>Kết quả / ghi chú</th></tr></thead>
                            <tbody>
                                @foreach($items as $item)
                                    @php
                                        $allocation = collect($item->allocations ?? [])->first();
                                        $rowStatus = (string) ($allocation?->status ?? '');
                                        $rowMeta = $rowStatusMeta[$rowStatus] ?? $rowStatusMeta[''];
                                        $matchedProduct = $allocation?->product ?: $item->product;
                                        $matchedProductId = (int) ($allocation?->product_id ?: $item->product_id);
                                        $selectedProductId = (int) old("items.{$item->id}.product_id", $matchedProductId);
                                        $selectedWarehouseId = (int) old("items.{$item->id}.warehouse_id", (int) ($allocation?->warehouse_id ?? 0));
                                        $selectedStatus = (string) old("items.{$item->id}.check_status", $rowStatus);
                                        $selectedNote = (string) old("items.{$item->id}.note", (string) ($allocation?->note ?? ''));
                                        $selectedProduct = $productsById->get($selectedProductId) ?: $matchedProduct;
                                        $selectedWarehouse = $warehousesById->get($selectedWarehouseId) ?: $allocation?->warehouse;
                                        $liveStock = $allocation?->live_stock_qty ?? null;
                                        $liveAvailable = $allocation?->live_available_qty ?? $allocation?->available_snapshot;
                                        $unitCost = (float) ($allocation?->live_unit_cost ?? $allocation?->unit_cost ?? 0);
                                        $lineCost = $unitCost * (float) ($allocation?->allocated_quantity ?: $item->quantity);
                                    @endphp
                                    <tr data-emw9-stock-row data-required="{{ (float)$item->quantity }}">
                                        <td><strong>{{ $item->item_name }}</strong>@if($matchedProduct?->sku)<small>SKU {{ $matchedProduct->sku }}</small>@endif</td>
                                        <td><strong>{{ rtrim(rtrim(number_format((float)$item->quantity,3,'.',''),'0'),'.') }} {{ $item->unit }}</strong><input type="hidden" name="items[{{ $item->id }}][quantity]" value="{{ (float)$item->quantity }}"></td>
                                        <td><span>{{ $item->note ?: '—' }}</span></td>
                                        <td class="emw9-map-cell">
                                            @if($isWarehouseEditing)
                                                <select class="pt-select" name="items[{{ $item->id }}][product_id]" required data-emw9-product-select data-url="{{ route('project-test.warehouse.products', $materialRequest) }}" data-selected="{{ $selectedProductId ?: '' }}"><option value="">Chọn sản phẩm / SKU</option>@if($selectedProductId)<option value="{{ $selectedProductId }}" selected>{{ $selectedProduct ? data_get($selectedProduct, 'name') : 'Sản phẩm #'.$selectedProductId }} · {{ $selectedProduct ? (data_get($selectedProduct, 'sku') ?: '—') : 'đã chọn' }}</option>@endif</select>
                                                <select class="pt-select" name="items[{{ $item->id }}][warehouse_id]" required data-emw9-warehouse-select data-url="{{ route('project-test.warehouse.warehouses', $materialRequest) }}" data-selected="{{ $selectedWarehouseId ?: '' }}"><option value="">Chọn kho cấp</option>@if($selectedWarehouseId)<option value="{{ $selectedWarehouseId }}" selected>{{ $selectedWarehouse ? data_get($selectedWarehouse, 'name') : 'Kho #'.$selectedWarehouseId }}</option>@endif</select>
                                            @else
                                                <strong>{{ $matchedProduct?->name ?: 'Chưa ghép sản phẩm' }}</strong><small>{{ $allocation?->warehouse?->name ?: 'Chưa chọn kho' }}</small>
                                            @endif
                                        </td>
                                        <td><strong data-emw9-live-stock>{{ $liveAvailable !== null ? rtrim(rtrim(number_format((float)$liveAvailable,3,'.',''),'0'),'.') : '—' }}</strong><small>
    {{
        $canViewLiveStock && $liveStock !== null
            ? 'Tồn thực '.rtrim(
                rtrim(
                    number_format((float) $liveStock, 3, '.', ''),
                    '0'
                ),
                '.'
            )
            : $item->unit
    }}
</small></td>
                                        @if($canViewCost)<td><strong data-emw9-unit-cost>{{ $unitCost > 0 ? number_format($unitCost,0,',','.') .' đ' : '—' }}</strong><small data-emw9-line-cost>{{ $lineCost > 0 ? 'Thành tiền '.number_format($lineCost,0,',','.') .' đ' : 'Chưa có giá lô' }}</small></td>@endif
                                        <td>
                                            @if($isWarehouseEditing)
                                                <select class="pt-select" name="items[{{ $item->id }}][check_status]" required data-emw9-status-select>
                                                    <option value="" @selected($selectedStatus==='')>Chọn kết quả đối chiếu</option>
                                                    <option value="ready" @selected($selectedStatus==='ready')>Đủ hàng</option>
                                                    <option value="shortage" @selected($selectedStatus==='shortage')>Không đủ / không có hàng</option>
                                                    <option value="transfer" @selected($selectedStatus==='transfer')>Cần điều chuyển</option>
                                                    <option value="waiting_purchase" @selected($selectedStatus==='waiting_purchase')>Chờ nhập</option>
                                                </select>
                                                <input class="pt-input" name="items[{{ $item->id }}][note]" value="{{ $selectedNote }}" placeholder="Bắt buộc ghi khi thiếu, chờ nhập hoặc điều chuyển" data-emw12-warehouse-note>
                                            @else
                                                <span class="emw9-status {{ $rowMeta[1] }}"><i class="bi {{ $rowMeta[2] }}"></i>{{ $rowMeta[0] }}</span>@if($allocation?->note)<small>{{ $allocation->note }}</small>@endif
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($isWarehouseEditing)<label class="emw9-warehouse-note"><span>Ghi chú chung của Kho</span><textarea class="pt-textarea" name="warehouse_note" maxlength="500" placeholder="Phương án điều chuyển, thời gian nhập dự kiến...">{{ $materialRequest->issue_note }}</textarea></label></form>@endif

                    <div class="emw9-summary"><span><small>Đã kiểm tra</small><b>{{ $checkedRows }}/{{ $totalRows }}</b></span><span><small>Đủ hàng</small><b>{{ $readyRows }}</b></span><span><small>Thiếu hàng</small><b>{{ $shortageRows }}</b></span>@if($canViewCost)<span class="is-cost"><small>Tổng giá vốn</small><b>{{ number_format($totalCost,0,',','.') }} đ</b></span>@endif</div>

                    @if($isWarehouseEditing)
                        <div class="emw9-actionbar">
                            <span>
                                {{ $requestStatus === 'approved'
                                    ? 'Phiếu đã được duyệt. Kho cập nhật lại sản phẩm/kho cấp và tồn thực tế sau điều hàng hoặc nhập bổ sung.'
                                    : 'Kho ghép sản phẩm/SKU thật, chọn kho cấp, kiểm tra tồn và ghi chú rõ các dòng thiếu, điều chuyển hoặc chờ nhập.'
                                }}
                            </span>
                            <div>
                                <button class="pt-btn pt-btn--soft" type="submit" form="emw9-stock-form">
                                    <i class="bi bi-floppy"></i>
                                    {{ $requestStatus === 'approved' ? 'Cập nhật tồn sau bổ sung' : 'Lưu đối chiếu' }}
                                </button>
                                @if($requestStatus === 'warehouse_check')
                                    <form method="POST" action="{{ route('project-test.warehouse.technical.return', $materialRequest) }}" class="emw9-inline-form" data-emw9-return-tech-form>
                                        @csrf
                                        <input type="hidden" name="feedback_note">
                                        <button class="pt-btn pt-btn--soft" type="submit"><i class="bi bi-reply"></i> Trả Kỹ thuật</button>
                                    </form>
                                    <button class="pt-btn pt-btn--brand"
                                            type="submit"
                                            form="emw9-stock-form"
                                            formaction="{{ route('project-test.warehouse.manager.submit', $materialRequest) }}"
                                            name="submit_for_manager"
                                            value="1">
                                        <i class="bi bi-send-check"></i> Lưu & gửi Admin duyệt
                                    </button>
                                @endif
                            </div>
                        </div>
                        @if($requestStatus === 'approved' && $materialRequest->warehouse_status === 'waiting_replenishment')
                            <div class="emw9-notice"><i class="bi bi-hourglass-split"></i> Admin đã duyệt phương án bổ sung hàng. Hệ thống tự lấy tồn mới từ module Kho; Kho cũng có thể nhập bổ sung nhanh ngay bên dưới.</div>

                            <section class="emw15-stock-tools" data-emw14-auto-sync data-url="{{ route('project-test.warehouse.stock.sync', $materialRequest) }}" data-token="{{ csrf_token() }}">
                                <header class="emw15-stock-head">
                                    <div class="emw15-stock-title">
                                        <span class="emw15-stock-icon"><i class="bi bi-box-seam"></i></span>
                                        <div>
                                            <strong>Xử lý hàng còn thiếu</strong>
                                            <span>Đồng bộ từ module Kho hoặc nhập bổ sung trực tiếp cho từng sản phẩm.</span>
                                        </div>
                                    </div>
                                    <form method="POST" action="{{ route('project-test.warehouse.stock.sync', $materialRequest) }}">
                                        @csrf
                                        <button class="pt-btn pt-btn--soft" type="submit"><i class="bi bi-arrow-repeat"></i> Đồng bộ tồn kho</button>
                                    </form>
                                </header>

                                <div class="emw15-shortage-list">
                                    @php $missingIndex = 0; @endphp
                                    @foreach($items as $stockItem)
                                        @php
                                            $stockAllocation = collect($stockItem->allocations ?? [])->first();
                                            $stockProduct = $stockAllocation?->product ?: $stockItem->product;
                                            $stockWarehouse = $stockAllocation?->warehouse;
                                            $stockNeeded = (float) ($stockAllocation?->allocated_quantity ?: $stockItem->quantity);
                                            $stockAvailable = (float) ($stockAllocation?->available_snapshot ?? 0);
                                            $stockMissingRaw = max(0, $stockNeeded - $stockAvailable);
                                            $stockMissing = max(1, (int) ceil($stockMissingRaw));
                                            $stockIsReady = (string) ($stockAllocation?->status ?? '') === 'ready';
                                            $stockStatus = (string) ($stockAllocation?->status ?? 'shortage');
                                            $stockStatusText = match($stockStatus) {
                                                'transfer' => 'Cần điều chuyển',
                                                'waiting_purchase' => 'Chờ nhập',
                                                default => 'Thiếu hàng',
                                            };
                                            $stockStatusClass = match($stockStatus) {
                                                'transfer' => 'is-transfer',
                                                'waiting_purchase' => 'is-waiting',
                                                default => 'is-shortage',
                                            };
                                            $stockPrice = $stockAllocation?->unit_cost
                                                ? rtrim(rtrim(number_format((float)$stockAllocation->unit_cost,4,'.',''),'0'),'.')
                                                : '0';
                                            $stockSerialized = (bool) ($stockProduct?->is_serialized ?? false);
                                        @endphp
                                        @if($stockAllocation && ! $stockIsReady)
                                            @php $missingIndex++; @endphp
                                            <div class="emw15-shortage-row">
                                                <div class="emw15-product-main">
                                                    <span class="emw15-row-index">{{ $missingIndex }}</span>
                                                    <div>
                                                        <strong>{{ $stockProduct?->name ?: $stockItem->item_name }}</strong>
                                                        <span>SKU {{ $stockProduct?->sku ?: '—' }} · {{ $stockWarehouse?->name ?: 'Chưa chọn kho' }}</span>
                                                    </div>
                                                </div>
                                                <div class="emw15-stock-metrics">
                                                    <span><small>Cần</small><b>{{ rtrim(rtrim(number_format($stockNeeded,3,'.',''),'0'),'.') }}</b></span>
                                                    <span><small>Tồn</small><b>{{ rtrim(rtrim(number_format($stockAvailable,3,'.',''),'0'),'.') }}</b></span>
                                                    <span class="is-missing"><small>Thiếu</small><b>{{ rtrim(rtrim(number_format($stockMissingRaw,3,'.',''),'0'),'.') }}</b></span>
                                                </div>
                                                <span class="emw15-state {{ $stockStatusClass }}">{{ $stockStatusText }}</span>
                                                <button type="button"
                                                        class="pt-btn pt-btn--brand emw15-open-receive"
                                                        data-emw15-open-receive
                                                        data-allocation-id="{{ $stockAllocation->id }}"
                                                        data-product="{{ $stockProduct?->name ?: $stockItem->item_name }}"
                                                        data-sku="{{ $stockProduct?->sku ?: '—' }}"
                                                        data-warehouse="{{ $stockWarehouse?->name ?: 'Chưa chọn kho' }}"
                                                        data-needed="{{ rtrim(rtrim(number_format($stockNeeded,3,'.',''),'0'),'.') }}"
                                                        data-available="{{ rtrim(rtrim(number_format($stockAvailable,3,'.',''),'0'),'.') }}"
                                                        data-missing="{{ $stockMissing }}"
                                                        data-unit-price="{{ $stockPrice }}"
                                                        data-serialized="{{ $stockSerialized ? '1' : '0' }}"
                                                        data-note="Bổ sung cho {{ $materialRequest->code }}">
                                                    <i class="bi bi-plus-circle"></i>
                                                    {{ $stockSerialized ? 'Nhập hàng & serial' : 'Nhập bổ sung' }}
                                                </button>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>

                                <footer class="emw15-stock-foot">
                                    <span data-emw14-sync-status><i class="bi bi-arrow-repeat"></i> Đang kiểm tra tồn mới nhất từ module Kho…</span>
                                    <small>Nhập tại đây sẽ tạo phiếu nhập, cộng tồn thật và tạo lô FIFO.</small>
                                </footer>
                            </section>

                            @php
                                $emw16CompanyId = (int) ($materialRequest?->project?->company_id ?? 0);
                                $emw16Suppliers = \Illuminate\Support\Facades\Schema::hasTable('product_suppliers')
                                    ? \Illuminate\Support\Facades\DB::table('product_suppliers')
                                        ->where('is_active', 1)
                                        ->when(
                                            $emw16CompanyId > 0 && \Illuminate\Support\Facades\Schema::hasColumn('product_suppliers', 'company_id'),
                                            fn ($query) => $query->where('company_id', $emw16CompanyId)
                                        )
                                        ->orderBy('name')
                                        ->get(['id', 'name', 'phone', 'tax_code'])
                                    : collect();
                            @endphp

                            <div class="emw15-modal" data-emw15-modal hidden>
                                <button type="button" class="emw15-modal-backdrop" data-emw15-close aria-label="Đóng"></button>
                                <div class="emw15-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="emw15-modal-title">
                                    <form method="POST"
                                          action="{{ route('project-test.warehouse.stock.quick-receive', $materialRequest) }}"
                                          data-emw15-receive-form
                                          data-confirm="Xác nhận tạo phiếu nhập đầy đủ, ghi nhận thanh toán/công nợ và cộng tồn kho thật?">
                                        @csrf
                                        <input type="hidden" name="allocation_id" data-emw15-allocation-id>

                                        <header class="emw15-modal-head">
                                            <div>
                                                <span class="emw15-modal-kicker">PHIẾU NHẬP KHO ĐẦY ĐỦ</span>
                                                <h3 id="emw15-modal-title" data-emw15-product-title>Sản phẩm</h3>
                                                <p data-emw15-product-meta>SKU · Kho</p>
                                            </div>
                                            <button type="button" class="emw15-modal-close" data-emw15-close><i class="bi bi-x-lg"></i></button>
                                        </header>

                                        <div class="emw15-modal-body">
                                            <div class="emw15-modal-stats">
                                                <span><small>Số lượng cần</small><b data-emw15-needed>0</b></span>
                                                <span><small>Tồn hiện tại</small><b data-emw15-available>0</b></span>
                                                <span class="is-missing"><small>Cần nhập thêm</small><b data-emw15-missing>0</b></span>
                                            </div>

                                            <section class="emw16-section">
                                                <div class="emw16-section-title"><i class="bi bi-building"></i><div><strong>Nhà cung cấp & chứng từ</strong><span>Thông tin bắt buộc của phiếu nhập kho.</span></div></div>
                                                <div class="emw15-form-grid emw16-grid-3">
                                                    <label class="is-wide">
                                                        <span>Nhà cung cấp <b>*</b></span>
                                                        <select class="pt-select" name="supplier_id" required data-emw16-supplier>
                                                            <option value="">Chọn nhà cung cấp</option>
                                                            @foreach($emw16Suppliers as $supplier)
                                                                <option value="{{ $supplier->id }}">{{ $supplier->name }}{{ $supplier->tax_code ? ' · MST '.$supplier->tax_code : '' }}</option>
                                                            @endforeach
                                                        </select>
                                                        @if($emw16Suppliers->isEmpty())<small class="emw16-field-help is-error">Chưa có nhà cung cấp hoạt động cho công ty này. Hãy tạo ở module Nhập kho trước.</small>@endif
                                                    </label>
                                                    <label><span>Ngày hóa đơn/nhập <b>*</b></span><input class="pt-input" type="date" name="invoice_date" value="{{ old('invoice_date', now()->toDateString()) }}" required data-emw16-invoice-date></label>
                                                    <label><span>Số hóa đơn</span><input class="pt-input" name="invoice_no" maxlength="120" value="{{ old('invoice_no') }}" placeholder="VD: HD001 / VAT001" data-emw16-invoice-no></label>
                                                </div>
                                            </section>

                                            <section class="emw16-section">
                                                <div class="emw16-section-title"><i class="bi bi-box-seam"></i><div><strong>Hàng nhập & giá vốn</strong><span>Tạo lô FIFO và cộng tồn kho thật.</span></div></div>
                                                <div class="emw15-form-grid emw16-grid-3">
                                                    <label><span>Số lượng nhập <b>*</b></span><input class="pt-input" type="number" name="quantity" min="1" step="1" required data-emw15-quantity data-emw16-calc></label>
                                                    <label><span>Đơn giá chưa VAT <b>*</b></span><input class="pt-input" type="number" name="unit_price" min="0" step="0.0001" value="0" required data-emw15-unit-price data-emw16-calc></label>
                                                    <label><span>VAT (%)</span><input class="pt-input" type="number" name="vat_percent" min="0" max="100" step="0.01" value="0" data-emw16-vat data-emw16-calc></label>
                                                </div>
                                            </section>

                                            <section class="emw16-section">
                                                <div class="emw16-section-title"><i class="bi bi-credit-card"></i><div><strong>Thông tin thanh toán</strong><span>Ghi nhận đã trả và công nợ còn lại ngay trên phiếu nhập.</span></div></div>
                                                <div class="emw15-form-grid emw16-grid-3">
                                                    <label><span>Trạng thái thanh toán <b>*</b></span>
                                                        <select class="pt-select" name="payment_status" required data-emw16-payment-status>
                                                            <option value="unpaid">Chưa thanh toán</option>
                                                            <option value="partial">Thanh toán một phần</option>
                                                            <option value="paid">Đã thanh toán đủ</option>
                                                        </select>
                                                    </label>
                                                    <label><span>Số tiền đã thanh toán</span><input class="pt-input" type="number" name="paid_amount" min="0" step="0.0001" value="0" data-emw16-paid-amount data-emw16-calc></label>
                                                    <label><span>Ngày dự kiến thanh toán</span><input class="pt-input" type="date" name="payment_due_date" value="{{ old('payment_due_date') }}" data-emw16-due-date></label>
                                                </div>
                                                <div class="emw16-pay-summary">
                                                    <span><small>Tổng giá trị phiếu</small><b data-emw16-total>0 đ</b></span>
                                                    <span><small>Đã thanh toán</small><b data-emw16-paid>0 đ</b></span>
                                                    <span class="is-debt"><small>Công nợ còn lại</small><b data-emw16-debt>0 đ</b></span>
                                                </div>
                                            </section>

                                            <section class="emw16-section is-last">
                                                <div class="emw15-form-grid">
                                                    <label class="is-wide"><span>Ghi chú phiếu nhập</span><input class="pt-input" name="note" maxlength="1000" data-emw15-note></label>
                                                    <label class="is-wide emw15-serial-field" data-emw15-serial-field hidden>
                                                        <span>Danh sách serial <b>*</b> <small>Mỗi serial một dòng</small></span>
                                                        <textarea class="pt-textarea" name="serial_codes" rows="5" data-emw15-serials></textarea>
                                                    </label>
                                                </div>
                                            </section>
                                        </div>

                                        <footer class="emw15-modal-actions">
                                            <button type="button" class="pt-btn pt-btn--soft" data-emw15-close>Hủy</button>
                                            <button type="submit" class="pt-btn pt-btn--brand" @disabled($emw16Suppliers->isEmpty())><i class="bi bi-box-arrow-in-down"></i> Lưu phiếu & nhập kho</button>
                                        </footer>
                                    </form>
                                </div>
                            </div>
                        @elseif(! $allRowsReady)
                            <div class="emw9-notice"><i class="bi bi-exclamation-triangle"></i> Thiếu hàng, cần điều chuyển hoặc chờ nhập vẫn được gửi Admin duyệt. Bắt buộc ghi chú từng dòng; chỉ được xuất khi tồn thực tế đã đủ.</div>
                        @endif
                    @elseif($canManagerReview)
                        <form method="POST" action="{{ route('project-test.materials.review', [$project, $materialRequest]) }}" class="emw9-manager-review" data-emw9-manager-form>@csrf
                            <div><strong>Quyết định của Admin / Quản lý</strong><span>Được phép duyệt khi còn thiếu hàng. Nếu thiếu, bắt buộc ghi rõ điều từ đâu, mua/nhập từ nguồn nào và thời gian dự kiến. Kho chỉ xuất sau khi nhập đủ tồn thực tế.</span></div>
                            @if($materialRequest->issue_note)
                                <div class="emw9-notice is-good"><i class="bi bi-chat-left-text"></i><strong>Ghi chú chung của Kho:</strong>&nbsp;{{ $materialRequest->issue_note }}</div>
                            @endif
                            <textarea class="pt-textarea" name="review_note" placeholder="Nếu thiếu hàng: ghi kho/chi nhánh điều về, nguồn mua, số lượng và ngày dự kiến nhập...">{{ old('review_note') }}</textarea>
                            <div><button class="pt-btn pt-btn--brand" name="decision" value="approve"><i class="bi bi-shield-check"></i> Duyệt phương án cấp hàng</button><button class="pt-btn pt-btn--soft" name="decision" value="return_warehouse">Trả Kho</button><button class="pt-btn pt-btn--soft" name="decision" value="return_technical">Trả Kỹ thuật</button></div>
                        </form>
                    @else
                        <div class="emw9-notice is-good"><i class="bi bi-info-circle"></i> {{ $requestStatusLabel }}</div>
                    @endif
                @endif
            @endif
        </article>
    @else
        <article class="pt-card emw9-empty"><i class="bi bi-list-check"></i><div><h3>Chưa có đề nghị cấp vật tư</h3><p>Kỹ thuật nhập danh sách theo hợp đồng và gửi Kho đối chiếu.</p></div></article>
    @endif
</section>


<style>
.emw15-stock-tools{margin-top:12px;border:1px solid #d9e4f1;border-radius:14px;background:#fff;overflow:hidden}.emw15-stock-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 14px;border-bottom:1px solid #e7edf5;background:#f8fafc}.emw15-stock-title{display:flex;align-items:center;gap:10px;min-width:0}.emw15-stock-icon{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#e8f7f2;color:#07966f;font-size:16px;flex:0 0 auto}.emw15-stock-title>div{display:flex;flex-direction:column;gap:2px;min-width:0}.emw15-stock-title strong{font-size:13px;color:#172b4d}.emw15-stock-title span{font-size:11px;color:#6b7d91}.emw15-shortage-list{display:grid}.emw15-shortage-row{display:grid;grid-template-columns:minmax(260px,1.6fr) minmax(210px,.9fr) auto auto;gap:12px;align-items:center;padding:11px 14px;border-bottom:1px solid #edf1f6}.emw15-shortage-row:last-child{border-bottom:0}.emw15-shortage-row:hover{background:#fbfcfe}.emw15-product-main{display:flex;align-items:center;gap:9px;min-width:0}.emw15-row-index{display:grid;place-items:center;width:25px;height:25px;border-radius:8px;background:#f0f4f8;color:#60738a;font-size:11px;font-weight:800;flex:0 0 auto}.emw15-product-main>div{display:flex;flex-direction:column;gap:2px;min-width:0}.emw15-product-main strong{font-size:12px;color:#15253e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.emw15-product-main span{font-size:10.5px;color:#718298;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.emw15-stock-metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:6px}.emw15-stock-metrics span{display:flex;align-items:baseline;justify-content:space-between;gap:5px;padding:6px 8px;border:1px solid #e3e9f1;border-radius:8px;background:#fff}.emw15-stock-metrics small{font-size:9px;color:#8795a7;text-transform:uppercase;font-weight:700}.emw15-stock-metrics b{font-size:12px;color:#24364f}.emw15-stock-metrics .is-missing{border-color:#fed7aa;background:#fff8ed}.emw15-stock-metrics .is-missing b{color:#c26405}.emw15-state{display:inline-flex;align-items:center;justify-content:center;min-width:92px;padding:6px 9px;border-radius:999px;font-size:10px;font-weight:800;white-space:nowrap}.emw15-state.is-shortage{background:#fff1f0;color:#c2413b}.emw15-state.is-transfer{background:#edf6ff;color:#2167a9}.emw15-state.is-waiting{background:#fff7e6;color:#a86600}.emw15-open-receive{white-space:nowrap}.emw15-stock-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:9px 14px;background:#fbfcfe;border-top:1px solid #edf1f6}.emw15-stock-foot span{font-size:10.5px;color:#64788f}.emw15-stock-foot span.is-ok{color:#138a64}.emw15-stock-foot span.is-error{color:#c2413b}.emw15-stock-foot small{font-size:10px;color:#8a98a9}.emw15-modal[hidden]{display:none!important}.emw15-modal{position:fixed;inset:0;z-index:1085;display:grid;place-items:center;padding:18px}.emw15-modal-backdrop{position:absolute;inset:0;border:0;background:rgba(18,32,51,.48);backdrop-filter:blur(2px);cursor:default}.emw15-modal-dialog{position:relative;width:min(860px,100%);max-height:calc(100vh - 36px);overflow:auto;border-radius:16px;background:#fff;box-shadow:0 24px 70px rgba(11,28,51,.28)}.emw15-modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:18px 20px 14px;border-bottom:1px solid #e7edf4}.emw15-modal-kicker{font-size:9px;font-weight:900;letter-spacing:.08em;color:#07966f}.emw15-modal-head h3{margin:4px 0 2px;font-size:18px;color:#14243d}.emw15-modal-head p{margin:0;font-size:11px;color:#718196}.emw15-modal-close{display:grid;place-items:center;width:32px;height:32px;border:1px solid #e1e7ef;border-radius:9px;background:#fff;color:#65768b}.emw15-modal-body{padding:16px 20px 18px}.emw15-modal-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:15px}.emw15-modal-stats span{display:flex;flex-direction:column;gap:3px;padding:10px 12px;border:1px solid #e4eaf2;border-radius:10px;background:#f9fbfd}.emw15-modal-stats small{font-size:9px;color:#7b8b9e;text-transform:uppercase;font-weight:800}.emw15-modal-stats b{font-size:17px;color:#1f334f}.emw15-modal-stats .is-missing{border-color:#fed7aa;background:#fff8ed}.emw15-modal-stats .is-missing b{color:#c26405}.emw15-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.emw15-form-grid label{display:flex;flex-direction:column;gap:5px}.emw15-form-grid label>span{font-size:10px;font-weight:800;color:#5e7087;text-transform:uppercase}.emw15-form-grid label>span b{color:#dc3f37}.emw15-form-grid label>span small{font-weight:500;text-transform:none;color:#8a98aa}.emw15-form-grid .is-wide{grid-column:1/-1}.emw15-modal-actions{display:flex;justify-content:flex-end;gap:8px;padding:13px 20px;border-top:1px solid #e7edf4;background:#f8fafc}.emw16-grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}.emw16-grid-3 .is-wide{grid-column:1/-1}.emw16-section{padding:14px 0;border-bottom:1px solid #edf1f6}.emw16-section:first-of-type{padding-top:0}.emw16-section.is-last{border-bottom:0;padding-bottom:0}.emw16-section-title{display:flex;align-items:center;gap:9px;margin-bottom:11px}.emw16-section-title>i{display:grid;place-items:center;width:30px;height:30px;border-radius:9px;background:#edf7f4;color:#078a69}.emw16-section-title>div{display:flex;flex-direction:column;gap:1px}.emw16-section-title strong{font-size:12px;color:#20344f}.emw16-section-title span{font-size:10px;color:#7b8a9d}.emw16-field-help{font-size:10px;color:#7e8d9f}.emw16-field-help.is-error{color:#c2413b}.emw16-pay-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px}.emw16-pay-summary span{display:flex;flex-direction:column;gap:3px;padding:10px 12px;border:1px solid #dfe7f0;border-radius:10px;background:#f9fbfd}.emw16-pay-summary small{font-size:9px;color:#7d8da0;text-transform:uppercase;font-weight:800}.emw16-pay-summary b{font-size:15px;color:#15304d}.emw16-pay-summary .is-debt{border-color:#fecaca;background:#fff7f7}.emw16-pay-summary .is-debt b{color:#c23a35}.emw15-modal-open{overflow:hidden}@media(max-width:1150px){.emw15-shortage-row{grid-template-columns:minmax(220px,1.4fr) minmax(190px,1fr) auto}.emw15-open-receive{grid-column:3}.emw15-state{display:none}}@media(max-width:760px){.emw16-grid-3{grid-template-columns:1fr}.emw16-pay-summary{grid-template-columns:1fr}.emw15-stock-head,.emw15-stock-foot{align-items:flex-start;flex-direction:column}.emw15-shortage-row{grid-template-columns:1fr}.emw15-stock-metrics{width:100%}.emw15-state{display:inline-flex;justify-self:start}.emw15-open-receive{grid-column:auto;width:100%}.emw15-modal{padding:8px}.emw15-modal-dialog{max-height:calc(100vh - 16px);border-radius:13px}.emw15-modal-head,.emw15-modal-body,.emw15-modal-actions{padding-left:14px;padding-right:14px}.emw15-form-grid{grid-template-columns:1fr}.emw15-form-grid .is-wide{grid-column:auto}}
</style>

@if($requestStatus === 'approved' && $materialRequest?->warehouse_status === 'waiting_replenishment' && $isWarehouseUser)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const box = document.querySelector('[data-emw14-auto-sync]');
    if (!box) return;
    const status = box.querySelector('[data-emw14-sync-status]');

    const modal = document.querySelector('[data-emw15-modal]');
    const receiveForm = modal ? modal.querySelector('[data-emw15-receive-form]') : null;
    const money = value => new Intl.NumberFormat('vi-VN', {maximumFractionDigits: 0}).format(Math.max(0, Number(value) || 0)) + ' đ';
    const receiptTotal = () => {
        if (!receiveForm) return 0;
        const qty = Number(receiveForm.querySelector('[data-emw15-quantity]')?.value || 0);
        const price = Number(receiveForm.querySelector('[data-emw15-unit-price]')?.value || 0);
        const vat = Number(receiveForm.querySelector('[data-emw16-vat]')?.value || 0);
        return Math.max(0, qty * price * (1 + vat / 100));
    };
    const recalcPayment = (statusChanged = false) => {
        if (!receiveForm) return;
        const total = receiptTotal();
        const status = receiveForm.querySelector('[data-emw16-payment-status]');
        const paidInput = receiveForm.querySelector('[data-emw16-paid-amount]');
        if (!status || !paidInput) return;
        if (status.value === 'unpaid') paidInput.value = '0';
        if (status.value === 'paid') paidInput.value = String(total);
        if (status.value === 'partial' && statusChanged && Number(paidInput.value || 0) >= total) paidInput.value = '0';
        const paid = Math.min(total, Math.max(0, Number(paidInput.value || 0)));
        const debt = Math.max(0, total - paid);
        const totalNode = receiveForm.querySelector('[data-emw16-total]');
        const paidNode = receiveForm.querySelector('[data-emw16-paid]');
        const debtNode = receiveForm.querySelector('[data-emw16-debt]');
        if (totalNode) totalNode.textContent = money(total);
        if (paidNode) paidNode.textContent = money(paid);
        if (debtNode) debtNode.textContent = money(debt);
        paidInput.readOnly = status.value !== 'partial';
    };
    const closeModal = () => {
        if (!modal) return;
        modal.hidden = true;
        document.body.classList.remove('emw15-modal-open');
    };
    const openModal = (button) => {
        if (!modal || !receiveForm) return;
        receiveForm.querySelector('[data-emw15-allocation-id]').value = button.dataset.allocationId || '';
        receiveForm.querySelector('[data-emw15-product-title]').textContent = button.dataset.product || 'Sản phẩm';
        receiveForm.querySelector('[data-emw15-product-meta]').textContent = 'SKU ' + (button.dataset.sku || '—') + ' · ' + (button.dataset.warehouse || 'Chưa chọn kho');
        receiveForm.querySelector('[data-emw15-needed]').textContent = button.dataset.needed || '0';
        receiveForm.querySelector('[data-emw15-available]').textContent = button.dataset.available || '0';
        receiveForm.querySelector('[data-emw15-missing]').textContent = button.dataset.missing || '0';
        receiveForm.querySelector('[data-emw15-quantity]').value = button.dataset.missing || '1';
        receiveForm.querySelector('[data-emw15-unit-price]').value = button.dataset.unitPrice || '0';
        receiveForm.querySelector('[data-emw15-note]').value = button.dataset.note || '';
        const paymentStatus = receiveForm.querySelector('[data-emw16-payment-status]');
        const paidAmount = receiveForm.querySelector('[data-emw16-paid-amount]');
        if (paymentStatus) paymentStatus.value = 'unpaid';
        if (paidAmount) paidAmount.value = '0';

        const serialField = receiveForm.querySelector('[data-emw15-serial-field]');
        const serialInput = receiveForm.querySelector('[data-emw15-serials]');
        const serialized = button.dataset.serialized === '1';
        serialField.hidden = !serialized;
        serialInput.required = serialized;
        serialInput.value = '';
        serialInput.placeholder = serialized ? 'Nhập đủ ' + (button.dataset.missing || '1') + ' serial, mỗi mã một dòng' : '';

        modal.hidden = false;
        document.body.classList.add('emw15-modal-open');
        recalcPayment();
        setTimeout(() => receiveForm.querySelector('[data-emw15-quantity]').focus(), 30);
    };

    document.querySelectorAll('[data-emw15-open-receive]').forEach(button => {
        button.addEventListener('click', () => openModal(button));
    });
    if (receiveForm) {
        receiveForm.querySelectorAll('[data-emw16-calc]').forEach(input => input.addEventListener('input', () => recalcPayment()));
        receiveForm.querySelector('[data-emw16-payment-status]')?.addEventListener('change', () => recalcPayment(true));
    }

    @php
        $failedFormData = [
            'allocation_id' => old('allocation_id'),
            'supplier_id' => old('supplier_id'),
            'invoice_no' => old('invoice_no'),
            'invoice_date' => old('invoice_date'),
            'quantity' => old('quantity'),
            'unit_price' => old('unit_price'),
            'vat_percent' => old('vat_percent'),
            'payment_status' => old('payment_status'),
            'paid_amount' => old('paid_amount'),
            'payment_due_date' => old('payment_due_date'),
            'note' => old('note'),
            'serial_codes' => old('serial_codes'),
        ];
    @endphp

    const failedForm = {{ \Illuminate\Support\Js::from($failedFormData) }};
    if (failedForm.allocation_id) {
        const failedButton = document.querySelector('[data-emw15-open-receive][data-allocation-id="' + failedForm.allocation_id + '"]');
        if (failedButton) {
            openModal(failedButton);
            Object.entries({
                supplier_id: failedForm.supplier_id,
                invoice_no: failedForm.invoice_no,
                invoice_date: failedForm.invoice_date,
                quantity: failedForm.quantity,
                unit_price: failedForm.unit_price,
                vat_percent: failedForm.vat_percent,
                payment_status: failedForm.payment_status,
                paid_amount: failedForm.paid_amount,
                payment_due_date: failedForm.payment_due_date,
                note: failedForm.note,
                serial_codes: failedForm.serial_codes,
            }).forEach(([name, value]) => {
                if (value === null || value === undefined || value === '') return;
                const field = receiveForm?.querySelector('[name="' + name + '"]');
                if (field) field.value = value;
            });
            recalcPayment();
        }
    }

    document.querySelectorAll('[data-emw15-close]').forEach(button => {
        button.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && modal && !modal.hidden) closeModal();
    });
    const key = 'emw14-stock-sync-{{ (int)$materialRequest->id }}-' + Math.floor(Date.now() / 30000);
    if (sessionStorage.getItem(key)) {
        if (status) status.textContent = 'Tồn kho đã được đồng bộ trong lần tải này.';
        return;
    }
    sessionStorage.setItem(key, '1');
    fetch(box.dataset.url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'X-CSRF-TOKEN': box.dataset.token,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    }).then(async response => {
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'Không thể đồng bộ tồn kho.');
        if (status) {
            status.textContent = payload.message || 'Đã đồng bộ tồn kho.';
            status.classList.add('is-ok');
        }
        if (payload.data && payload.data.changed_rows > 0) {
            setTimeout(() => window.location.reload(), 600);
        }
    }).catch(error => {
        if (status) {
            status.textContent = error.message;
            status.classList.add('is-error');
        }
    });
});
</script>
@endif
