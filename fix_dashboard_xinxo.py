from pathlib import Path
from datetime import datetime
import re

target = Path("/home/crmegoso/public_html/resources/views/dashboard/index.blade.php")

if not target.exists():
    raise SystemExit(f"Không tìm thấy file: {target}")

text = target.read_text()
backup = target.with_name(target.name + ".bak_xinxo_" + datetime.now().strftime("%Y%m%d_%H%M%S"))
backup.write_text(text)

print(f"Đang sửa file: {target}")
print(f"Đã backup: {backup}")

# ============================================================
# 1) DỌN MẤY HIỆU ỨNG CŨ ĐANG LÀM XẤU / LÀM NHỎ CHỮ / MẤT CHART
# ============================================================
bad_blocks = [
    r"\n?<style id=\"egoDashboardProFX\">.*?</style>\s*",
    r"\n?<style id=\"egoDashboardProFXSoftHover\">.*?</style>\s*",
    r"\n?<style id=\"DashboardTopSalesListPro\">.*?</style>\s*",
    r"\n?<style id=\"DashboardXinXoPatch\">.*?</style>\s*",
    r"\n?<script id=\"egoDashboardProFXInline\">.*?</script>\s*",
    r"\n?<script id=\"DashboardTopSalesListJS\">.*?</script>\s*",
    r"\n?<script id=\"DashboardXinXoJS\">.*?</script>\s*",
    r"\n?@push\('scripts'\)\s*<script id=\"egoDashboardProFXScript\">.*?</script>\s*@endpush\s*",
]

for p in bad_blocks:
    text = re.sub(p, "\n", text, flags=re.S)

# Xóa particle node nếu từng bị chèn trực tiếp
text = re.sub(r"\s*<span class=\"ego-fx-particle\".*?</span>\s*", "\n", text, flags=re.S)

# ============================================================
# 2) TOP SALES QUERY: CHỈ ROLE SALES / MANAGER / ADMIN, LIMIT 5
# ============================================================
top_sales_new = r'''    $allowedTopSalesRoleSlugs = collect(['sales', 'manager', 'admin']);
    $allowedTopSalesUserIds = collect();

    try {
        // Cách 1: hệ thống lưu role trực tiếp ở users.role
        if ($hasTable('users') && $hasColumn('users', 'role')) {
            $ids = DB::table('users')
                ->select('id', 'role')
                ->get()
                ->filter(function ($u) use ($normalizeRole, $allowedTopSalesRoleSlugs) {
                    return $allowedTopSalesRoleSlugs->contains($normalizeRole($u->role ?? ''));
                })
                ->pluck('id');

            $allowedTopSalesUserIds = $allowedTopSalesUserIds->merge($ids);
        }

        // Cách 2: role_user + roles
        if (
            $hasTable('role_user')
            && $hasTable('roles')
            && $hasColumn('role_user', 'user_id')
            && $hasColumn('role_user', 'role_id')
            && $hasColumn('roles', 'name')
        ) {
            $ids = DB::table('role_user as ru')
                ->join('roles as r', 'r.id', '=', 'ru.role_id')
                ->select('ru.user_id', 'r.name')
                ->get()
                ->filter(function ($row) use ($normalizeRole, $allowedTopSalesRoleSlugs) {
                    return $allowedTopSalesRoleSlugs->contains($normalizeRole($row->name ?? ''));
                })
                ->pluck('user_id');

            $allowedTopSalesUserIds = $allowedTopSalesUserIds->merge($ids);
        }

        // Cách 3: Spatie model_has_roles + roles
        if (
            $hasTable('model_has_roles')
            && $hasTable('roles')
            && $hasColumn('model_has_roles', 'model_id')
            && $hasColumn('model_has_roles', 'role_id')
            && $hasColumn('roles', 'name')
        ) {
            $ids = DB::table('model_has_roles as mhr')
                ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                ->select('mhr.model_id', 'r.name')
                ->get()
                ->filter(function ($row) use ($normalizeRole, $allowedTopSalesRoleSlugs) {
                    return $allowedTopSalesRoleSlugs->contains($normalizeRole($row->name ?? ''));
                })
                ->pluck('model_id');

            $allowedTopSalesUserIds = $allowedTopSalesUserIds->merge($ids);
        }

        $allowedTopSalesUserIds = $allowedTopSalesUserIds
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    } catch (\Throwable $e) {
        $allowedTopSalesUserIds = collect();
    }

    $topSales = collect();

    if ($hasTable('crm_orders') && $hasColumn('crm_orders', 'total_amount')) {
        try {
            $q = DB::table('crm_orders as o')
                ->leftJoin('users as u', 'u.id', '=', 'o.created_by')
                ->selectRaw("
                    o.created_by as user_id,
                    COALESCE(u.name, 'Không xác định') as name,
                    SUM(o.total_amount) as revenue,
                    COUNT(o.id) as orders
                ");

            $applyOrderFilters($q, 'o');

            if ($allowedTopSalesUserIds->isNotEmpty()) {
                $q->whereIn('o.created_by', $allowedTopSalesUserIds->all());
            } else {
                $q->whereRaw('1 = 0');
            }

            $topSales = $q
                ->groupBy('o.created_by', 'u.name')
                ->orderByDesc('revenue')
                ->limit(5)
                ->get();
        } catch (\Throwable $e) {
            $topSales = collect();
        }
    }

    $topSalesLabels = $topSales->pluck('name')->values();
    $topSalesValues = $topSales->pluck('revenue')->map(fn ($v) => (float) $v)->values();

'''

patterns = [
    re.compile(
        r"    \$allowedTopSalesRoleSlugs = collect\(\['sales', 'manager', 'admin'\]\);.*?"
        r"    \$topSalesValues = \$topSales->pluck\('revenue'\)->map\(fn \(\$v\) => \(float\) \$v\)->values\(\);\s*",
        re.S
    ),
    re.compile(
        r"    \$topSales = collect\(\);\s*"
        r"if \(\$hasTable\('crm_orders'\) && \$hasColumn\('crm_orders', 'total_amount'\)\) \{.*?"
        r"    \$topSalesLabels = \$topSales->pluck\('name'\)->values\(\);\s*"
        r"\$topSalesValues = \$topSales->pluck\('revenue'\)->map\(fn \(\$v\) => \(float\) \$v\)->values\(\);\s*",
        re.S
    )
]

patched_query = False
for pattern in patterns:
    text, n = pattern.subn(top_sales_new, text, count=1)
    if n:
        patched_query = True
        break

if patched_query:
    print("OK: Đã sửa query Top Sales.")
else:
    print("WARN: Không tìm thấy block query Top Sales để thay. Sẽ vẫn sửa giao diện Top Sales nếu tìm thấy panel.")

# ============================================================
# 3) THAY PANEL TOP SALES CHART THÀNH LIST SỐ XỊN HƠN
# ============================================================
new_top_panel = r'''        <div class="panel top-sales-panel">
            <div class="section-head">
                <div>
                    <h3 class="section-title"><i class="bi bi-trophy"></i> Top 5 Sales / Manager / Admin</h3>
                    <div class="section-sub">Chỉ tính user có role sales, manager hoặc admin.</div>
                </div>
                <span class="mini-badge top-sales-badge">Top 5</span>
            </div>

            <div class="panel-pad">
                @php
                    $topMaxRevenue = max(1, (float) $topSales->max('revenue'));
                @endphp

                <div class="top-sales-list">
                    @forelse($topSales as $index => $item)
                        @php
                            $itemName = (string)($item->name ?? 'Không xác định');
                            $nameParts = collect(preg_split('/\s+/u', trim($itemName)))->filter()->values();
                            $initial = $nameParts->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                            $initial = $initial ?: 'S';
                            $revenue = (float)($item->revenue ?? 0);
                            $orders = (int)($item->orders ?? 0);
                            $barWidth = min(100, max(5, $revenue / $topMaxRevenue * 100));
                        @endphp

                        <div class="top-sales-row top-sales-row-{{ $index + 1 }}" style="--bar:{{ $barWidth }}%;">
                            <div class="top-sales-rank">#{{ $index + 1 }}</div>

                            <div class="top-sales-avatar">
                                {{ $initial }}
                            </div>

                            <div class="top-sales-main">
                                <div class="top-sales-line">
                                    <div>
                                        <div class="top-sales-name">{{ $itemName }}</div>
                                        <div class="top-sales-meta">{{ $num($orders) }} đơn hàng thương mại</div>
                                    </div>

                                    <div class="top-sales-value js-money-count" data-money="{{ (int)$revenue }}">
                                        {{ $money($revenue) }}
                                    </div>
                                </div>

                                <div class="top-sales-bar">
                                    <span></span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="top-sales-empty">
                            <i class="bi bi-info-circle"></i>
                            Chưa có dữ liệu top sales thuộc role Sales / Manager / Admin.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>'''

# Nếu đã có panel top-sales-panel, thay lại cho sạch
existing_top_panel = re.compile(
    r"        <div class=\"panel top-sales-panel\">.*?\n        </div>\s*\n    </div>",
    re.S
)

if "top-sales-panel" in text:
    text, n = existing_top_panel.subn(new_top_panel + "\n    </div>", text, count=1)
    if n:
        print("OK: Đã refresh panel Top Sales list.")
else:
    idx = text.find('id="topSalesChart"')

    if idx != -1:
        # tìm div.panel đang chứa topSalesChart
        div_re = re.compile(r"<div\b[^>]*>|</div>", re.I)
        stack = []
        panel_start = None

        for m in div_re.finditer(text, 0, idx):
            token = m.group(0).lower()
            if token.startswith("<div"):
                stack.append((m.start(), m.group(0)))
            else:
                if stack:
                    stack.pop()

        for pos, tag in reversed(stack):
            if 'class="panel' in tag or "class='panel" in tag:
                panel_start = pos
                break

        if panel_start is None:
            print("WARN: Không tìm được div.panel chứa topSalesChart.")
        else:
            depth = 0
            panel_end = None
            for m in div_re.finditer(text, panel_start):
                if m.group(0).lower().startswith("<div"):
                    depth += 1
                else:
                    depth -= 1
                    if depth == 0:
                        panel_end = m.end()
                        break

            if panel_end:
                text = text[:panel_start] + new_top_panel + text[panel_end:]
                print("OK: Đã đổi Top Sales chart thành list số.")
            else:
                print("WARN: Không tìm được điểm kết thúc panel Top Sales.")
    else:
        print("WARN: Không thấy topSalesChart. Có thể bạn đã xóa panel Top Sales trước đó.")

# ============================================================
# 4) THÊM CSS XỊN NHẸ, KHÔNG LÀM NHỎ TOÀN TRANG
# ============================================================
xinxo_css = r'''
<style id="DashboardXinXoPatch">
    /* Patch xịn nhẹ: không đụng font toàn site, chỉ scope trong dashboard */
    #egoDashboard{
        position:relative;
        overflow:hidden;
        font-size:14px;
    }

    #egoDashboard::before{
        content:"";
        position:absolute;
        inset:0;
        pointer-events:none;
        z-index:0;
        background:
            radial-gradient(circle at var(--mx, 18%) var(--my, 18%), rgba(15,190,169,.11), transparent 20%),
            radial-gradient(circle at 86% 8%, rgba(56,189,248,.10), transparent 22%);
        transition:background .18s ease;
    }

    #egoDashboard > *{
        position:relative;
        z-index:1;
    }

    #egoDashboard .filter-label{
        font-size:12px;
        font-weight:800;
    }

    #egoDashboard .filter-control{
        font-size:14px;
        font-weight:700;
        min-height:44px;
        transition:border-color .18s ease, box-shadow .18s ease, transform .18s ease;
    }

    #egoDashboard .filter-control:focus{
        transform:translateY(-1px);
        border-color:rgba(15,190,169,.55);
        box-shadow:0 0 0 4px rgba(15,190,169,.08);
    }

    #egoDashboard .kpi,
    #egoDashboard .panel,
    #egoDashboard .hero-sales,
    #egoDashboard .dept-card,
    #egoDashboard .hero-metric,
    #egoDashboard .insight-item{
        transition:transform .22s ease, box-shadow .22s ease, border-color .22s ease;
    }

    #egoDashboard .kpi:hover,
    #egoDashboard .panel:hover,
    #egoDashboard .hero-sales:hover,
    #egoDashboard .dept-card:hover,
    #egoDashboard .hero-metric:hover,
    #egoDashboard .insight-item:hover{
        transform:translateY(-2px);
        box-shadow:0 16px 38px rgba(15,23,42,.09);
        border-color:rgba(15,190,169,.22);
    }

    #egoDashboard .chart-box canvas{
        display:block!important;
    }

    #egoDashboard #salesTrendChart{
        display:block!important;
    }

    #egoDashboard .top-sales-panel{
        min-height:430px;
    }

    #egoDashboard .top-sales-badge{
        color:#0f766e;
        background:#ecfdf5;
        border-color:#c7f5e5;
    }

    #egoDashboard .top-sales-list{
        display:grid;
        gap:11px;
    }

    #egoDashboard .top-sales-row{
        display:flex;
        align-items:center;
        gap:11px;
        padding:12px;
        border:1px solid #e6edf5;
        border-radius:18px;
        background:
            linear-gradient(135deg, rgba(255,255,255,.96), rgba(248,250,252,.92));
        transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        position:relative;
        overflow:hidden;
    }

    #egoDashboard .top-sales-row::after{
        content:"";
        position:absolute;
        inset:0;
        pointer-events:none;
        background:linear-gradient(120deg, transparent, rgba(15,190,169,.08), transparent);
        transform:translateX(-120%);
        transition:transform .55s ease;
    }

    #egoDashboard .top-sales-row:hover{
        transform:translateY(-2px);
        border-color:rgba(15,190,169,.28);
        box-shadow:0 14px 28px rgba(15,23,42,.07);
    }

    #egoDashboard .top-sales-row:hover::after{
        transform:translateX(120%);
    }

    #egoDashboard .top-sales-rank{
        width:42px;
        height:42px;
        border-radius:15px;
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:13px;
        font-weight:950;
        color:#0f766e;
        background:linear-gradient(135deg, rgba(15,190,169,.13), rgba(56,189,248,.10));
        border:1px solid rgba(15,190,169,.18);
        flex:0 0 auto;
    }

    #egoDashboard .top-sales-row-1 .top-sales-rank{
        color:#b45309;
        background:linear-gradient(135deg, rgba(245,158,11,.16), rgba(251,191,36,.10));
        border-color:rgba(245,158,11,.24);
    }

    #egoDashboard .top-sales-avatar{
        width:42px;
        height:42px;
        border-radius:999px;
        display:flex;
        align-items:center;
        justify-content:center;
        flex:0 0 auto;
        color:#fff;
        font-size:13px;
        font-weight:950;
        background:linear-gradient(135deg, #0fbea9, #38bdf8);
        box-shadow:0 10px 20px rgba(15,190,169,.18);
    }

    #egoDashboard .top-sales-main{
        flex:1;
        min-width:0;
    }

    #egoDashboard .top-sales-line{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:10px;
    }

    #egoDashboard .top-sales-name{
        color:#0f172a;
        font-size:14px;
        font-weight:900;
        line-height:1.25;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
        max-width:210px;
    }

    #egoDashboard .top-sales-meta{
        margin-top:2px;
        color:#64748b;
        font-size:12px;
        font-weight:750;
    }

    #egoDashboard .top-sales-value{
        color:#0f766e;
        font-size:14px;
        font-weight:950;
        white-space:nowrap;
        text-align:right;
    }

    #egoDashboard .top-sales-bar{
        height:8px;
        margin-top:9px;
        border-radius:999px;
        background:#eef6f8;
        overflow:hidden;
    }

    #egoDashboard .top-sales-bar span{
        display:block;
        height:100%;
        width:var(--bar);
        border-radius:999px;
        background:linear-gradient(90deg, #0fbea9, #38bdf8);
        transform-origin:left;
        animation:topSalesGrow .9s ease both;
    }

    @keyframes topSalesGrow{
        from{transform:scaleX(0);}
        to{transform:scaleX(1);}
    }

    #egoDashboard .top-sales-empty{
        padding:24px 12px;
        border-radius:18px;
        background:#f8fafc;
        border:1px dashed #dbe6ef;
        color:#64748b;
        text-align:center;
        font-weight:800;
        font-size:13px;
    }

    #egoDashboard .dashboard-reveal{
        opacity:0;
        transform:translateY(12px);
        transition:opacity .45s ease, transform .45s ease;
    }

    #egoDashboard .dashboard-reveal.show{
        opacity:1;
        transform:none;
    }

    body.ego-nova-dark #egoDashboard .top-sales-row{
        background:rgba(15,23,42,.55);
        border-color:rgba(255,255,255,.10);
    }

    body.ego-nova-dark #egoDashboard .top-sales-name{
        color:#e5eefb;
    }

    body.ego-nova-dark #egoDashboard .top-sales-value{
        color:#67e8f9;
    }

    body.ego-nova-dark #egoDashboard .top-sales-bar{
        background:rgba(255,255,255,.08);
    }

    @media(max-width:576px){
        #egoDashboard .top-sales-line{
            display:block;
        }

        #egoDashboard .top-sales-value{
            margin-top:4px;
            text-align:left;
        }

        #egoDashboard .top-sales-name{
            max-width:100%;
        }
    }
</style>
'''

text = re.sub(r"\n?<style id=\"DashboardXinXoPatch\">.*?</style>\s*", "\n", text, flags=re.S)

if '<div id="egoDashboard">' in text:
    text = text.replace('<div id="egoDashboard">', xinxo_css + "\n<div id=\"egoDashboard\">", 1)
    print("OK: Đã thêm CSS xịn nhẹ.")
else:
    print("WARN: Không tìm thấy <div id=\"egoDashboard\"> để chèn CSS.")

# ============================================================
# 5) JS NHẸ: REVEAL + COUNT MONEY + MOUSE GLOW, NHÚNG TRỰC TIẾP
# ============================================================
xinxo_js = r'''
<script id="DashboardXinXoJS">
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('egoDashboard');
    if (!root || root.dataset.xinxo === '1') return;
    root.dataset.xinxo = '1';

    const revealItems = root.querySelectorAll('.kpi, .panel, .hero-sales, .dept-card, .hero-metric, .insight-item');
    revealItems.forEach((el, index) => {
        el.classList.add('dashboard-reveal');
        setTimeout(() => el.classList.add('show'), 45 * index + 80);
    });

    root.addEventListener('mousemove', function (event) {
        const rect = root.getBoundingClientRect();
        const x = ((event.clientX - rect.left) / Math.max(1, rect.width)) * 100;
        const y = ((event.clientY - rect.top) / Math.max(1, rect.height)) * 100;
        root.style.setProperty('--mx', x.toFixed(2) + '%');
        root.style.setProperty('--my', y.toFixed(2) + '%');
    }, { passive: true });

    function formatMoney(value) {
        return new Intl.NumberFormat('vi-VN').format(Math.round(Number(value || 0))) + ' đ';
    }

    const moneyEls = root.querySelectorAll('.js-money-count[data-money]');
    moneyEls.forEach((el) => {
        const target = Number(el.dataset.money || 0);
        if (!Number.isFinite(target) || target <= 0) return;

        let start = null;
        const duration = 850;

        function step(timestamp) {
            if (!start) start = timestamp;
            const progress = Math.min(1, (timestamp - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = formatMoney(target * eased);

            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = formatMoney(target);
            }
        }

        requestAnimationFrame(step);
    });

    console.log('Dashboard xin xo patch loaded');
});
</script>
'''

text = re.sub(r"\n?<script id=\"DashboardXinXoJS\">.*?</script>\s*", "\n", text, flags=re.S)

marker = "@endsection\n\n@push('scripts')"
if marker in text:
    text = text.replace(marker, xinxo_js + "\n@endsection\n\n@push('scripts')", 1)
    print("OK: Đã thêm JS xịn nhẹ trực tiếp trước @push scripts.")
else:
    # fallback: chèn trước @endsection đầu tiên sau dashboard
    end_pos = text.find("@endsection")
    if end_pos != -1:
        text = text[:end_pos] + xinxo_js + "\n" + text[end_pos:]
        print("OK: Đã thêm JS xịn nhẹ bằng fallback.")
    else:
        print("WARN: Không tìm thấy @endsection để chèn JS.")

# ============================================================
# 6) ĐẢM BẢO BIỂU ĐỒ DOANH THU VẪN CÒN
# ============================================================
if 'id="salesTrendChart"' not in text:
    print("WARN: Không thấy salesTrendChart. Biểu đồ doanh thu có thể đã bị xóa trước đó.")
else:
    print("OK: Biểu đồ doanh thu salesTrendChart vẫn còn.")

if 'id="topSalesChart"' in text:
    print("WARN: topSalesChart vẫn còn. Có thể panel chưa được thay.")
else:
    print("OK: Top Sales chart đã bỏ, chuyển sang list số.")

target.write_text(text)

print("DONE: Đã patch dashboard xịn xò.")
