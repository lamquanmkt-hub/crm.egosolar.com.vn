@extends('layouts.app')

@section('content')
@php
    $groupLabels = ['info'=>'Thông tin','income'=>'Thu nhập (+)','deduction'=>'Khấu trừ (-)','summary'=>'Tổng hợp'];
    $typeLabels = ['money'=>'Tiền','number'=>'Số','percent'=>'Phần trăm','text'=>'Văn bản'];
@endphp
<style>
.slip-settings-page{--bg:#f4f7fb;--panel:#fff;--line:#e3ebf4;--text:#0f172a;--muted:#64748b;background:var(--bg);min-height:100vh;padding-bottom:35px;color:var(--text);font-size:12px}.top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:12px}.top h1{font-size:23px;font-weight:950;margin:3px 0 4px}.top p{margin:0;color:var(--muted)}.crumb{font-size:10.5px;color:#8291a2;font-weight:850}.btn-soft,.btn-main{height:36px;border-radius:11px;padding:0 13px;font-size:11.5px;font-weight:900;display:inline-flex;gap:7px;align-items:center;text-decoration:none}.btn-soft{background:#fff;border:1px solid var(--line);color:#17324d}.btn-main{background:#0c3f6b;border:1px solid #0c3f6b;color:#fff}.hero{background:linear-gradient(135deg,#0a2e4c,#0a5b79 62%,#0b8585);color:#fff;border-radius:19px;padding:17px 19px;margin-bottom:12px}.hero h2{font-size:22px;font-weight:950;margin:0 0 5px}.hero p{margin:0;color:#d6e8ef}.notice{padding:10px 12px;border-radius:11px;margin-bottom:10px;background:#eaf8f2;border:1px solid #caeee0;color:#247053}.panel{background:#fff;border:1px solid var(--line);border-radius:18px;overflow:hidden;box-shadow:0 8px 28px rgba(15,23,42,.04)}.panel-head{padding:13px 14px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:flex-start}.panel-head h3{margin:0 0 3px;font-size:14px;font-weight:950}.panel-head p{margin:0;color:var(--muted);font-size:10.8px}.table-wrap{overflow:auto}.cfg-table{width:100%;border-collapse:collapse;min-width:1250px}.cfg-table th{background:#0d2942;color:#fff;padding:9px 8px;font-size:10px;white-space:nowrap;text-align:left}.cfg-table td{padding:7px 6px;border-bottom:1px solid #edf1f5;vertical-align:middle}.cfg-table input,.cfg-table select{height:33px;border:1px solid #d9e4ee;border-radius:8px;padding:0 8px;font-size:10.8px;width:100%;background:#fff}.cfg-table input[type=checkbox]{width:17px;height:17px}.danger-check{accent-color:#dc4d4d}.new-row{background:#f5fbff}.footer{padding:12px 14px;display:flex;justify-content:space-between;align-items:center;gap:10px;background:#fbfdff}.hint{font-size:10.3px;color:#7a899a}.tag{font-size:9.5px;font-weight:900;padding:4px 7px;border-radius:999px;background:#eef4fa;color:#476079}@media(max-width:700px){.top{flex-direction:column}}
</style>
<div class="slip-settings-page">
<div class="container-fluid px-3 px-lg-4 py-3">
    <div class="top">
        <div><div class="crumb">Kỹ thuật › Phiếu lương › Cấu hình</div><h1>Cấu hình phiếu lương</h1><p>Quản trị viên được thêm, sửa, xóa, đổi thứ tự và chọn khoản nào tính vào thực nhận.</p></div>
        <div style="display:flex;gap:8px"><a class="btn-soft" href="{{ url('/ky-thuat/luong') }}"><i class="fas fa-arrow-left"></i> Danh sách lương</a><button form="configForm" class="btn-main" type="submit"><i class="fas fa-save"></i> Lưu cấu hình</button></div>
    </div>
    @if(session('success'))<div class="notice">✓ {{ session('success') }}</div>@endif
    @if(session('error'))<div class="notice" style="background:#fff1f1;border-color:#ffd4d4;color:#a63d3d">! {{ session('error') }}</div>@endif
    <div class="hero"><h2>Phiếu lương động theo cấu hình Admin</h2><p>“Nguồn dữ liệu” lấy từ bảng lương KPI hiện có. Chọn “Nhập tay” để tạo phụ cấp, thưởng, tạm ứng, khấu trừ hoặc bất kỳ mục riêng nào.</p></div>

    <form id="configForm" method="POST" action="{{ route('ky-thuat.luong.slip-settings.save') }}">
    @csrf
    <div class="panel">
        <div class="panel-head"><div><h3>Danh sách trường phiếu lương</h3><p>Xóa ở đây chỉ xóa cấu hình hiển thị và giá trị ghi đè của mục đó, không xóa bảng lương gốc.</p></div><span class="tag">ADMIN ONLY</span></div>
        <div class="table-wrap">
            <table class="cfg-table">
                <thead><tr><th style="width:55px">STT</th><th style="width:155px">Mã</th><th style="width:190px">Tên hiển thị</th><th style="width:125px">Nhóm</th><th style="width:110px">Kiểu</th><th style="width:205px">Nguồn dữ liệu</th><th style="width:110px">Mặc định</th><th style="width:75px;text-align:center">Tính tổng</th><th style="width:70px;text-align:center">Hiện</th><th>Ghi chú</th><th style="width:60px;text-align:center">Xóa</th></tr></thead>
                <tbody id="fieldRows">
                @foreach($fields as $field)
                    <tr>
                        <td><input type="hidden" name="fields[{{ $loop->index }}][id]" value="{{ $field->id }}"><input type="number" name="fields[{{ $loop->index }}][sort_order]" value="{{ $field->sort_order }}"></td>
                        <td><input name="fields[{{ $loop->index }}][field_key]" value="{{ $field->field_key }}"></td>
                        <td><input name="fields[{{ $loop->index }}][label]" value="{{ $field->label }}" required></td>
                        <td><select name="fields[{{ $loop->index }}][group_key]">@foreach($groupLabels as $k=>$v)<option value="{{ $k }}" @selected($field->group_key===$k)>{{ $v }}</option>@endforeach</select></td>
                        <td><select name="fields[{{ $loop->index }}][field_type]">@foreach($typeLabels as $k=>$v)<option value="{{ $k }}" @selected($field->field_type===$k)>{{ $v }}</option>@endforeach</select></td>
                        <td><select name="fields[{{ $loop->index }}][source_column]">@foreach($sourceColumns as $k=>$v)<option value="{{ $k }}" @selected(($field->source_column ?? '')===$k)>{{ $v }}</option>@endforeach</select></td>
                        <td><input type="number" step="0.01" name="fields[{{ $loop->index }}][default_value]" value="{{ $field->default_value }}"></td>
                        <td style="text-align:center"><input type="hidden" name="fields[{{ $loop->index }}][is_in_total]" value="0"><input type="checkbox" name="fields[{{ $loop->index }}][is_in_total]" value="1" @checked($field->is_in_total)></td>
                        <td style="text-align:center"><input type="hidden" name="fields[{{ $loop->index }}][is_enabled]" value="0"><input type="checkbox" name="fields[{{ $loop->index }}][is_enabled]" value="1" @checked($field->is_enabled)></td>
                        <td><input name="fields[{{ $loop->index }}][note]" value="{{ $field->note }}"></td>
                        <td style="text-align:center"><input class="danger-check" type="checkbox" name="fields[{{ $loop->index }}][delete]" value="1"></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="footer"><span class="hint">Admin có thể xóa cả trường hệ thống khỏi phiếu; dữ liệu gốc vẫn được giữ an toàn trong bảng lương KPI.</span><button type="button" class="btn-soft" onclick="addRow()"><i class="fas fa-plus"></i> Thêm mục mới</button></div>
    </div>
    </form>
</div>
</div>
<script>
let nextIndex = {{ $fields->count() }};
const groupOptions = @json($groupLabels);
const typeOptions = @json($typeLabels);
const sourceOptions = @json($sourceColumns);
function options(obj, selected=''){ return Object.entries(obj).map(([k,v])=>`<option value="${k}" ${k===selected?'selected':''}>${v}</option>`).join(''); }
function addRow(){
    const i=nextIndex++;
    const tr=document.createElement('tr'); tr.className='new-row';
    tr.innerHTML=`
      <td><input type="number" name="fields[${i}][sort_order]" value="${(i+1)*10}"></td>
      <td><input name="fields[${i}][field_key]" placeholder="tu_dong_neu_de_trong"></td>
      <td><input name="fields[${i}][label]" placeholder="Tên khoản" required></td>
      <td><select name="fields[${i}][group_key]">${options(groupOptions,'income')}</select></td>
      <td><select name="fields[${i}][field_type]">${options(typeOptions,'money')}</select></td>
      <td><select name="fields[${i}][source_column]">${options(sourceOptions,'')}</select></td>
      <td><input type="number" step="0.01" name="fields[${i}][default_value]" value="0"></td>
      <td style="text-align:center"><input type="hidden" name="fields[${i}][is_in_total]" value="0"><input type="checkbox" name="fields[${i}][is_in_total]" value="1" checked></td>
      <td style="text-align:center"><input type="hidden" name="fields[${i}][is_enabled]" value="0"><input type="checkbox" name="fields[${i}][is_enabled]" value="1" checked></td>
      <td><input name="fields[${i}][note]" placeholder="Ghi chú"></td>
      <td style="text-align:center"><button type="button" class="btn-soft" onclick="this.closest('tr').remove()">Bỏ</button></td>`;
    document.getElementById('fieldRows').appendChild(tr);
}
</script>
@endsection
