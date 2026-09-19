<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý phê duyệt vật tư</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f3f7fb;color:#17324d;font-family:Arial,sans-serif}.page{max-width:920px;margin:0 auto;padding:24px}.top{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:16px}.back{color:#0f766e;text-decoration:none;font-weight:700}.card{background:#fff;border:1px solid #dbe6ef;border-radius:18px;padding:20px;box-shadow:0 12px 34px rgba(15,23,42,.07);margin-bottom:16px}.title{margin:0 0 6px;font-size:24px}.muted{color:#64748b;font-size:13px;line-height:1.5}.summary{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:15px}.summary div{padding:12px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc}.summary small,.summary strong{display:block}.summary small{color:#64748b;font-size:11px}.summary strong{margin-top:4px}.items{width:100%;border-collapse:collapse;margin-top:12px}.items th,.items td{padding:10px;border-bottom:1px solid #e7edf3;text-align:left;font-size:12px}.items th{background:#f8fafc;color:#526a80}.actions{display:grid;grid-template-columns:1fr;gap:14px}.action{padding:15px;border:1px solid #dbe6ef;border-radius:15px;background:#fff}.action.selected{border-color:#14b8a6;box-shadow:0 0 0 3px rgba(20,184,166,.10)}.action h2{margin:0 0 5px;font-size:16px}.action p{margin:0 0 11px;color:#64748b;font-size:12px}.textarea{width:100%;min-height:90px;padding:12px;border:1px solid #cbd5e1;border-radius:12px;font:inherit;resize:vertical;margin-bottom:10px}.submit{width:100%;min-height:48px;border:0;border-radius:12px;padding:10px 14px;font-weight:800;font-size:14px;cursor:pointer}.approve{background:#0f9f8b;color:#fff}.return{background:#f8fafc;color:#334155;border:1px solid #cbd5e1}.errors{padding:12px 14px;border:1px solid #fecaca;border-radius:12px;background:#fff1f2;color:#9f1239;margin-bottom:14px}.note{padding:10px 12px;border:1px solid #bae6fd;border-radius:12px;background:#f0f9ff;color:#075985;font-size:12px;margin-bottom:14px}@media(max-width:700px){.page{padding:12px}.summary{grid-template-columns:1fr}.top{display:block}.back{display:inline-block;margin-top:10px}}
    </style>
</head>
<body>
<div class="page">
    <div class="top">
        <div>
            <h1 class="title">Quản lý phê duyệt vật tư</h1>
            <div class="muted">Công trình {{ $project->code }} · {{ $project->name }}</div>
        </div>
        <a class="back" href="{{ url('/cong-trinh/'.$project->id).'?tab=materials&material_view=proposal&material_request='.$materialRequest->id }}">← Quay lại công trình</a>
    </div>

    @if($errors->any())
        <div class="errors"><strong>Không thể xử lý:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="note">Màn hình này dùng form POST HTML nguyên bản, không phụ thuộc JavaScript của trang Công trình.</div>

    <section class="card">
        <strong>Phiếu {{ $materialRequest->code }}</strong>
        <div class="summary">
            <div><small>Trạng thái</small><strong>Chờ Quản lý phê duyệt</strong></div>
            <div><small>Số dòng vật tư</small><strong>{{ $materialRequest->items->count() }}</strong></div>
            <div><small>Ngày cần vật tư</small><strong>{{ optional($materialRequest->needed_at)->format('d/m/Y') ?: '—' }}</strong></div>
        </div>
        <table class="items">
            <thead><tr><th>Vật tư</th><th>Sản phẩm/SKU Kho xác nhận</th><th>Số lượng</th></tr></thead>
            <tbody>
            @foreach($materialRequest->items as $item)
                @php($allocation = $item->allocations->first())
                <tr>
                    <td>{{ $item->item_name }}</td>
                    <td>{{ $allocation?->product?->name ?: $item->product?->name ?: '—' }}@if($allocation?->product?->sku)<br><small>SKU {{ $allocation->product->sku }}</small>@endif</td>
                    <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }} {{ $item->unit }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <div class="actions">
        <section id="approve" class="action {{ $selectedDecision === 'approve' ? 'selected' : '' }}">
            <h2>Phê duyệt chuyển xuất kho</h2>
            <p>Chuyển phiếu sang Kho giữ hàng và thực hiện xuất kho.</p>
            <form method="POST" action="{{ url('/cong-trinh/'.$project->id.'/vat-tu/'.$materialRequest->id.'/quyet-dinh/approve') }}">
                @csrf
                <input type="hidden" name="review_note" value="Phê duyệt chuyển xuất kho">
                <input class="submit approve" type="submit" value="Phê duyệt chuyển xuất kho">
            </form>
        </section>

        <section id="return-warehouse" class="action {{ $selectedDecision === 'return_warehouse' ? 'selected' : '' }}">
            <h2>Trả Kho kiểm tra lại</h2>
            <p>Dùng khi sai sản phẩm, SKU, kho cấp hoặc số lượng tồn.</p>
            <form method="POST" action="{{ url('/cong-trinh/'.$project->id.'/vat-tu/'.$materialRequest->id.'/quyet-dinh/return_warehouse') }}">
                @csrf
                <textarea class="textarea" name="review_note" maxlength="3000" required placeholder="Nhập lý do trả Kho kiểm tra lại...">{{ old('review_note') }}</textarea>
                <input class="submit return" type="submit" value="Trả Kho kiểm tra lại">
            </form>
        </section>

        <section id="return-technical" class="action {{ $selectedDecision === 'return_technical' ? 'selected' : '' }}">
            <h2>Trả Kỹ thuật điều chỉnh</h2>
            <p>Kỹ thuật sửa tên vật tư, thông số, đơn vị hoặc số lượng rồi gửi lại Kho.</p>
            <form method="POST" action="{{ url('/cong-trinh/'.$project->id.'/vat-tu/'.$materialRequest->id.'/quyet-dinh/return_technical') }}">
                @csrf
                <textarea class="textarea" name="review_note" maxlength="3000" required placeholder="Nhập lý do trả Kỹ thuật điều chỉnh...">{{ old('review_note') }}</textarea>
                <input class="submit return" type="submit" value="Trả Kỹ thuật điều chỉnh">
            </form>
        </section>
    </div>
</div>
</body>
</html>
