<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18px 18px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; }
        .title { text-align: center; font-weight: 800; font-size: 18px; margin-bottom: 4px; }
        .sub { text-align: center; color: #475569; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #eaf6ff; color: #0f172a; font-weight: 800; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 5px; vertical-align: top; }
        .note { white-space: pre-line; font-size: 9px; line-height: 1.35; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 8px; background: #e2e8f0; font-weight: 700; }
        .approved-note { color: #047857; font-weight: 700; }
    </style>
</head>
<body>
    <div class="title">BẢNG CHẤM CÔNG CHI TIẾT</div>
    <div class="sub">
        Tháng {{ \Carbon\Carbon::parse($start)->format('m/Y') }}
        | Từ {{ $start->format('d/m/Y') }} đến {{ $end->format('d/m/Y') }}
        | Tổng {{ $records->count() }} dòng
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:6%">Ngày</th>
                <th style="width:10%">Nhân viên</th>
                <th style="width:9%">Phòng ban</th>
                <th style="width:6%">Check-in</th>
                <th style="width:6%">Check-out</th>
                <th style="width:5%">Muộn</th>
                <th style="width:5%">Sớm</th>
                <th style="width:6%">Giờ công</th>
                <th style="width:8%">Trạng thái</th>
                <th>Ghi chú / đơn nghỉ phép / làm online</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $record)
                <tr>
                    <td>{{ optional($record->work_date)->format('d/m/Y') }}</td>
                    <td><strong>{{ $record->user->name ?? '-' }}</strong></td>
                    <td>{{ optional($record->user->department)->name ?? '-' }}</td>
                    <td>{{ optional($record->check_in_at)->format('H:i:s') ?? '-' }}</td>
                    <td>{{ optional($record->check_out_at)->format('H:i:s') ?? '-' }}</td>
                    <td>{{ (int) $record->late_minutes }} phút</td>
                    <td>{{ (int) $record->early_leave_minutes }} phút</td>
                    <td>{{ round(((int) $record->work_minutes) / 60, 2) }} giờ</td>
                    <td><span class="badge">{{ $record->status_label }}</span></td>
                    <td class="note {{ str_contains((string) $record->note, 'Đơn HR') ? 'approved-note' : '' }}">
                        {{ $record->note ?: '-' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align:center;padding:20px">Không có dữ liệu chấm công trong bộ lọc này.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
