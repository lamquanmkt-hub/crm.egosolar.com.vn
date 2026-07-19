
@php
    $egoPendingOrdersCount = $egoPendingOrdersCount ?? 0;
    $egoPendingMaterialRequestsCount = $egoPendingMaterialRequestsCount ?? 0;

    try {
        $egoPendingOrdersCount = (int) \Illuminate\Support\Facades\DB::table('crm_order_approvals')
            ->where('status', 'pending')
            ->distinct()
            ->count('order_id');

        $egoPendingMaterialRequestsCount = (int) \Illuminate\Support\Facades\DB::table('material_requests')
            ->whereIn('status', ['SUBMITTED', 'ADMIN_APPROVED'])
            ->count();
    } catch (\Throwable $e) {
        $egoPendingOrdersCount = 0;
        $egoPendingMaterialRequestsCount = 0;
    }
@endphp

@extends('layouts.app')

@section('content')
<div class="container-fluid px-3 px-lg-4 mt-3 ego-stock-history-page">

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <div class="ego-page-dot"></div>
                <h3 class="fw-bold mb-0">Lịch sử nhập / xuất kho</h3>
            </div>
            <div class="text-muted small">Theo dõi biến động tồn kho từ đơn hàng và công trình</div>
        </div>

        <a href="{{ route('products.input') }}" class="btn ego-btn-soft">
            <i class="bi bi-arrow-left"></i> Quay lại sản phẩm
        </a>
    </div>

    <form method="GET" class="card ego-card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg-4">
                    <label class="form-label ego-label">Tìm kiếm</label>
                    <input type="text" name="q" class="form-control ego-control"
                           value="{{ request('q') }}"
                           placeholder="Sản phẩm, SKU, kho, đơn hàng, công trình...">
                </div>

                <div class="col-12 col-lg-3">
                    <label class="form-label ego-label">Kho</label>
                    <select name="warehouse_id" class="form-select ego-control">
                        <option value="">Tất cả kho</option>
                        @foreach(($warehouses ?? collect()) as $w)
                            <option value="{{ $w->id }}" {{ request('warehouse_id') == $w->id ? 'selected' : '' }}>
                                {{ $w->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-lg-3">
                    <label class="form-label ego-label">Loại</label>
                    <select name="type" class="form-select ego-control">
                        <option value="">Tất cả</option>
                        <option value="in" {{ request('type') === 'in' ? 'selected' : '' }}>Nhập kho</option>
                        <option value="out" {{ request('type') === 'out' ? 'selected' : '' }}>Xuất kho</option>
                    </select>
                </div>

                <div class="col-12 col-lg-2 d-flex gap-2">
                    <button class="btn ego-btn-primary flex-fill">
                        <i class="bi bi-funnel"></i> Lọc
                    </button>
                    <a href="{{ route('products.history') }}" class="btn ego-btn-soft">
                        Reset
                    </a>
                </div>
            </div>
        </div>
    </form>

    <div class="card ego-card">
        <div class="table-responsive ego-table-wrap">
            <table class="table align-middle mb-0 ego-history-table">
                <thead>
                <tr>
                    <th style="width: 260px;">Đơn hàng / Công trình</th>
                    <th style="min-width: 280px;">Sản phẩm</th>
                    <th style="width: 180px;">Kho</th>
                    <th class="text-center" style="width: 165px;">Số lượng<br>trước khi xuất/nhập</th>
                    <th class="text-center" style="width: 130px;">Thay đổi</th>
                    <th class="text-center" style="width: 165px;">Số lượng<br>sau khi xuất/nhập</th>
                    <th style="min-width: 260px;">Ghi chú</th>
                    <th style="width: 170px;">Người xuất / nhập</th>
                    <th style="width: 180px;">Thời gian nhập / xuất</th>
                </tr>
                </thead>

                <tbody>
                @forelse($logs as $log)
                    @php
                        $changeQty = (int)($log->change_qty ?? 0);
                        $isExport = $changeQty < 0;
                        $reason = (string)($log->reason ?? '');
                        $noteRaw = (string)($log->note ?? '');
                        $refType = (string)($log->reference_type ?? '');
                        $reasonLower = mb_strtolower($reason, 'UTF-8');
                        $noteLower = mb_strtolower($noteRaw, 'UTF-8');
                        $refTypeLower = mb_strtolower($refType, 'UTF-8');

                        if (!empty($log->order_code) || str_contains($refTypeLower, 'order')) {
                            $sourceLabel = 'Đơn hàng ' . $log->order_code;
                            $sourceClass = 'is-order';
                            $sourceIcon = 'bi-receipt-cutoff';
                        } elseif (!empty($log->site_name) || str_contains($refTypeLower, 'material')) {
                            $sourceLabel = !empty($log->site_name) ? ('Công trình: ' . $log->site_name) : ('Đơn vật tư ID ' . ($log->reference_id ?? '—'));
                            $sourceClass = 'is-site';
                            $sourceIcon = 'bi-kanban';
                        } elseif (str_contains($refTypeLower, 'manual') || str_contains($reasonLower, 'manual') || str_contains($reasonLower, 'nhập tay') || str_contains($reasonLower, 'sản phẩm đầu vào') || str_contains($reasonLower, 'product_create') || str_contains($reasonLower, 'manual_lot') || str_contains($reasonLower, 'v2') || str_contains($noteLower, 'nhập tay')) {
                            $sourceLabel = $changeQty >= 0 ? 'Nhập tay' : 'Điều chỉnh tay';
                            $sourceClass = 'is-other';
                            $sourceIcon = 'bi-pencil-square';
                        } elseif (str_contains($reason, 'material')) {
                            $sourceLabel = 'Đơn vật tư ID ' . ($log->reference_id ?? '—');
                            $sourceClass = 'is-site';
                            $sourceIcon = 'bi-kanban';
                        } else {
                            $sourceLabel = 'Điều chỉnh kho';
                            $sourceClass = 'is-other';
                            $sourceIcon = 'bi-arrow-left-right';
                        }

                        $note = $log->note ?? null;
                        if (!$note) {
                            $note = match ($reason) {
                                'order_export' => 'Xuất kho theo đơn hàng',
                                'material_request_export' => 'Xuất kho cho công trình / đơn vật tư',
                                'material_external_import' => 'Nhập vật tư ngoài kho trước khi xuất cho công trình',
                                'stock_decrease' => 'Điều chỉnh giảm tồn kho',
                                'stock_increase' => 'Điều chỉnh tăng tồn kho',
                                default => $reason ?: '—',
                            };
                        }
                    @endphp

                    <tr>
                        <td>
                            <span class="ego-source {{ $sourceClass }}">
                                <i class="bi {{ $sourceIcon }}"></i>
                                {{ $sourceLabel }}
                            </span>
                        </td>

                        <td>
                            <div class="fw-bold ego-product-name">
                                {{ $log->product_name ?? 'Không rõ sản phẩm' }}
                            </div>
                            @if(!empty($log->product_sku))
                                <div class="small text-muted">SKU: {{ $log->product_sku }}</div>
                            @endif
                        </td>

                        <td>
                            <span class="ego-warehouse">
                                <i class="bi bi-box-seam"></i>
                                {{ $log->warehouse_name ?? 'Không rõ kho' }}
                            </span>
                        </td>

                        <td class="text-center">
                            <span class="ego-before-qty">
                                {{ $log->qty_before_safe !== null ? number_format((int)$log->qty_before_safe) : '—' }}
                            </span>
                        </td>

                        <td class="text-center">
                            <span class="ego-change {{ $isExport ? 'is-minus' : 'is-plus' }}">
                                {{ $changeQty > 0 ? '+' : '' }}{{ number_format($changeQty) }}
                            </span>
                        </td>

                        <td class="text-center">
                            <span class="ego-current-qty">
                                {{ $log->qty_after_safe !== null ? number_format((int)$log->qty_after_safe) : '—' }}
                            </span>
                        </td>

                        <td>
                            <div class="ego-note" title="{{ $note }}">
                                {{ $note }}
                            </div>
                        </td>

                        <td>
                            <span class="ego-user">
                                <i class="bi bi-person-check"></i>
                                {{ $log->user_name ?? '—' }}
                            </span>
                        </td>

                        <td>
                            <div class="ego-time">
                                {{ !empty($log->created_at) ? \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i') : '—' }}
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">
                            Chưa có lịch sử kho
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-body d-flex justify-content-end py-2">
            {{ $logs->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

<style>
.ego-stock-history-page{
    --ego:#0E7C86;
    --ego2:#0B5E66;
    --border: rgba(12, 92, 100, .12);
    --muted:#64748b;
    --text:#0f172a;
}
.ego-page-dot{
    width:9px;height:9px;border-radius:999px;
    background:linear-gradient(135deg,var(--ego),var(--ego2));
    box-shadow:0 8px 18px rgba(14,124,134,.22);
}
.ego-card{
    border:1px solid var(--border);
    border-radius:16px;
    overflow:hidden;
    background:#fff;
    box-shadow:0 10px 24px rgba(15,23,42,.04);
}
.ego-label{
    font-size:11px;
    font-weight:900;
    letter-spacing:.35px;
    color:var(--muted);
    text-transform:uppercase;
}
.ego-control{
    border-radius:12px;
    border:1px solid var(--border);
    padding:.5rem .75rem;
}
.ego-table-wrap{ overflow-x:auto; }
.ego-history-table{ min-width:1460px; }
.ego-history-table thead th{
    background:linear-gradient(135deg, rgba(14,124,134,.10), rgba(14,124,134,.03));
    border-bottom:1px solid var(--border);
    font-size:11px;
    font-weight:900;
    letter-spacing:.35px;
    color:var(--muted);
    text-transform:uppercase;
    white-space:nowrap;
    padding:13px 12px;
}
.ego-history-table tbody td{
    border-top:1px solid rgba(15,23,42,.06);
    padding:13px 12px;
    vertical-align:middle;
    background:#fff;
    font-size:.93rem;
}
.ego-history-table tbody tr:nth-child(2n) td{ background:rgba(15,23,42,.015); }
.ego-history-table tbody tr:hover td{ background:rgba(14,124,134,.05); }
.ego-btn-primary{
    background:linear-gradient(135deg,var(--ego),var(--ego2));
    border:none;
    color:#fff;
    border-radius:12px;
    padding:8px 12px;
    font-weight:900;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
}
.ego-btn-soft{
    background:rgba(14,124,134,.10);
    border:1px solid var(--border);
    color:var(--ego2);
    border-radius:12px;
    padding:8px 12px;
    font-weight:900;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    text-decoration:none;
}
.ego-source{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 10px;
    border-radius:999px;
    font-weight:900;
    font-size:12px;
    white-space:nowrap;
}
.ego-source.is-order{
    background:rgba(59,130,246,.10);
    color:#1d4ed8;
    border:1px solid rgba(59,130,246,.18);
}
.ego-source.is-site{
    background:rgba(168,85,247,.10);
    color:#7e22ce;
    border:1px solid rgba(168,85,247,.18);
}
.ego-source.is-other{
    background:rgba(100,116,139,.10);
    color:#475569;
    border:1px solid rgba(100,116,139,.18);
}
.ego-product-name{ color:var(--text); line-height:1.25; }
.ego-warehouse{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:6px 10px;
    border-radius:999px;
    background:rgba(14,124,134,.08);
    color:var(--ego2);
    font-weight:800;
    font-size:12px;
    max-width:170px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}
.ego-change{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:74px;
    padding:7px 10px;
    border-radius:999px;
    font-weight:950;
    letter-spacing:.2px;
}
.ego-change.is-minus{
    color:#b4232c;
    background:rgba(220,53,69,.08);
    border:1px solid rgba(220,53,69,.20);
}
.ego-change.is-plus{
    color:#0f7a3a;
    background:rgba(34,197,94,.10);
    border:1px solid rgba(34,197,94,.20);
}

.ego-before-qty{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:70px;
    padding:7px 10px;
    border-radius:999px;
    background:#f8fafc;
    border:1px solid rgba(15,23,42,.12);
    color:#0f172a;
    font-weight:950;
}
.ego-current-qty{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:70px;
    padding:7px 10px;
    border-radius:999px;
    background:#fff;
    border:1px solid rgba(15,23,42,.10);
    color:#0f172a;
    font-weight:950;
}
.ego-note{
    color:#111827;
    font-weight:600;
    line-height:1.35;
    display:-webkit-box;
    -webkit-line-clamp:2;
    -webkit-box-orient:vertical;
    overflow:hidden;
}
.ego-user{
    display:inline-flex;
    align-items:center;
    gap:7px;
    color:#334155;
    font-weight:800;
    white-space:nowrap;
}
.ego-time{
    color:#334155;
    font-weight:800;
    white-space:nowrap;
}
@media (max-width: 991.98px){
    .ego-history-table{ min-width:1280px; }
}
</style>

@endsection

{{-- EGO_FIX_CONG_TRINH_CLICKABLE_START --}}
<script>
(function () {
    function normalizeText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function findSidebar() {
        var selectors = [
            'aside',
            '.sidebar',
            '#sidebar',
            '.main-sidebar',
            '.app-sidebar',
            '.side-menu',
            '.navigation',
            'nav'
        ];

        for (var i = 0; i < selectors.length; i++) {
            var nodes = document.querySelectorAll(selectors[i]);

            for (var j = 0; j < nodes.length; j++) {
                var text = normalizeText(nodes[j].textContent);

                if (
                    text.indexOf('trang chủ') !== -1 &&
                    (
                        text.indexOf('đơn hàng') !== -1 ||
                        text.indexOf('đề nghị thanh toán') !== -1 ||
                        text.indexOf('khách hàng') !== -1 ||
                        text.indexOf('công trình') !== -1
                    )
                ) {
                    return nodes[j];
                }
            }
        }

        return null;
    }

    function isCompactMenuElement(el) {
        if (!el) return false;

        var text = normalizeText(el.textContent);

        if (text !== 'công trình') return false;

        var rect = el.getBoundingClientRect();

        return rect.height <= 80 && rect.width <= 320;
    }

    function cleanDropdownBehavior(el) {
        if (!el) return;

        [
            'data-bs-toggle',
            'data-toggle',
            'data-bs-target',
            'data-target',
            'aria-expanded',
            'aria-controls'
        ].forEach(function (attr) {
            el.removeAttribute(attr);
        });

        el.classList.remove('collapsed');
    }

    function makeClickable(el) {
        if (!el) return;

        el.setAttribute('data-ego-cong-trinh-click', '1');
        el.setAttribute('role', 'link');
        el.setAttribute('tabindex', '0');
        el.style.cursor = 'pointer';

        var link = el.matches('a') ? el : el.querySelector('a');

        if (link) {
            link.href = '/cong-trinh';
            cleanDropdownBehavior(link);
        }

        cleanDropdownBehavior(el);

        var parent = el.closest('li, .nav-item, .menu-item, .sidebar-item, [class*="nav"], [class*="menu"]');

        if (parent && parent !== el) {
            parent.setAttribute('data-ego-cong-trinh-click', '1');
            parent.style.cursor = 'pointer';
            cleanDropdownBehavior(parent);
        }
    }

    function fixMenu() {
        var sidebar = findSidebar();

        if (!sidebar) return;

        var directLink = Array.prototype.slice.call(sidebar.querySelectorAll('a')).find(function (a) {
            return normalizeText(a.textContent) === 'công trình';
        });

        if (directLink) {
            makeClickable(directLink);
        }

        var candidates = Array.prototype.slice.call(sidebar.querySelectorAll('a, button, li, div, span')).filter(isCompactMenuElement);

        candidates.forEach(makeClickable);

        var active = location.pathname.indexOf('/cong-trinh') === 0;

        if (active) {
            sidebar.querySelectorAll('[data-ego-cong-trinh-click="1"]').forEach(function (el) {
                el.classList.add('active');
            });
        }
    }

    function goToCongTrinh(event) {
        var target = event.target;
        var clickable = target && target.closest ? target.closest('[data-ego-cong-trinh-click="1"]') : null;

        if (!clickable) return;

        event.preventDefault();
        event.stopPropagation();

        window.location.href = '/cong-trinh';
    }

    function goToCongTrinhKeyboard(event) {
        if (event.key !== 'Enter' && event.key !== ' ') return;

        var target = event.target;
        var clickable = target && target.closest ? target.closest('[data-ego-cong-trinh-click="1"]') : null;

        if (!clickable) return;

        event.preventDefault();
        window.location.href = '/cong-trinh';
    }

    document.addEventListener('DOMContentLoaded', fixMenu);
    document.addEventListener('click', goToCongTrinh, true);
    document.addEventListener('keydown', goToCongTrinhKeyboard, true);

    setTimeout(fixMenu, 300);
    setTimeout(fixMenu, 900);
    setTimeout(fixMenu, 1600);
})();
</script>
{{-- EGO_FIX_CONG_TRINH_CLICKABLE_END --}}


