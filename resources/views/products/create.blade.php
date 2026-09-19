@extends('layouts.app')

@section('title', 'Thêm sản phẩm mới')

@php
    $categories = $categories ?? collect();
    $brands = $brands ?? collect();
    $priceTiers = $priceTiers ?? collect();
    $warehouses = collect($warehouses ?? []);

    if ($warehouses->isEmpty()) {
        $warehouses = collect($companyWarehouses ?? [])->flatMap(fn($items) => collect($items))->values();
    }

    $warehouseOptions = $warehouses->map(fn($warehouse) => [
        'id' => (int) $warehouse->id,
        'name' => (string) $warehouse->name,
    ])->values();

    $oldLines = collect(old('v2_lines', []))->values();
@endphp

@section('content')
<style>
    :root{
        --pc-navy:#0b1f38;--pc-teal:#079b96;--pc-teal2:#0bb4ae;--pc-bg:#f3f7fb;--pc-card:#fff;
        --pc-text:#132c48;--pc-muted:#6d7f92;--pc-line:#dce7f0;--pc-soft:#eafaf9;--pc-danger:#e84a5f;
        --pc-shadow:0 12px 34px rgba(19,42,67,.07)
    }
    .pc-page{padding:16px 18px 34px;background:var(--pc-bg);min-height:calc(100vh - 72px)}
    .pc-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:13px}
    .pc-eyebrow{font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.11em;color:#078f8a;margin-bottom:6px}
    .pc-title{font-size:25px;line-height:1.15;font-weight:950;letter-spacing:-.035em;color:var(--pc-navy);margin:0}
    .pc-sub{font-size:12px;color:var(--pc-muted);font-weight:650;margin-top:5px}
    .pc-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
    .pc-btn{height:38px;border:1px solid var(--pc-line);border-radius:11px;background:#fff;color:var(--pc-text);font-size:12px;font-weight:900;padding:0 13px;display:inline-flex;align-items:center;justify-content:center;gap:7px;text-decoration:none;cursor:pointer;transition:.16s;white-space:nowrap}
    .pc-btn:hover{transform:translateY(-1px);box-shadow:0 8px 18px rgba(15,23,42,.08);color:var(--pc-text)}
    .pc-btn-primary{background:linear-gradient(135deg,var(--pc-teal2),var(--pc-teal));border-color:transparent;color:#fff;box-shadow:0 9px 20px rgba(7,155,150,.22)}.pc-btn-primary:hover{color:#fff}
    .pc-btn-danger{background:#fff2f3;color:#d83d53;border-color:#ffd1d7}.pc-btn-soft{background:var(--pc-soft);color:#087b77;border-color:#bfebe8}
    .pc-scope{display:flex;align-items:center;gap:8px;background:#fff;border:1px solid var(--pc-line);border-radius:13px;padding:10px 12px;margin-bottom:12px;box-shadow:0 6px 18px rgba(19,42,67,.04)}
    .pc-scope-icon{width:34px;height:34px;border-radius:10px;background:var(--pc-soft);display:flex;align-items:center;justify-content:center;color:#07847f}.pc-scope strong{font-size:12px;color:var(--pc-navy)}.pc-scope span{font-size:11px;color:var(--pc-muted);margin-left:4px}
    .pc-layout{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:12px;align-items:start}
    .pc-card{background:#fff;border:1px solid var(--pc-line);border-radius:16px;box-shadow:var(--pc-shadow);overflow:hidden;margin-bottom:12px}
    .pc-card-head{padding:12px 14px;border-bottom:1px solid var(--pc-line);display:flex;align-items:center;justify-content:space-between;gap:10px;background:linear-gradient(135deg,#fbfefe,#f3fbfb)}
    .pc-card-title{font-size:15px;font-weight:950;color:var(--pc-navy);margin:0}.pc-card-sub{font-size:11px;color:var(--pc-muted);font-weight:650;margin-top:2px}.pc-card-body{padding:13px}
    .pc-grid{display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:10px}.pc-field label{display:block;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.055em;color:#52677d;margin-bottom:5px}.pc-required{color:#e64a5e}
    .pc-control{height:39px;width:100%;border:1px solid var(--pc-line);border-radius:11px;background:#fff;padding:0 11px;color:#17304d;font-size:12px;font-weight:750;outline:none}.pc-control:focus,.pc-textarea:focus{border-color:#68dcd6;box-shadow:0 0 0 4px rgba(7,155,150,.10)}
    .pc-textarea{width:100%;min-height:82px;border:1px solid var(--pc-line);border-radius:11px;background:#fff;padding:9px 11px;color:#17304d;font-size:12px;font-weight:700;outline:none;resize:vertical}
    .pc-line{border:1px solid var(--pc-line);border-radius:15px;margin-top:10px;overflow:hidden;background:#fff;box-shadow:0 6px 18px rgba(19,42,67,.035)}
    .pc-line-head{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:9px 11px;background:#f7fbfc;border-bottom:1px solid var(--pc-line)}
    .pc-line-name{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:950;color:var(--pc-navy)}.pc-line-no{width:26px;height:26px;border-radius:999px;background:#dff7f5;color:#07827d;display:flex;align-items:center;justify-content:center}
    .pc-line-body{padding:11px}.pc-line-primary{display:grid;grid-template-columns:1.15fr 1.15fr .62fr .82fr .55fr;gap:8px;align-items:end}.pc-line-secondary{display:grid;grid-template-columns:.72fr .72fr 1.4fr;gap:8px;align-items:end;margin-top:8px}.pc-line-tertiary{display:grid;grid-template-columns:1.15fr 1fr;gap:8px;margin-top:8px}
    .pc-readonly{height:39px;border:1px solid #b9ebe7;border-radius:11px;background:#edfbfa;padding:10px 11px;text-align:right;font-size:12px;font-weight:950;color:#087e79}
    .pc-serial{border:1px dashed #9ddfd9;border-radius:12px;background:#f6fdfd;padding:9px}.pc-serial-help{font-size:10px;color:#087a76;font-weight:750;margin-top:5px}
    .pc-line-total{display:flex;justify-content:flex-end;gap:14px;flex-wrap:wrap;margin-top:8px;padding-top:8px;border-top:1px dashed #d7e4ec;font-size:11px;color:var(--pc-muted);font-weight:750}.pc-line-total b{color:#087c77}
    .pc-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:9px;margin-top:11px}.pc-summary-item{border:1px solid var(--pc-line);border-radius:13px;background:#fff;padding:10px;text-align:center}.pc-summary-item span{display:block;font-size:9px;font-weight:900;text-transform:uppercase;color:var(--pc-muted)}.pc-summary-item b{display:block;font-size:17px;color:var(--pc-navy);margin-top:3px}
    .pc-price-card{position:sticky;top:84px}.pc-price-row{padding:10px;border:1px solid var(--pc-line);border-radius:13px;margin-bottom:8px;background:#fff}.pc-price-title{font-size:11px;font-weight:950;color:var(--pc-navy);margin-bottom:7px}.pc-price-grid{display:grid;grid-template-columns:1fr .58fr 1fr;gap:6px}.pc-price-grid small{display:block;font-size:8px;font-weight:900;text-transform:uppercase;color:var(--pc-muted);margin-bottom:4px}
    .pc-preview{overflow:auto}.pc-table{width:100%;min-width:1080px;border-collapse:collapse}.pc-table th{background:#f4f8fb;border-bottom:1px solid var(--pc-line);padding:9px;font-size:9px;font-weight:900;text-transform:uppercase;color:#617589;white-space:nowrap}.pc-table td{padding:9px;border-bottom:1px solid #edf2f6;font-size:11px;color:#233d59}.pc-table td:last-child{white-space:normal}.pc-tag{display:inline-flex;border-radius:999px;padding:4px 7px;background:#eaf9f8;color:#087b77;font-weight:900}
    .pc-footer{position:sticky;bottom:0;z-index:8;display:flex;justify-content:flex-end;gap:8px;padding:10px 0 0;background:rgba(243,247,251,.9);backdrop-filter:blur(9px)}
    @media(max-width:1200px){.pc-layout{grid-template-columns:1fr}.pc-price-card{position:static}.pc-line-primary{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:760px){.pc-page{padding:12px}.pc-head{display:block}.pc-actions{justify-content:flex-start;margin-top:10px}.pc-grid,.pc-line-primary,.pc-line-secondary,.pc-line-tertiary,.pc-summary,.pc-price-grid{grid-template-columns:1fr}.pc-title{font-size:21px}}
</style>

<div class="pc-page">
    <div class="pc-head">
        <div><div class="pc-eyebrow">Kho & Sản phẩm</div><h1 class="pc-title">Thêm sản phẩm mới</h1><div class="pc-sub">Tạo SKU, chọn kho, nhập giá vốn và số lượng trong cùng một quy trình.</div></div>
        <div class="pc-actions">
            <a href="{{ route('products.input') }}" class="pc-btn"><i class="bi bi-arrow-left"></i> Danh sách</a>
            @if(Route::has('product-goods-receipts.index'))<a href="{{ route('product-goods-receipts.index') }}" class="pc-btn pc-btn-soft"><i class="bi bi-receipt"></i> Nhập sản phẩm</a>@endif
            <button type="submit" form="productCreateForm" class="pc-btn pc-btn-primary"><i class="bi bi-check2-circle"></i> Lưu sản phẩm</button>
        </div>
    </div>

    <div class="pc-scope"><div class="pc-scope-icon"><i class="bi bi-shield-check"></i></div><div><strong>Phạm vi: Công ty Quốc Tế EGO</strong><span>Không cần chọn công ty. Mỗi dòng chỉ cần chọn kho nhập.</span></div></div>

    @if(session('success'))<div class="alert alert-success border-0 rounded-3 shadow-sm py-2">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger border-0 rounded-3 shadow-sm py-2">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger border-0 rounded-3 shadow-sm py-2"><b>Vui lòng kiểm tra:</b><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <form id="productCreateForm" action="{{ route('products.store') }}" method="POST">
        @csrf
        <input type="hidden" name="sku" id="mainSku" value="">
        <input type="hidden" name="price_agent" id="mainCost" value="0">
        <input type="hidden" name="cost_vat_percent" id="mainVat" value="0">

        <div class="pc-layout">
            <div>
                <div class="pc-card">
                    <div class="pc-card-head"><div><h2 class="pc-card-title">Thông tin chung</h2><div class="pc-card-sub">Tên dùng chung; mỗi dòng bên dưới là một SKU và một lần nhập kho riêng.</div></div><button type="button" id="addLineBtn" class="pc-btn pc-btn-primary"><i class="bi bi-plus-lg"></i> Thêm SKU</button></div>
                    <div class="pc-card-body">
                        <div class="pc-grid">
                            <div class="pc-field"><label>Tên sản phẩm <span class="pc-required">*</span></label><input class="pc-control" name="name" id="productName" value="{{ old('name') }}" required placeholder="Ví dụ: Pin lưu trữ PowerBrick SC"></div>
                            <div class="pc-field"><label>Danh mục</label><select class="pc-control" name="category_id"><option value="">Chọn danh mục</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('category_id') === (string)$category->id)>{{ $category->name }}</option>@endforeach</select></div>
                            <div class="pc-field"><label>Thương hiệu</label><select class="pc-control" name="brand_id"><option value="">Chọn thương hiệu</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((string)old('brand_id') === (string)$brand->id)>{{ $brand->name }}</option>@endforeach</select></div>
                        </div>
                        <div id="linesContainer"></div>
                        <div class="pc-summary">
                            <div class="pc-summary-item"><span>Số dòng SKU</span><b id="sumLines">0</b></div>
                            <div class="pc-summary-item"><span>Tổng số lượng</span><b id="sumQty">0</b></div>
                            <div class="pc-summary-item"><span>Tổng giá vốn</span><b id="sumAmount">0 đ</b></div>
                            <div class="pc-summary-item"><span>SKU đầu tiên</span><b id="sumSku">—</b></div>
                        </div>
                    </div>
                </div>

                <div class="pc-card">
                    <div class="pc-card-head"><div><h2 class="pc-card-title">Kiểm tra trước khi lưu</h2><div class="pc-card-sub">Bảng xem nhanh các dòng SKU sẽ được tạo hoặc nhập thêm.</div></div></div>
                    <div class="pc-preview"><table class="pc-table"><thead><tr><th>STT</th><th>Tên sản phẩm</th><th>SKU</th><th>Kho nhập</th><th>Ngày nhập</th><th>Giá trước VAT</th><th>VAT</th><th>Giá thực tế</th><th>SL</th><th>Tổng vốn</th><th>Ghi chú</th></tr></thead><tbody id="previewBody"></tbody></table></div>
                </div>

                <div class="pc-footer"><a href="{{ route('products.input') }}" class="pc-btn">Hủy</a><button type="submit" class="pc-btn pc-btn-primary"><i class="bi bi-check2-circle"></i> Lưu sản phẩm</button></div>
            </div>

            <div>
                <div class="pc-card pc-price-card">
                    <div class="pc-card-head"><div><h2 class="pc-card-title">Giá bán</h2><div class="pc-card-sub">Thiết lập giá mặc định và giá theo cấp đại lý.</div></div></div>
                    <div class="pc-card-body">
                        <div class="pc-price-row sale-row"><div class="pc-price-title">Giá bán mặc định</div><div class="pc-price-grid"><div><small>Trước VAT</small><input class="pc-control sale-before" type="number" min="0" step="0.01" name="price_retail" value="{{ old('price_retail',0) }}"></div><div><small>VAT</small><input class="pc-control sale-vat" type="number" min="0" max="100" step="0.01" name="vat_percent" value="{{ old('vat_percent',0) }}"></div><div><small>Sau VAT</small><input class="pc-control sale-after" readonly value="0 đ"></div></div></div>
                        @foreach($priceTiers as $tier)
                            <div class="pc-price-row sale-row"><div class="pc-price-title">{{ $tier->name }}</div><div class="pc-price-grid"><div><small>Trước VAT</small><input class="pc-control sale-before" type="number" min="0" step="0.01" name="prices[{{ $tier->id }}][before_vat]" value="{{ old('prices.'.$tier->id.'.before_vat') }}"></div><div><small>VAT</small><input class="pc-control sale-vat" type="number" min="0" max="100" step="0.01" name="prices[{{ $tier->id }}][vat_percent]" value="{{ old('prices.'.$tier->id.'.vat_percent',0) }}"></div><div><small>Sau VAT</small><input class="pc-control sale-after" readonly value="0 đ"></div></div></div>
                        @endforeach
                        <div class="pc-price-row"><div class="pc-price-title">Ghi chú chung</div><textarea class="pc-textarea" name="note" placeholder="Thông tin kỹ thuật, quy cách, lưu ý bán hàng...">{{ old('note') }}</textarea></div>
                        <label style="display:flex;align-items:center;gap:8px;font-size:11px;font-weight:850;color:#496076"><input type="checkbox" name="is_serialized" value="1" @checked(old('is_serialized'))> Sản phẩm quản lý serial/IMEI</label>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(function(){
    const COMPANY_ID = 2;
    const warehouses = @json($warehouseOptions);
    const oldLines = @json($oldLines);
    const container = document.getElementById('linesContainer');
    const previewBody = document.getElementById('previewBody');

    function esc(value){return String(value ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
    function num(value){return Number(String(value ?? '').replace(/,/g,'')) || 0;}
    function money(value){return Math.round(Number(value || 0)).toLocaleString('vi-VN') + ' đ';}
    function serialCount(value){return String(value || '').split(/[\r\n,;]+/).map(v => v.trim()).filter(Boolean).length;}
    function warehouseOptions(selected){let html='<option value="">Chọn kho nhập hàng</option>';warehouses.forEach(w=>{html+=`<option value="${esc(w.id)}" ${String(selected||'')===String(w.id)?'selected':''}>${esc(w.name)}</option>`});return html;}
    function defaultLine(){return {sku:'',warehouse_id:'',qty_in:1,received_at:'{{ now()->toDateString() }}',cost_before_vat:0,cost_vat_percent:0,extra_cost:0,note:'',serials:''};}

    function lineTemplate(index, data){
        data = Object.assign(defaultLine(), data || {});
        return `<div class="pc-line" data-index="${index}">
            <div class="pc-line-head"><div class="pc-line-name"><span class="pc-line-no">${index+1}</span><span>SKU / Dòng nhập ${index+1}</span></div><button type="button" class="pc-btn pc-btn-danger js-remove"><i class="bi bi-trash3"></i> Xóa</button></div>
            <div class="pc-line-body">
                <input type="hidden" name="v2_lines[${index}][company_id]" value="${COMPANY_ID}">
                <div class="pc-line-primary">
                    <div class="pc-field"><label>Mã SKU <span class="pc-required">*</span></label><input class="pc-control js-sku" name="v2_lines[${index}][sku]" value="${esc(data.sku)}" required placeholder="VD: PBSC-001-A"></div>
                    <div class="pc-field"><label>Kho nhập <span class="pc-required">*</span></label><select class="pc-control js-warehouse" name="v2_lines[${index}][warehouse_id]" required>${warehouseOptions(data.warehouse_id)}</select></div>
                    <div class="pc-field"><label>Số lượng</label><input class="pc-control js-qty" type="number" min="0" step="1" name="v2_lines[${index}][qty_in]" value="${esc(data.qty_in)}"></div>
                    <div class="pc-field"><label>Giá trước VAT</label><input class="pc-control js-cost" type="number" min="0" step="0.01" name="v2_lines[${index}][cost_before_vat]" value="${esc(data.cost_before_vat)}"></div>
                    <div class="pc-field"><label>VAT %</label><input class="pc-control js-vat" type="number" min="0" max="100" step="0.01" name="v2_lines[${index}][cost_vat_percent]" value="${esc(data.cost_vat_percent)}"></div>
                </div>
                <div class="pc-line-secondary">
                    <div class="pc-field"><label>Ngày nhập</label><input class="pc-control js-date" type="date" name="v2_lines[${index}][received_at]" value="${esc(data.received_at)}"></div>
                    <div class="pc-field"><label>Chi phí cộng/trừ</label><input class="pc-control js-extra" type="number" step="0.01" name="v2_lines[${index}][extra_cost]" value="${esc(data.extra_cost)}"></div>
                    <div class="pc-field"><label>Ghi chú dòng nhập</label><input class="pc-control js-note" name="v2_lines[${index}][note]" value="${esc(data.note)}" placeholder="Vận chuyển, bốc xếp, nguồn nhập..."></div>
                </div>
                <div class="pc-line-tertiary">
                    <div class="pc-serial"><div class="pc-field"><label>Serial / IMEI</label><textarea class="pc-textarea js-serials" name="v2_lines[${index}][serials]" placeholder="Mỗi dòng một mã serial">${esc(data.serials)}</textarea></div><div class="pc-serial-help">Khi có serial, số lượng sẽ tự đồng bộ theo số mã đã nhập.</div></div>
                    <div><div class="pc-field"><label>Giá sau VAT</label><div class="pc-readonly js-after">0 đ</div></div><div class="pc-line-total"><span>Giá thực tế/cái: <b class="js-actual">0 đ</b></span><span>Tổng dòng: <b class="js-total">0 đ</b></span></div></div>
                </div>
            </div>
        </div>`;
    }

    function reindex(){container.querySelectorAll('.pc-line').forEach((line,index)=>{line.dataset.index=index;line.querySelector('.pc-line-no').textContent=index+1;line.querySelector('.pc-line-name span:last-child').textContent='SKU / Dòng nhập '+(index+1);line.querySelectorAll('[name]').forEach(el=>{el.name=el.name.replace(/v2_lines\[\d+\]/,'v2_lines['+index+']')})})}
    function calculate(line){const serials=serialCount(line.querySelector('.js-serials').value);if(serials>0) line.querySelector('.js-qty').value=serials;const qty=Math.max(0,num(line.querySelector('.js-qty').value));const cost=num(line.querySelector('.js-cost').value);const vat=num(line.querySelector('.js-vat').value);const extra=num(line.querySelector('.js-extra').value);const after=cost*(1+vat/100);const actual=qty>0?after+(extra/qty):after;const total=actual*qty;line.dataset.after=after;line.dataset.actual=actual;line.dataset.total=total;line.querySelector('.js-after').textContent=money(after);line.querySelector('.js-actual').textContent=money(actual);line.querySelector('.js-total').textContent=money(total)}
    function syncMain(){const first=container.querySelector('.pc-line');if(!first)return;document.getElementById('mainSku').value=first.querySelector('.js-sku').value||'';document.getElementById('mainCost').value=first.querySelector('.js-cost').value||0;document.getElementById('mainVat').value=first.querySelector('.js-vat').value||0}
    function refresh(){let totalQty=0,totalAmount=0,firstSku='—';previewBody.innerHTML='';container.querySelectorAll('.pc-line').forEach((line,index)=>{calculate(line);const sku=line.querySelector('.js-sku').value||'';const warehouse=line.querySelector('.js-warehouse');const warehouseName=warehouse.options[warehouse.selectedIndex]?.text||'—';const qty=Math.max(0,num(line.querySelector('.js-qty').value));const total=num(line.dataset.total);totalQty+=qty;totalAmount+=total;if(index===0&&sku)firstSku=sku;previewBody.insertAdjacentHTML('beforeend',`<tr><td><span class="pc-tag">${index+1}</span></td><td>${esc(document.getElementById('productName').value||'—')}</td><td><b>${esc(sku||'—')}</b></td><td>${esc(warehouseName)}</td><td>${esc(line.querySelector('.js-date').value||'—')}</td><td>${num(line.querySelector('.js-cost').value).toLocaleString('vi-VN')}</td><td>${num(line.querySelector('.js-vat').value)}%</td><td><b>${money(line.dataset.actual)}</b></td><td><b>${qty.toLocaleString('vi-VN')}</b></td><td><b>${money(total)}</b></td><td>${esc(line.querySelector('.js-note').value||'—')}</td></tr>`)});document.getElementById('sumLines').textContent=container.querySelectorAll('.pc-line').length;document.getElementById('sumQty').textContent=totalQty.toLocaleString('vi-VN');document.getElementById('sumAmount').textContent=money(totalAmount);document.getElementById('sumSku').textContent=firstSku;syncMain();calculateSalePrices()}
    function bind(line){line.querySelectorAll('input,select,textarea').forEach(el=>{el.addEventListener('input',refresh);el.addEventListener('change',refresh)});line.querySelector('.js-remove').addEventListener('click',()=>{if(container.querySelectorAll('.pc-line').length<=1){alert('Phải có ít nhất một dòng SKU.');return}line.remove();reindex();refresh()})}
    function addLine(data){const index=container.querySelectorAll('.pc-line').length;container.insertAdjacentHTML('beforeend',lineTemplate(index,data));bind(container.lastElementChild);refresh()}
    function calculateSalePrices(){document.querySelectorAll('.sale-row').forEach(row=>{const before=num(row.querySelector('.sale-before')?.value);const vat=num(row.querySelector('.sale-vat')?.value);const out=row.querySelector('.sale-after');if(out)out.value=money(before*(1+vat/100))})}

    document.getElementById('addLineBtn').addEventListener('click',()=>addLine());
    document.getElementById('productName').addEventListener('input',refresh);
    document.querySelectorAll('.sale-before,.sale-vat').forEach(el=>el.addEventListener('input',calculateSalePrices));
    document.getElementById('productCreateForm').addEventListener('submit',function(event){for(const line of container.querySelectorAll('.pc-line')){if(!line.querySelector('.js-sku').value.trim()||!line.querySelector('.js-warehouse').value){event.preventDefault();alert('Mỗi dòng phải có Mã SKU và Kho nhập.');return false}}syncMain()});

    if(Array.isArray(oldLines)&&oldLines.length){oldLines.forEach(line=>addLine(line));}else{addLine();}
    calculateSalePrices();
})();
</script>
@endsection
