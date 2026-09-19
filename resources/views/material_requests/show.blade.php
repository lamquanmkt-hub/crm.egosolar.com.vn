@extends('layouts.app')
@section('title', 'Yêu cầu vật tư #'.$mr->id)
@push('styles')
<link rel="stylesheet" href="{{ asset('css/ego-material-workspace.css') }}?v={{ file_exists(public_path('css/ego-material-workspace.css')) ? filemtime(public_path('css/ego-material-workspace.css')) : '3' }}">
<link rel="stylesheet" href="{{ asset('css/ego-material-dispatch-tabs.css') }}?v={{ file_exists(public_path('css/ego-material-dispatch-tabs.css')) ? filemtime(public_path('css/ego-material-dispatch-tabs.css')) : '5' }}">
@endpush
@section('content')
@php
    use App\Enums\MaterialRequestStatus;
    $status = (string) $mr->status;
    $user = auth()->user();
    $isAdmin = $user && (((int) ($user->is_admin ?? 0) === 1) || (method_exists($user, 'hasRole') && $user->hasRole('admin')));
    $isTechnical = $user && method_exists($user, 'hasAnyRole')
        ? $user->hasAnyRole(['technical', 'ky_thuat'])
        : ($user && method_exists($user, 'hasRole') && ($user->hasRole('technical') || $user->hasRole('ky_thuat')));
    $canEdit = ($isAdmin && in_array($status, [MaterialRequestStatus::DRAFT->value, MaterialRequestStatus::SUBMITTED->value, MaterialRequestStatus::ADMIN_APPROVED->value], true)) || ($isTechnical && $status === MaterialRequestStatus::DRAFT->value);
    $statusLabels = [MaterialRequestStatus::DRAFT->value => 'Nháp', MaterialRequestStatus::SUBMITTED->value => 'Chờ Admin duyệt', MaterialRequestStatus::ADMIN_APPROVED->value => 'Kho đang xử lý', MaterialRequestStatus::EXPORTED->value => 'Đã xuất kho', MaterialRequestStatus::REJECTED->value => 'Đã từ chối'];
    $statusTone = match ($status) { MaterialRequestStatus::SUBMITTED->value => 'amber', MaterialRequestStatus::ADMIN_APPROVED->value => 'blue', MaterialRequestStatus::EXPORTED->value => 'green', MaterialRequestStatus::REJECTED->value => 'red', default => 'neutral' };
    $fmtMoney = fn ($value) => number_format((float) ($value ?? 0), 0, ',', '.').' đ';
    $fmtQty = fn ($value) => rtrim(rtrim(number_format((float) ($value ?? 0), 2, ',', '.'), '0'), ',');
    $cleanRequest = function ($item, $proposal = null) { if (! empty($proposal?->requested_name)) { return ['name' => (string) $proposal->requested_name, 'spec' => (string) ($proposal->requested_spec ?? ''), 'unit' => (string) ($proposal->requested_unit ?? $item->unit ?? ''), 'qty' => (float) ($proposal->requested_qty ?? $item->qty ?? 0)]; } $note = preg_replace('/^\[[^\]]+\]\s*/u', '', (string) ($item->note ?? '')); $parts = array_map('trim', explode('|', $note)); return ['name' => $parts[0] ?: ($item->product->name ?? 'Vật tư chưa đặt tên'), 'spec' => implode(' · ', array_slice($parts, 1)), 'unit' => (string) ($item->unit ?? ''), 'qty' => (float) ($item->qty ?? 0)]; };
    $matchedCount = collect($mr->items)->filter(fn ($item) => ! empty($item->product_id))->count();
    $selectedWarehouseId = $canAllocate ? (int) ($dispatchWarehouseId ?? 0) : (int) ($mr->warehouse_id ?? 0);
    $flowStep = match ($status) { MaterialRequestStatus::DRAFT->value => 1, MaterialRequestStatus::SUBMITTED->value => 2, MaterialRequestStatus::ADMIN_APPROVED->value => 3, MaterialRequestStatus::EXPORTED->value => 4, default => 1 };
    $historyIcons = ['technical_requested' => 'bi-person-gear', 'created' => 'bi-plus-circle', 'submitted' => 'bi-send-check', 'admin_approved' => 'bi-shield-check', 'warehouse_allocated' => 'bi-box-seam', 'warehouse_exported' => 'bi-box-arrow-up-right', 'updated' => 'bi-pencil-square'];
@endphp
<main class="mrw-page mrw-detail">
    <header class="mrw-header"><div class="mrw-title-group"><a class="mrw-back" href="{{ route('material-requests.index') }}"><i class="bi bi-arrow-left"></i></a><div><span class="mrw-eyebrow">PHIẾU VT-{{ str_pad((string) $mr->id, 5, '0', STR_PAD_LEFT) }}</span><h1>{{ $mr->site->name ?? 'Yêu cầu vật tư' }}</h1></div></div><div class="mrw-header-badges"><span class="mrw-source {{ $requestSource['tone'] ?? 'teal' }}">{{ $requestSource['label'] }}</span><span class="mrw-status {{ $statusTone }}">{{ $statusLabels[$status] ?? $status }}</span></div></header>
    @if(session('success'))<div class="mrw-alert success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mrw-alert danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="mrw-alert danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <section class="mrw-meta-bar"><div><span>Người đề xuất</span><strong>{{ $requestSource['requester_name'] ?? '—' }}</strong></div><div><span>Công trình</span><strong>#{{ $mr->site_id }} · {{ $mr->site->name ?? '—' }}</strong></div><div><span>Ngày tạo</span><strong>{{ optional($mr->created_at)->format('d/m/Y H:i') }}</strong></div>@if($canViewCost)<div><span>Tổng giá vốn</span><strong class="mrw-money">{{ $fmtMoney($mr->total_cost ?? collect($mr->items)->sum('line_total')) }}</strong></div>@endif</section>

    <section class="mrw-flow">@foreach([1 => 'Kỹ thuật yêu cầu', 2 => 'Admin duyệt', 3 => 'Kho soạn hàng', 4 => 'Xuất kho · Trừ tồn'] as $step => $label)<div class="{{ $flowStep >= $step ? 'active' : '' }}"><i>{{ $flowStep > $step ? '✓' : $step }}</i><span>{{ $label }}</span></div>@endforeach</section>

    <section class="mrw-card mrw-work-card">
        <nav class="mrw-tabs"><button type="button" class="active" data-mrw-tab="materials">Vật tư <span>{{ collect($mr->items)->count() }}</span></button><button type="button" data-mrw-tab="history">Lịch sử <span>{{ collect($materialHistory)->count() }}</span></button>@if($status === MaterialRequestStatus::EXPORTED->value && $canViewCost)<button type="button" data-mrw-tab="dispatch"><i class="bi bi-receipt"></i> Phiếu xuất kho</button>@endif<div class="mrw-context-actions">@if($canEdit)<a href="{{ route('material-requests.edit', $mr) }}">Sửa phiếu</a>@endif @if($canViewCost && $status === MaterialRequestStatus::EXPORTED->value)<a class="mrw-doc-action pdf" href="{{ route('material-requests.dispatch.pdf', $mr) }}"><i class="bi bi-file-earmark-pdf"></i> PDF</a><a class="mrw-doc-action excel" href="{{ route('material-requests.dispatch.excel', $mr) }}"><i class="bi bi-file-earmark-excel"></i> Excel</a>@elseif($canViewCost)<a href="{{ route('material-requests.export.excel', $mr) }}">Excel</a>@endif</div></nav>

        <section data-mrw-panel="materials">
            @if($canAllocate)<form method="POST" action="{{ route('material-requests.warehouse-allocation', $mr) }}" id="warehouseAllocationForm">@csrf @endif
            <div class="mrw-material-toolbar"><label class="mrw-warehouse-picker"><span><i class="bi bi-shop"></i> Kho xuất</span>@if($canAllocate)<select name="warehouse_id" id="mrwWarehouseSelect" required>@forelse($dispatchWarehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($selectedWarehouseId === (int) $warehouse->id)>{{ $warehouse->name }}</option>@empty<option value="">Chưa cấu hình kho EGO_VN</option>@endforelse</select>@else<strong>{{ $warehouseNames->get((int) ($mr->warehouse_id ?? 0))->name ?? 'Chưa chọn kho' }}</strong>@endif</label><span class="mrw-stock-hint">Chọn sản phẩm trước để xem tồn từng kho.</span><label class="mrw-search mrw-line-search"><i class="bi bi-search"></i><input type="search" id="mrwLineFilter" placeholder="Tìm vật tư trong phiếu..."></label><span class="mrw-matched-count"><b data-matched-count>{{ $matchedCount }}</b>/{{ collect($mr->items)->count() }} đã ghép</span></div>

            <div class="mrw-table-scroll"><table class="mrw-table mrw-detail-table"><thead><tr><th>Yêu cầu kỹ thuật</th><th>Sản phẩm thực xuất</th><th>Số lượng</th>@if($canViewCost)<th>Giá vốn</th>@endif</tr></thead><tbody>
                @foreach(collect($mr->items)->values() as $index => $item)
                    @php $proposalLine = $proposalLines->get($index); $requested = $cleanRequest($item, $proposalLine); $warehouseId = (int) ($item->warehouse_id ?? $proposalLine?->selected_warehouse_id ?? $mr->warehouse_id ?? $dispatchWarehouseId ?? 0); $warehouse = $warehouseNames->get($warehouseId); $matched = ! empty($item->product_id); $productStocks = collect($inventoryByProduct->get((int) ($item->product_id ?? 0), [])); $currentStock = (float) $productStocks->where('warehouse_id', (string) $selectedWarehouseId)->sum('quantity'); $requiredStock = (float) ($requested['qty'] ?: $item->qty); $shortage = max(0, $requiredStock - $currentStock); @endphp
                    <tr data-allocation-row data-qty="{{ $requiredStock }}">
                        <td><strong class="mrw-request-name">{{ $requested['name'] }}</strong>@if($requested['spec'])<small>{{ $requested['spec'] }}</small>@endif</td>
                        <td>
                            <div class="mrw-product-choice {{ $matched ? 'matched' : 'waiting' }}" data-selected-product><i class="bi {{ $matched ? 'bi-check-circle-fill' : 'bi-dash-circle' }}"></i><div><strong data-selected-name>{{ $matched ? ($item->product->name ?? 'Sản phẩm #'.$item->product_id) : 'Chưa ghép sản phẩm' }}</strong><small data-selected-meta>{{ $matched ? 'SKU #'.$item->product_id.' · '.($warehouse->name ?? 'Kho #'.$warehouseId) : 'Tìm sản phẩm để xem tồn tại các kho' }}</small></div></div>
                            <div class="mrw-stock-breakdown" data-stock-breakdown>
                                @if($matched)
                                    @foreach($productStocks as $stockRow)
                                        <span class="{{ (int) $stockRow['warehouse_id'] === $selectedWarehouseId ? 'dispatch' : '' }}">{{ $warehouseNames->get((int) $stockRow['warehouse_id'])->name ?? 'Kho #'.$stockRow['warehouse_id'] }}: <b>{{ $fmtQty($stockRow['quantity']) }}</b></span>
                                    @endforeach
                                    @if($productStocks->isEmpty())
                                        <span>Chưa có tồn tại các kho</span>
                                    @endif
                                @endif
                            </div>
                            @if($canAllocate)
                                <input type="hidden" name="allocations[{{ $item->id }}][product_id]" value="{{ $item->product_id }}" data-product-id>
                                <input type="hidden" name="allocations[{{ $item->id }}][warehouse_id]" value="{{ $matched ? $selectedWarehouseId : '' }}" data-warehouse-id>
                                <div class="mrw-product-search"><i class="bi bi-search"></i><input type="search" data-product-search placeholder="Chọn sản phẩm, xem tồn các kho..." autocomplete="off"><button type="button" data-clear-selection title="Bỏ sản phẩm đã ghép"><i class="bi bi-x-lg"></i></button></div>
                                <div class="mrw-search-results" data-search-results hidden></div>
                            @endif
                        </td>
                        <td><strong class="mrw-quantity">{{ $fmtQty($requiredStock) }}</strong><small>{{ $requested['unit'] ?: $item->unit ?: 'đơn vị' }}</small>
                            <small class="mrw-live-stock {{ $matched && $shortage > 0 && $status !== MaterialRequestStatus::EXPORTED->value ? 'shortage' : '' }}" data-live-stock>
                                @if($matched && $status !== MaterialRequestStatus::EXPORTED->value)
                                    Tồn EGO_VN: {{ $fmtQty($currentStock) }}
                                    @if($shortage > 0)
                                        · Thiếu {{ $fmtQty($shortage) }}
                                    @endif
                                @endif
                            </small>
                        </td>
                        @if($canViewCost)<td><strong class="mrw-money" data-cost>{{ $matched ? $fmtMoney($item->unit_cost) : '—' }}</strong><small data-line-total>{{ $matched ? $fmtMoney($item->line_total) : '' }}</small></td>@endif
                    </tr>
                @endforeach
            </tbody></table></div>

            <div class="mrw-lines-pagination"><span id="mrwPageInfo"></span><div><button type="button" id="mrwPrevPage" class="mrw-btn icon"><i class="bi bi-chevron-left"></i></button><button type="button" id="mrwNextPage" class="mrw-btn icon"><i class="bi bi-chevron-right"></i></button></div></div>
            @if($canAllocate)<footer class="mrw-sticky-actions"><span><strong data-footer-matched>{{ $matchedCount }}</strong>/{{ collect($mr->items)->count() }} đã ghép · Thiếu tồn vẫn lưu được để chờ điều hàng.</span><div><button class="mrw-btn secondary" type="submit"><i class="bi bi-floppy"></i> Lưu / Chờ điều hàng</button><button class="mrw-btn primary" type="submit" formaction="{{ route('material-requests.warehouse-approve', $mr) }}" onclick="return confirm('Xác nhận xuất kho EGO_VN và trừ tồn cho phiếu #{{ $mr->id }}?')"><i class="bi bi-box-arrow-up-right"></i> Xuất kho &amp; trừ tồn</button></div></footer></form>@endif

            @if($status === MaterialRequestStatus::DRAFT->value)<form class="mrw-inline-action" method="POST" action="{{ route('material-requests.submit', $mr) }}">@csrf<button class="mrw-btn primary">Gửi Admin duyệt</button></form>@endif
            @if($status === MaterialRequestStatus::SUBMITTED->value && $isAdmin)<form class="mrw-inline-action" method="POST" action="{{ route('material-requests.admin-approve', $mr) }}">@csrf<button class="mrw-btn primary">Duyệt &amp; chuyển Kho</button></form>@endif
        </section>

        <section data-mrw-panel="history" hidden><div class="mrw-note-line"><strong>Ghi chú:</strong> {{ $mr->note ?: 'Không có ghi chú' }}</div><div class="mrw-history-list">@forelse($materialHistory as $history)@php $action = (string) ($history->action ?? 'updated'); $details = json_decode((string) ($history->details ?? ''), true); @endphp<article><i class="bi {{ $historyIcons[$action] ?? 'bi-clock-history' }}"></i><div><strong>{{ $history->note ?? 'Cập nhật yêu cầu vật tư' }}</strong><small>{{ $history->user_name ?? 'Hệ thống' }}@if(!empty($history->user_role)) · {{ $history->user_role }}@endif · {{ !empty($history->created_at) ? \Illuminate\Support\Carbon::parse($history->created_at)->format('H:i d/m/Y') : '—' }}</small>@if($canViewCost && is_array($details) && $details !== [])<details><summary>Xem chi tiết thay đổi</summary><pre>{{ json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>@endif</div></article>@empty<div class="mrw-empty">Chưa có lịch sử xử lý.</div>@endforelse</div></section>

        @if($status === MaterialRequestStatus::EXPORTED->value && $canViewCost)
            <section data-mrw-panel="dispatch" hidden>
                <div class="mrw-dispatch-preview">
                    <header><div><span>CHỨNG TỪ XUẤT KHO</span><h2>PXK-{{ str_pad((string) $mr->id, 5, '0', STR_PAD_LEFT) }}</h2></div><span class="mrw-status green"><i class="bi bi-check-circle"></i> Đã xuất và trừ tồn</span></header>
                    <div class="mrw-dispatch-meta"><div><span>Công trình</span><strong>{{ $mr->site->name ?? '—' }}</strong></div><div><span>Kho xuất</span><strong>{{ $warehouseNames->get((int) ($mr->warehouse_id ?? 0))->name ?? 'EGO_VN' }}</strong></div><div><span>Người đề xuất</span><strong>{{ $requestSource['requester_name'] ?? '—' }}</strong></div><div><span>Ngày xuất</span><strong>{{ optional($mr->updated_at ?? $mr->created_at)->format('d/m/Y H:i') }}</strong></div></div>
                    <div class="mrw-table-scroll"><table class="mrw-table mrw-dispatch-table"><thead><tr><th>STT</th><th>Sản phẩm thực xuất</th><th>SKU</th><th>Số lượng</th><th>Giá vốn</th><th>Thành tiền</th></tr></thead><tbody>
                        @foreach($mr->items as $dispatchIndex => $dispatchItem)
                            <tr><td>{{ $dispatchIndex + 1 }}</td><td><strong>{{ $dispatchItem->product->name ?? 'Sản phẩm #'.$dispatchItem->product_id }}</strong></td><td>{{ $dispatchItem->product->sku ?? '#'.$dispatchItem->product_id }}</td><td>{{ $fmtQty($dispatchItem->qty) }} {{ $dispatchItem->unit ?? '' }}</td><td>{{ $fmtMoney($dispatchItem->unit_cost) }}</td><td><strong class="mrw-money">{{ $fmtMoney($dispatchItem->line_total ?? ((float) $dispatchItem->qty * (float) $dispatchItem->unit_cost)) }}</strong></td></tr>
                        @endforeach
                    </tbody></table></div>
                    <footer><div><span>Tổng giá trị xuất kho</span><strong>{{ $fmtMoney($mr->total_cost ?? collect($mr->items)->sum('line_total')) }}</strong></div><div><a class="mrw-btn secondary" href="{{ route('material-requests.dispatch.pdf', $mr) }}"><i class="bi bi-file-earmark-pdf"></i> Tải PDF</a><a class="mrw-btn primary" href="{{ route('material-requests.dispatch.excel', $mr) }}"><i class="bi bi-file-earmark-excel"></i> Tải Excel</a></div></footer>
                </div>
            </section>
        @endif
    </section>
</main>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tabs = [...document.querySelectorAll('[data-mrw-tab]')];
    tabs.forEach(tab => tab.addEventListener('click', () => {
        tabs.forEach(item => item.classList.toggle('active', item === tab));
        document.querySelectorAll('[data-mrw-panel]').forEach(panel => panel.hidden = panel.dataset.mrwPanel !== tab.dataset.mrwTab);
    }));

    const rows = [...document.querySelectorAll('[data-allocation-row]')];
    const filter = document.getElementById('mrwLineFilter');
    const pageInfo = document.getElementById('mrwPageInfo');
    const previous = document.getElementById('mrwPrevPage');
    const next = document.getElementById('mrwNextPage');
    const perPage = 15;
    let page = 1;

    function renderPage() {
        const query = (filter?.value || '').trim().toLocaleLowerCase('vi');
        const matching = rows.filter(row => !query || row.textContent.toLocaleLowerCase('vi').includes(query));
        const pages = Math.max(1, Math.ceil(matching.length / perPage));
        page = Math.min(page, pages);
        rows.forEach(row => row.hidden = true);
        matching.slice((page - 1) * perPage, page * perPage).forEach(row => row.hidden = false);
        pageInfo.textContent = matching.length ? `${(page - 1) * perPage + 1}–${Math.min(page * perPage, matching.length)} / ${matching.length} vật tư` : 'Không tìm thấy vật tư';
        previous.disabled = page <= 1;
        next.disabled = page >= pages;
    }

    filter?.addEventListener('input', () => { page = 1; renderPage(); });
    previous?.addEventListener('click', () => { page -= 1; renderPage(); });
    next?.addEventListener('click', () => { page += 1; renderPage(); });
    renderPage();

    const selector = document.getElementById('mrwWarehouseSelect');
    const form = document.getElementById('warehouseAllocationForm');
    if (!selector || !form) return;

    const endpoint = @json(route('material-requests.warehouse-products.search'));
    const money = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0)) + ' đ';
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[char]));
    let lastWarehouse = selector.value;

    function updateCount() {
        const count = rows.filter(row => Boolean(row.querySelector('[data-product-id]')?.value)).length;
        document.querySelectorAll('[data-matched-count], [data-footer-matched]').forEach(element => element.textContent = count);
    }

    function updateLiveStock(row, item) {
        row.querySelector('[data-stock-breakdown]').innerHTML = (item.stock_by_warehouse || []).map(stock => `<span class="${String(stock.warehouse_id) === selector.value ? 'dispatch' : ''}">${escapeHtml(stock.warehouse_name)}: <b>${escapeHtml(stock.quantity)}</b></span>`).join('') || '<span>Chưa có tồn tại các kho</span>';
        const available = Number(item.quantity || 0);
        const missing = Math.max(0, Number(row.dataset.qty || 0) - available);
        const liveStock = row.querySelector('[data-live-stock]');
        liveStock.textContent = 'Tồn EGO_VN: ' + available + (missing ? ' · Thiếu ' + missing + ' · Chờ điều hàng' : ' · Đủ xuất');
        liveStock.classList.toggle('shortage', missing > 0);
    }

    async function refreshLiveStocks() {
        if (document.hidden || !selector.value) return;
        const ids = [...new Set(rows.map(row => row.querySelector('[data-product-id]')?.value).filter(Boolean))];
        if (!ids.length) return;
        try {
            const response = await fetch(endpoint + '?product_ids=' + encodeURIComponent(ids.join(',')) + '&warehouse_id=' + encodeURIComponent(selector.value), {headers: {'Accept':'application/json'}});
            if (!response.ok) return;
            const payload = await response.json();
            const products = new Map((payload.data || []).map(item => [String(item.product_id), item]));
            rows.forEach(row => {
                const id = row.querySelector('[data-product-id]')?.value;
                if (!id) return;
                updateLiveStock(row, products.get(String(id)) || {quantity: 0, stock_by_warehouse: []});
            });
        } catch (error) { /* Giữ dữ liệu gần nhất nếu mạng tạm gián đoạn. */ }
    }

    setInterval(refreshLiveStocks, 25000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshLiveStocks(); });

    function clearRow(row) {
        row.querySelector('[data-product-id]').value = '';
        row.querySelector('[data-warehouse-id]').value = '';
        const product = row.querySelector('[data-selected-product]');
        product.classList.remove('matched');
        product.classList.add('waiting');
        product.querySelector('i').className = 'bi bi-dash-circle';
        row.querySelector('[data-selected-name]').textContent = 'Chưa ghép sản phẩm';
        row.querySelector('[data-selected-meta]').textContent = 'Tìm sản phẩm để xem tồn tại các kho';
        row.querySelector('[data-stock-breakdown]').innerHTML = '';
        row.querySelector('[data-live-stock]').textContent = '';
        row.querySelector('[data-live-stock]').classList.remove('shortage');
        const cost = row.querySelector('[data-cost]');
        const total = row.querySelector('[data-line-total]');
        if (cost) cost.textContent = '—';
        if (total) total.textContent = '';
        updateCount();
    }

    selector.addEventListener('change', () => {
        const hasMatched = rows.some(row => Boolean(row.querySelector('[data-product-id]')?.value));
        if (hasMatched && lastWarehouse !== selector.value && !confirm('Đổi kho sẽ bỏ toàn bộ sản phẩm đã ghép. Tiếp tục?')) {
            selector.value = lastWarehouse;
            return;
        }
        if (hasMatched && lastWarehouse !== selector.value) rows.forEach(clearRow);
        lastWarehouse = selector.value;
    });

    form.addEventListener('submit', event => {
        if (!selector.value) {
            event.preventDefault();
            selector.reportValidity();
            selector.focus();
        }
    });

    rows.forEach(row => {
        const input = row.querySelector('[data-product-search]');
        const results = row.querySelector('[data-search-results]');
        let timer;
        const close = () => { results.hidden = true; results.innerHTML = ''; };

        input.addEventListener('input', () => {
            clearTimeout(timer);
            const keyword = input.value.trim();
            if (keyword.length < 2) { close(); return; }
            results.hidden = false;
            results.innerHTML = '<div class="mrw-search-message">Đang kiểm tra tồn tại tất cả kho...</div>';
            timer = setTimeout(async () => {
                try {
                    const response = await fetch(endpoint + '?q=' + encodeURIComponent(keyword) + '&warehouse_id=' + encodeURIComponent(selector.value), {headers: {'Accept':'application/json'}});
                    if (!response.ok) throw new Error('search');
                    const payload = await response.json();
                    const products = payload.data || [];
                    if (!products.length) { results.innerHTML = '<div class="mrw-search-message">Không tìm thấy sản phẩm trong các kho.</div>'; return; }
                    results.innerHTML = products.map((item, index) => {
                        const stockText = (item.stock_by_warehouse || []).map(stock => `${escapeHtml(stock.warehouse_name)}: ${escapeHtml(stock.quantity)}`).join(' · ');
                        return `<button type="button" data-result-index="${index}"><span><strong>${escapeHtml(item.product_name)}</strong><small>${item.sku ? 'SKU ' + escapeHtml(item.sku) + ' · ' : ''}${stockText}</small></span><b>EGO_VN: ${escapeHtml(item.quantity)}</b></button>`;
                    }).join('');
                    results.querySelectorAll('[data-result-index]').forEach(button => button.addEventListener('click', () => {
                        const item = products[Number(button.dataset.resultIndex)];
                        row.querySelector('[data-product-id]').value = item.product_id;
                        row.querySelector('[data-warehouse-id]').value = selector.value;
                        row.querySelector('[data-selected-name]').textContent = item.product_name;
                        row.querySelector('[data-selected-meta]').textContent = (item.sku ? 'SKU ' + item.sku + ' · ' : '') + 'Xuất tại ' + item.warehouse_name;
                        updateLiveStock(row, item);
                        const product = row.querySelector('[data-selected-product]');
                        product.classList.remove('waiting');
                        product.classList.add('matched');
                        product.querySelector('i').className = 'bi bi-check-circle-fill';
                        const cost = row.querySelector('[data-cost]');
                        const total = row.querySelector('[data-line-total]');
                        if (cost) cost.textContent = money(item.unit_cost);
                        if (total) total.textContent = money(Number(item.unit_cost || 0) * Number(row.dataset.qty || 0));
                        input.value = '';
                        close();
                        updateCount();
                    }));
                } catch (error) { results.innerHTML = '<div class="mrw-search-message">Không tải được dữ liệu kho.</div>'; }
            }, 260);
        });

        row.querySelector('[data-clear-selection]').addEventListener('click', () => { clearRow(row); input.focus(); });
        document.addEventListener('click', event => { if (!row.contains(event.target)) close(); });
    });
});
</script>
@endpush
