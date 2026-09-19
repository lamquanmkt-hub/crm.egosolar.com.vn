<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<style>
    @page{margin:16px 18px}body{font-family:DejaVu Sans,sans-serif;font-size:7px;color:#172033}h1{font-size:16px;margin:0 0 3px;color:#0f766e}.sub{color:#64748b;margin-bottom:10px}.meta{display:table;width:100%;margin-bottom:8px}.meta>div{display:table-cell}.right{text-align:right}.cards{width:100%;border-collapse:separate;border-spacing:4px;margin-left:-4px}.card{border:1px solid #dbe3ec;border-radius:7px;padding:6px}.label{font-size:6px;text-transform:uppercase;color:#64748b}.value{font-size:10px;font-weight:bold;margin-top:2px}table.data{width:100%;border-collapse:collapse;margin-top:7px;table-layout:auto}table.data th{background:#0f766e;color:#fff;padding:4px 3px;text-align:left;font-size:6px}table.data td{border:1px solid #e2e8f0;padding:3px;vertical-align:top}.money{text-align:right;white-space:nowrap}.muted{color:#64748b;font-size:6px}.section{font-size:10px;font-weight:bold;margin:10px 0 4px}.footer{position:fixed;bottom:-8px;left:0;right:0;text-align:center;color:#94a3b8;font-size:6px}.nowrap{white-space:nowrap}
</style>
</head>
<body>
@php
    $fmt=fn($v)=>number_format((float)$v,0,',','.').' đ';
    $kpiLabel = match($policy['kpi_calculate_on'] ?? 'commission_base') {
        'order_revenue' => 'Doanh thu đơn phát sinh',
        'paid_in_period' => 'Tiền thực thu trong tháng',
        default => 'Cơ sở hoa hồng trước VAT',
    };
@endphp
<div class="meta"><div><h1>BẢNG THU NHẬP & HOA HỒNG KINH DOANH</h1><div class="sub">Kỳ {{ $month }} · Trạng thái {{ strtoupper($policy['status'] ?? 'DRAFT') }} · Phiên bản {{ $policy['version'] ?? 1 }} · KPI: {{ $kpiLabel }}</div></div><div class="right">Xuất lúc {{ now()->format('d/m/Y H:i') }}</div></div>
<table class="cards"><tr>
<td class="card"><div class="label">Tổng doanh thu</div><div class="value">{{ $fmt($totals['total_order_revenue'] ?? 0) }}</div></td>
<td class="card"><div class="label">Tổng thu tháng</div><div class="value">{{ $fmt($totals['paid_in_period'] ?? 0) }}</div></td>
<td class="card"><div class="label">Tổng công nợ</div><div class="value">{{ $fmt($totals['debt'] ?? 0) }}</div></td>
<td class="card"><div class="label">Cơ sở hoa hồng</div><div class="value">{{ $fmt($totals['commission_base'] ?? $totals['eligible_before_vat'] ?? 0) }}</div></td>
<td class="card"><div class="label">Tổng hoa hồng</div><div class="value">{{ $fmt($totals['commission'] ?? 0) }}</div></td>
<td class="card"><div class="label">Tổng thu nhập</div><div class="value">{{ $fmt($totals['total_income'] ?? 0) }}</div></td>
</tr></table>
<div class="section">1. Tổng hợp nhân sự</div>
<table class="data"><thead><tr><th>#</th><th>Nhân sự</th><th>Vai trò</th><th>Doanh thu</th><th>Thu tháng</th><th>Công nợ</th><th>Cơ sở HH</th><th>KPI / Target</th><th>Lương</th><th>Phụ cấp</th><th>Hoa hồng</th><th>Thưởng/ĐC</th><th>Tổng TN</th></tr></thead><tbody>
@foreach($staff_rows as $i=>$row)<tr>
<td>{{ $i+1 }}</td>
<td><strong>{{ $row['name'] }}</strong><div class="muted">{{ $row['email'] }}</div></td>
<td>{{ $row['role_type']==='leader'?'Team Leader':'Sale' }}</td>
<td class="money">{{ $fmt($row['total_order_revenue'] ?? 0) }}</td>
<td class="money">{{ $fmt($row['paid_in_period'] ?? 0) }}</td>
<td class="money">{{ $fmt($row['debt'] ?? 0) }}</td>
<td class="money">{{ $fmt($row['commission_base'] ?? 0) }}</td>
<td class="money">{{ $fmt($row['kpi_revenue'] ?? 0) }}<div class="muted">{{ $fmt($row['target_revenue']) }} · {{ number_format($row['achievement_percent'],1,',','.') }}%</div></td>
<td class="money">{{ $fmt($row['base_salary']) }}</td>
<td class="money">{{ $fmt($row['responsibility_allowance']+$row['travel_allowance']) }}</td>
<td class="money">{{ $fmt($row['personal_commission']+$row['team_commission']) }}</td>
<td class="money">{{ $fmt($row['dealer_bonus']+$row['adjustment_total']) }}</td>
<td class="money"><strong>{{ $fmt($row['total_income']) }}</strong></td>
</tr>@endforeach
</tbody></table>
<div class="section">2. Chi tiết đơn hàng và tiền thu</div>
<table class="data"><thead><tr><th>#</th><th>Mã đơn / Ngày</th><th>Khách hàng / Sale</th><th>Loại</th><th>Giá trị</th><th>Trước VAT</th><th>Đã thu LK</th><th>Thu tháng</th><th>Công nợ</th><th>Cơ sở HH</th><th>Cơ sở KPI</th><th>Tỷ lệ</th><th>Hoa hồng</th></tr></thead><tbody>
@foreach($orders as $i=>$row)<tr>
<td>{{ $i+1 }}</td>
<td><strong>{{ $row['order_code'] }}</strong><div class="muted">{{ $row['order_date'] ?: $row['activity_date'] }}</div></td>
<td>{{ $row['customer_name'] }}<div class="muted">{{ $row['sales_name'] }}</div></td>
<td>{{ $row['order_type']==='project'?'Công trình':'Phân phối' }}<div class="muted">{{ $row['customer_group'] }}</div></td>
<td class="money">{{ $fmt($row['order_total']) }}</td>
<td class="money">{{ $fmt($row['order_before_vat']) }}</td>
<td class="money">{{ $fmt($row['paid_lifetime']) }}</td>
<td class="money">{{ $fmt($row['paid_in_period']) }}</td>
<td class="money">{{ $fmt($row['debt']) }}</td>
<td class="money">{{ $fmt($row['commission_base']) }}</td>
<td class="money">{{ $fmt($row['kpi_base'] ?? 0) }}</td>
<td class="money">{{ number_format($row['rate_percent'],4,',','.') }}%</td>
<td class="money"><strong>{{ $fmt($row['commission']) }}</strong>@if($row['dealer_bonus']>0)<div class="muted">+{{ $fmt($row['dealer_bonus']) }} đại lý</div>@endif</td>
</tr>@endforeach
</tbody></table>
<div class="footer">Báo cáo được tạo từ CRM EGO Solar · Chính sách tháng {{ $month }}</div>
</body></html>
