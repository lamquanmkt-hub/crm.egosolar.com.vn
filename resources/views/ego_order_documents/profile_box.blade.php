@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $egoDocTypes = [
        'payment_request' => 'ĐNTT',
        'purchase_contract' => 'Hợp đồng mua bán',
        'agency_contract' => 'Hợp đồng đại lý',
        'deposit' => 'Phiếu đặt cọc',
        'quotation' => 'Báo giá',
        'invoice' => 'Hóa đơn / UNC',
        'delivery' => 'Vận chuyển / Giao nhận',
        'warranty' => 'Bảo hành',
        'po' => 'PO',
        'ci' => 'Commercial Invoice',
        'pl' => 'Packing List',
        'co_cq' => 'CO / CQ',
        'customs' => 'Hải quan',
        'other' => 'Khác',
    ];

    $egoProfileId = (int) ($profile->id ?? 0);

    $egoCustomerIds = collect([$profile->customer_id ?? null])
        ->filter()
        ->map(fn($x) => (int) $x)
        ->unique()
        ->values();

    if (Schema::hasTable('crm_customers')) {
        if (!empty($profile->phone) && Schema::hasColumn('crm_customers', 'phone')) {
            $egoCustomerIds = $egoCustomerIds
                ->merge(DB::table('crm_customers')->where('phone', $profile->phone)->pluck('id'))
                ->filter()
                ->map(fn($x) => (int) $x)
                ->unique()
                ->values();
        }

        if (!empty($profile->agent_name) && Schema::hasColumn('crm_customers', 'name')) {
            $egoCustomerIds = $egoCustomerIds
                ->merge(DB::table('crm_customers')->where('name', $profile->agent_name)->pluck('id'))
                ->filter()
                ->map(fn($x) => (int) $x)
                ->unique()
                ->values();
        }
    }

    $egoLeadIds = collect();

    if (Schema::hasTable('crm_leads') && Schema::hasColumn('crm_leads', 'customer_id') && $egoCustomerIds->count()) {
        $egoLeadIds = DB::table('crm_leads')
            ->whereIn('customer_id', $egoCustomerIds)
            ->pluck('id')
            ->map(fn($x) => (int) $x)
            ->unique()
            ->values();
    }

    $egoOrders = collect();

    if (Schema::hasTable('crm_orders') && ($egoLeadIds->count() || $egoCustomerIds->count())) {
        $egoOrdersQuery = DB::table('crm_orders as o')->select('o.*');

        if (Schema::hasTable('crm_order_status_types') && Schema::hasColumn('crm_orders', 'current_status_type_id')) {
            $egoOrdersQuery
                ->leftJoin('crm_order_status_types as st', 'st.id', '=', 'o.current_status_type_id')
                ->addSelect('st.name as ego_status_name');
        }

        $egoOrdersQuery->where(function($q) use ($egoLeadIds, $egoCustomerIds) {
            $has = false;

            if ($egoLeadIds->count() && Schema::hasColumn('crm_orders', 'lead_id')) {
                $q->whereIn('o.lead_id', $egoLeadIds);
                $has = true;
            }

            if ($egoCustomerIds->count() && Schema::hasColumn('crm_orders', 'customer_id')) {
                $has ? $q->orWhereIn('o.customer_id', $egoCustomerIds) : $q->whereIn('o.customer_id', $egoCustomerIds);
            }
        });

        $egoOrders = $egoOrdersQuery
            ->orderByDesc('o.id')
            ->limit(100)
            ->get();
    }

    $egoOrderIds = $egoOrders->pluck('id')->map(fn($x) => (int) $x)->unique()->values();

    $egoPaidByOrder = collect();

    foreach (['crm_order_payments', 'order_payments', 'crm_payments'] as $payTable) {
        if (
            Schema::hasTable($payTable) &&
            Schema::hasColumn($payTable, 'order_id') &&
            Schema::hasColumn($payTable, 'amount')
        ) {
            $egoPaidByOrder = DB::table($payTable)
                ->whereIn('order_id', $egoOrderIds)
                ->select('order_id', DB::raw('SUM(amount) as paid'))
                ->groupBy('order_id')
                ->pluck('paid', 'order_id');

            break;
        }
    }

    $egoOrderDocs = collect();

    if (Schema::hasTable('crm_order_documents')) {
        $egoOrderDocs = DB::table('crm_order_documents')
            ->where(function($q) use ($egoProfileId, $egoCustomerIds, $egoOrderIds) {
                if (Schema::hasColumn('crm_order_documents', 'customer_profile_id')) {
                    $q->where('customer_profile_id', $egoProfileId);
                } else {
                    $q->whereRaw('1 = 0');
                }

                if ($egoCustomerIds->count() && Schema::hasColumn('crm_order_documents', 'customer_id')) {
                    $q->orWhereIn('customer_id', $egoCustomerIds);
                }

                if ($egoOrderIds->count() && Schema::hasColumn('crm_order_documents', 'order_id')) {
                    $q->orWhereIn('order_id', $egoOrderIds);
                }
            })
            ->orderByDesc('id')
            ->get();
    }

    $egoDocsByOrder = $egoOrderDocs->groupBy('order_id');
    $egoTotalRevenue = (float) $egoOrders->sum(fn($o) => (float) ($o->total_amount ?? 0));
    $egoTotalPaid = (float) $egoPaidByOrder->sum();
    $egoTotalRemain = max(0, $egoTotalRevenue - $egoTotalPaid);

    $egoFirstDoc = $egoOrderDocs->first();
    $egoFirstPreviewUrl = $egoFirstDoc ? url('/orders/'.$egoFirstDoc->order_id.'/documents-ego/'.$egoFirstDoc->id.'/preview') : '';
    $egoFirstName = $egoFirstDoc ? (($egoFirstDoc->title ?: $egoFirstDoc->original_name) ?: 'File xem trước') : '';
@endphp

<style>
.ego-cp-order-board{
    margin-top:18px;
    margin-bottom:26px;
    background:linear-gradient(135deg,#ffffff 0%,#f8fdff 48%,#eefaff 100%);
    border:1px solid rgba(14,165,233,.18);
    border-radius:26px;
    box-shadow:0 20px 55px rgba(15,23,42,.08);
    overflow:hidden;
}

.ego-cp-order-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    padding:20px 22px 16px;
    border-bottom:1px solid rgba(148,163,184,.20);
}

.ego-cp-order-title{
    display:flex;
    align-items:center;
    gap:12px;
}

.ego-cp-order-icon{
    width:46px;
    height:46px;
    border-radius:18px;
    display:grid;
    place-items:center;
    color:#fff;
    background:linear-gradient(135deg,#0891b2,#0f766e);
    box-shadow:0 14px 28px rgba(8,145,178,.24);
}

.ego-cp-order-title h3{
    margin:0;
    font-size:22px;
    font-weight:950;
    color:#0f172a;
    letter-spacing:-.02em;
}

.ego-cp-order-title p{
    margin:5px 0 0;
    font-size:12.5px;
    font-weight:700;
    color:#64748b;
}

.ego-cp-kpis{
    display:flex;
    flex-wrap:wrap;
    justify-content:flex-end;
    gap:8px;
}

.ego-cp-kpi{
    min-width:116px;
    padding:10px 12px;
    border-radius:17px;
    background:rgba(255,255,255,.86);
    border:1px solid rgba(148,163,184,.22);
}

.ego-cp-kpi .k{
    font-size:10px;
    font-weight:950;
    color:#64748b;
    text-transform:uppercase;
}

.ego-cp-kpi .v{
    margin-top:4px;
    font-size:16px;
    font-weight:950;
    color:#0f172a;
    line-height:1.2;
}

.ego-cp-order-body{
    padding:18px 22px 22px;
}

.ego-cp-main-grid{
    display:grid;
    grid-template-columns:minmax(0,1.12fr) minmax(380px,.88fr);
    gap:16px;
    align-items:start;
}

.ego-order-list{
    display:grid;
    gap:12px;
}

.ego-order-card{
    border:1px solid rgba(148,163,184,.22);
    background:rgba(255,255,255,.90);
    border-radius:20px;
    overflow:hidden;
    box-shadow:0 12px 30px rgba(15,23,42,.045);
}

.ego-order-card-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
    padding:14px 16px;
    border-bottom:1px solid #edf2f7;
    background:#fff;
}

.ego-order-code{
    font-size:15px;
    font-weight:950;
    color:#0f172a;
}

.ego-order-meta{
    margin-top:3px;
    font-size:12px;
    font-weight:750;
    color:#64748b;
}

.ego-status-pill,
.ego-file-pill{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:25px;
    padding:0 9px;
    border-radius:999px;
    font-size:11px;
    font-weight:950;
    white-space:nowrap;
}

.ego-status-pill{
    background:#e0f2fe;
    color:#0369a1;
}

.ego-file-pill{
    background:#ecfdf5;
    color:#047857;
}

.ego-order-money{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:8px;
    padding:12px 16px;
    border-bottom:1px solid #edf2f7;
}

.ego-money-box{
    border:1px solid #edf2f7;
    border-radius:15px;
    padding:9px 10px;
    background:#f8fafc;
}

.ego-money-box .k{
    font-size:10px;
    font-weight:950;
    color:#64748b;
    text-transform:uppercase;
}

.ego-money-box .v{
    margin-top:4px;
    font-size:14px;
    font-weight:950;
    color:#0f172a;
}

.ego-order-docs{
    padding:12px 16px 15px;
}

.ego-doc-row{
    display:grid;
    grid-template-columns:42px minmax(0,1fr) auto;
    align-items:center;
    gap:10px;
    padding:10px;
    border:1px solid #edf2f7;
    border-radius:15px;
    background:#fff;
    margin-top:8px;
}

.ego-doc-ext{
    width:38px;
    height:38px;
    border-radius:14px;
    display:grid;
    place-items:center;
    background:linear-gradient(135deg,#e0f2fe,#f0fdfa);
    color:#0369a1;
    font-size:10px;
    font-weight:950;
    text-transform:uppercase;
}

.ego-doc-name{
    font-size:13px;
    font-weight:950;
    color:#0f172a;
    line-height:1.25;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.ego-doc-meta{
    margin-top:3px;
    font-size:11.5px;
    font-weight:700;
    color:#64748b;
}

.ego-doc-actions{
    display:flex;
    align-items:center;
    gap:6px;
    flex-wrap:wrap;
    justify-content:flex-end;
}

.ego-doc-btn{
    height:31px;
    border:1px solid #dbe5ef;
    border-radius:11px;
    padding:0 10px;
    background:#fff;
    color:#0f172a;
    font-size:12px;
    font-weight:900;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    text-decoration:none;
    cursor:pointer;
}

.ego-doc-btn:hover{
    color:#0891b2;
    border-color:#7dd3fc;
    text-decoration:none;
}

.ego-doc-btn.primary{
    background:linear-gradient(135deg,#0891b2,#0f766e);
    color:#fff;
    border-color:transparent;
}

.ego-preview-panel{
    position:sticky;
    top:84px;
    border:1px solid rgba(148,163,184,.24);
    border-radius:22px;
    background:#fff;
    overflow:hidden;
    box-shadow:0 16px 42px rgba(15,23,42,.07);
}

.ego-preview-head{
    min-height:54px;
    padding:12px 14px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    border-bottom:1px solid #edf2f7;
    background:linear-gradient(135deg,#f8fafc,#f0fdfa);
}

.ego-preview-title{
    min-width:0;
}

.ego-preview-title .k{
    font-size:10px;
    font-weight:950;
    color:#64748b;
    text-transform:uppercase;
}

.ego-preview-title .v{
    margin-top:3px;
    font-size:13px;
    font-weight:950;
    color:#0f172a;
    overflow:hidden;
    white-space:nowrap;
    text-overflow:ellipsis;
    max-width:360px;
}

.ego-preview-frame-wrap{
    height:560px;
    background:#f8fafc;
}

.ego-preview-frame{
    display:block;
    width:100%;
    height:100%;
    border:0;
    background:#fff;
}

.ego-empty{
    padding:16px;
    border:1px dashed #cbd5e1;
    border-radius:16px;
    background:#f8fafc;
    color:#64748b;
    font-weight:750;
}

.ego-preview-empty{
    height:560px;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:22px;
    color:#64748b;
    font-weight:800;
    text-align:center;
}

@media(max-width:1200px){
    .ego-cp-main-grid{
        grid-template-columns:1fr;
    }

    .ego-preview-panel{
        position:relative;
        top:auto;
    }
}

@media(max-width:760px){
    .ego-cp-order-head{
        display:block;
    }

    .ego-cp-kpis{
        justify-content:flex-start;
        margin-top:14px;
    }

    .ego-order-money{
        grid-template-columns:1fr;
    }

    .ego-doc-row{
        grid-template-columns:38px 1fr;
    }

    .ego-doc-actions{
        grid-column:1 / -1;
        justify-content:flex-start;
    }
}
</style>

<div class="ego-cp-order-board">
    <div class="ego-cp-order-head">
        <div class="ego-cp-order-title">
            <div class="ego-cp-order-icon">
                <i class="bi bi-receipt-cutoff"></i>
            </div>
            <div>
                <h3>Đơn hàng & giấy tờ liên quan</h3>
                <p>Hiển thị toàn bộ đơn hàng của đại lý và file upload từ đơn hàng. Bấm “Xem tại trang” để xem trước ngay bên phải.</p>
            </div>
        </div>

        <div class="ego-cp-kpis">
            <div class="ego-cp-kpi">
                <div class="k">Đơn hàng</div>
                <div class="v">{{ $egoOrders->count() }}</div>
            </div>
            <div class="ego-cp-kpi">
                <div class="k">Tổng tiền</div>
                <div class="v">{{ number_format($egoTotalRevenue, 0, ',', '.') }} đ</div>
            </div>
            <div class="ego-cp-kpi">
                <div class="k">Đã thu</div>
                <div class="v" style="color:#16a34a">{{ number_format($egoTotalPaid, 0, ',', '.') }} đ</div>
            </div>
            <div class="ego-cp-kpi">
                <div class="k">File</div>
                <div class="v">{{ $egoOrderDocs->count() }}</div>
            </div>
        </div>
    </div>

    <div class="ego-cp-order-body">
        <div class="ego-cp-main-grid">
            <div class="ego-order-list">
                @forelse($egoOrders as $egoOrder)
                    @php
                        $egoOrderDocsOfOrder = $egoDocsByOrder->get($egoOrder->id, collect());
                        $egoPaid = (float) ($egoPaidByOrder[$egoOrder->id] ?? 0);
                        $egoTotal = (float) ($egoOrder->total_amount ?? 0);
                        $egoRemain = max(0, $egoTotal - $egoPaid);
                        $egoStatus = $egoOrder->ego_status_name
                            ?? ($egoOrder->current_department ?? ($egoOrder->shipping_status ?? '—'));
                    @endphp

                    <div class="ego-order-card">
                        <div class="ego-order-card-head">
                            <div>
                                <div class="ego-order-code">{{ $egoOrder->order_code ?? ('Đơn #'.$egoOrder->id) }}</div>
                                <div class="ego-order-meta">
                                    Ngày: {{ !empty($egoOrder->order_date) ? \Carbon\Carbon::parse($egoOrder->order_date)->format('d/m/Y') : '—' }}
                                    • ID #{{ $egoOrder->id }}
                                </div>
                            </div>

                            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;justify-content:flex-end">
                                <span class="ego-status-pill">{{ $egoStatus ?: '—' }}</span>
                                <span class="ego-file-pill">{{ $egoOrderDocsOfOrder->count() }} file</span>
                                <a class="ego-doc-btn" href="{{ url('/orders/'.$egoOrder->id) }}">Mở đơn</a>
                            </div>
                        </div>

                        <div class="ego-order-money">
                            <div class="ego-money-box">
                                <div class="k">Tổng tiền</div>
                                <div class="v">{{ number_format($egoTotal, 0, ',', '.') }} đ</div>
                            </div>
                            <div class="ego-money-box">
                                <div class="k">Đã thu</div>
                                <div class="v" style="color:#16a34a">{{ number_format($egoPaid, 0, ',', '.') }} đ</div>
                            </div>
                            <div class="ego-money-box">
                                <div class="k">Còn lại</div>
                                <div class="v" style="color:#dc2626">{{ number_format($egoRemain, 0, ',', '.') }} đ</div>
                            </div>
                        </div>

                        <div class="ego-order-docs">
                            @forelse($egoOrderDocsOfOrder as $doc)
                                @php
                                    $docTitle = $doc->title ?: $doc->original_name;
                                    $docExt = strtolower(pathinfo($doc->original_name ?: $doc->file_path, PATHINFO_EXTENSION));
                                    $previewUrl = url('/orders/'.$doc->order_id.'/documents-ego/'.$doc->id.'/preview');
                                    $downloadUrl = url('/orders/'.$doc->order_id.'/documents-ego/'.$doc->id.'/download');
                                @endphp

                                <div class="ego-doc-row">
                                    <div class="ego-doc-ext">{{ $docExt ?: 'file' }}</div>

                                    <div style="min-width:0">
                                        <div class="ego-doc-name">{{ $docTitle }}</div>
                                        <div class="ego-doc-meta">
                                            {{ $egoDocTypes[$doc->document_type] ?? $doc->document_type }}
                                            • {{ number_format(($doc->size_bytes ?? 0) / 1024, 1) }} KB
                                            • {{ $doc->created_at ? \Carbon\Carbon::parse($doc->created_at)->format('d/m/Y H:i') : '—' }}
                                        </div>
                                        @if($doc->note)
                                            <div class="ego-doc-meta">{{ $doc->note }}</div>
                                        @endif
                                    </div>

                                    <div class="ego-doc-actions">
                                        <button type="button"
                                                class="ego-doc-btn primary"
                                                data-ego-preview-url="{{ $previewUrl }}"
                                                data-ego-preview-name="{{ $docTitle }}">
                                            Xem tại trang
                                        </button>
                                        <a class="ego-doc-btn" target="_blank" href="{{ $previewUrl }}">Mở tab</a>
                                        <a class="ego-doc-btn" href="{{ $downloadUrl }}">Tải</a>
                                    </div>
                                </div>
                            @empty
                                <div class="ego-empty">
                                    Đơn này chưa có file upload. Vào chi tiết đơn hàng để tải ĐNTT / hợp đồng / chứng từ lên.
                                </div>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <div class="ego-empty">
                        Chưa thấy đơn hàng liên quan tới hồ sơ đại lý này.
                    </div>
                @endforelse
            </div>

            <div class="ego-preview-panel">
                <div class="ego-preview-head">
                    <div class="ego-preview-title">
                        <div class="k">Xem trước tại trang</div>
                        <div class="v" id="egoPreviewName">{{ $egoFirstName ?: 'Chưa chọn file' }}</div>
                    </div>

                    @if($egoFirstPreviewUrl)
                        <a id="egoPreviewOpen" class="ego-doc-btn" target="_blank" href="{{ $egoFirstPreviewUrl }}">Mở tab</a>
                    @else
                        <a id="egoPreviewOpen" class="ego-doc-btn" target="_blank" href="#" style="display:none">Mở tab</a>
                    @endif
                </div>

                @if($egoFirstPreviewUrl)
                    <div class="ego-preview-frame-wrap">
                        <iframe id="egoInlinePreview" class="ego-preview-frame" src="{{ $egoFirstPreviewUrl }}"></iframe>
                    </div>
                @else
                    <div class="ego-preview-empty" id="egoPreviewEmpty">
                        Chưa có file từ đơn hàng để xem trước.
                    </div>
                    <div class="ego-preview-frame-wrap" style="display:none">
                        <iframe id="egoInlinePreview" class="ego-preview-frame" src=""></iframe>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const frame = document.getElementById('egoInlinePreview');
    const nameEl = document.getElementById('egoPreviewName');
    const openEl = document.getElementById('egoPreviewOpen');
    const emptyEl = document.getElementById('egoPreviewEmpty');

    document.addEventListener('click', function(e){
        const btn = e.target.closest('[data-ego-preview-url]');
        if (!btn || !frame) return;

        e.preventDefault();

        const url = btn.getAttribute('data-ego-preview-url');
        const name = btn.getAttribute('data-ego-preview-name') || 'File xem trước';

        frame.src = url;

        if (nameEl) nameEl.textContent = name;

        if (openEl) {
            openEl.href = url;
            openEl.style.display = 'inline-flex';
        }

        if (emptyEl) {
            emptyEl.style.display = 'none';
            const wrap = frame.closest('.ego-preview-frame-wrap');
            if (wrap) wrap.style.display = 'block';
        }

        document.querySelectorAll('[data-ego-preview-url]').forEach(function(x){
            x.classList.remove('is-active');
        });

        btn.classList.add('is-active');
    });
})();
</script>
