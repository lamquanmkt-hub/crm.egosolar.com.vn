@php
    $vnd = static fn ($n) => number_format((float) $n, 0, ',', '.');
    $num = static fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    [$year, $monthNo] = explode('-', $period->payroll_month);
    $income = [
        ['Lương ngày công thực tế', $line->base_pay, 'Lương chính '.$vnd($line->base_salary).' × '.$num($line->paid_days).'/'.$num($line->standard_days).' công'],
        ['Thưởng theo QĐ', $line->decision_bonus_pay, null],
        ['Tiền ăn', $line->meal_pay, null],
        ['Hỗ trợ đi lại', $line->travel_pay, null],
        ['Hỗ trợ điện thoại', $line->phone_pay, null],
        ['Hiệu suất công việc (lương KPI)', $line->kpi_pay, $line->kpi_rate !== null
            ? ($line->kpi_percent !== null ? 'KPI '.$num($line->kpi_percent).'% → ' : '').'hưởng '.$num($line->kpi_rate * 100).'% · '.$line->kpi_label
            : $line->kpi_label],
        ['Khác (chuyên cần, sinh nhật)', $line->other_income, null],
        ['Tăng ca, công tác tỉnh', $line->ot_amount + $line->trip_allowance, $line->ot_hours > 0 ? $num($line->ot_hours).' giờ tăng ca đã duyệt' : null],
    ];
    $deductions = [
        ['BHXH', $line->bhxh_employee],
        ['BHYT', $line->bhyt_employee],
        ['BHTN', $line->bhtn_employee],
        ['Thuế TNCN', $line->pit],
        ['Tạm ứng', $line->advance],
        ['Phạt đi trễ (chấm công)', $line->late_penalty],
        ['Trừ khác', $line->other_deduction],
    ];
    $totalDeduction = array_sum(array_column($deductions, 1));
@endphp
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Phiếu lương {{ $line->employee_name }} – {{ (int) $monthNo }}/{{ $year }}</title>
    <style>
        body{font-family:"Segoe UI",Arial,sans-serif;color:#13293d;background:#eef3f6;margin:0;padding:24px}
        .slip{max-width:720px;margin:0 auto;background:#fff;border-radius:12px;padding:28px 32px;box-shadow:0 10px 30px rgba(20,60,90,.08)}
        h1{text-align:center;font-size:20px;margin:6px 0 2px}
        .company{text-align:center;font-weight:700;font-size:13px}
        .sub{text-align:center;color:#5b7284;font-size:13px;margin-bottom:18px}
        table{width:100%;border-collapse:collapse;font-size:13px}
        td{padding:7px 8px;border-bottom:1px solid #e3ecf1;vertical-align:top}
        td.amt{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
        tr.head td{background:#f1f7fa;font-weight:800;text-transform:uppercase;font-size:11px;color:#4a6377}
        tr.total td{font-weight:800}
        tr.net td{font-weight:900;font-size:16px;color:#08745a;border-bottom:2px solid #08745a}
        .hint{color:#7a8f9c;font-size:11px}
        .info td:first-child{width:45%;color:#4a6377}
        .draft{margin:0 0 12px;padding:8px 10px;border-radius:8px;background:#fff6e6;color:#9a6100;font-size:12px;text-align:center}
        .actions{max-width:720px;margin:0 auto 12px;text-align:right}
        .actions button{border:0;border-radius:8px;padding:8px 14px;background:#0d988c;color:#fff;font-weight:700;cursor:pointer}
        .sign{display:flex;justify-content:space-between;margin-top:28px;font-size:13px;text-align:center}
        @media print{body{background:#fff;padding:0}.slip{box-shadow:none}.actions{display:none}}
    </style>
</head>
<body>
<div class="actions"><button type="button" onclick="window.print()">In phiếu lương</button></div>
<div class="slip">
    <div class="company">{{ \App\Support\EgoCompanyLock::name() }}</div>
    <h1>PHIẾU LƯƠNG</h1>
    <div class="sub">Tháng {{ (int) $monthNo }} năm {{ $year }}</div>
    @if($period->status !== 'approved')
        <div class="draft">BẢN NHÁP — bảng lương chưa được duyệt, số liệu có thể thay đổi.</div>
    @endif

    <table class="info">
        <tr><td>Họ và tên</td><td><strong>{{ $line->employee_name }}</strong> {{ $line->employee_code ? '('.$line->employee_code.')' : '' }}</td></tr>
        <tr><td>Chức vụ / Phòng ban</td><td>{{ $line->position_name ?: '—' }} / {{ $line->department_name ?: '—' }}</td></tr>
        <tr><td>Lương cứng / tháng</td><td>{{ $vnd($line->base_salary) }}</td></tr>
        <tr><td>Số công tính lương</td><td>{{ $num($line->paid_days) }} / {{ $num($line->standard_days) }} <span class="hint">(đi làm {{ $num($line->worked_days) }} · phép {{ $num($line->paid_leave_days) }} · lễ {{ $num($line->holiday_days) }} · không lương {{ $num($line->unpaid_days) }})</span></td></tr>
    </table>

    <table style="margin-top:14px">
        <tr class="head"><td colspan="2">Thu nhập</td></tr>
        @foreach($income as [$label, $amount, $hint])
            <tr><td>{{ $label }}@if($hint)<div class="hint">{{ $hint }}</div>@endif</td><td class="amt">{{ $vnd($amount) }}</td></tr>
        @endforeach
        <tr class="total"><td>Tổng lương</td><td class="amt">{{ $vnd($line->gross_income) }}</td></tr>

        <tr class="head"><td colspan="2">Các khoản giảm trừ</td></tr>
        @foreach($deductions as [$label, $amount])
            <tr><td>{{ $label }}</td><td class="amt">{{ $vnd($amount) }}</td></tr>
        @endforeach
        <tr class="total"><td>Tổng các khoản giảm trừ lương</td><td class="amt">{{ $vnd($totalDeduction) }}</td></tr>

        <tr class="net"><td>THỰC LÃNH</td><td class="amt">{{ $vnd($line->net_salary) }}</td></tr>
    </table>

    @if($line->note)
        <p class="hint">Ghi chú: {{ $line->note }}</p>
    @endif
    <p class="hint">Mọi thắc mắc về phiếu lương vui lòng phản hồi phòng Hành chính Nhân sự.</p>

    <div class="sign">
        <div>Người lập<br><br><br></div>
        <div>Người nhận<br><br><br>{{ $line->employee_name }}</div>
    </div>
</div>
</body>
</html>
