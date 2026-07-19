<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        th, td {
            border: 1px solid #999;
            padding: 6px 8px;
            vertical-align: top;
        }

        th {
            background: #dff3ff;
            font-weight: bold;
            text-align: center;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            background: #ffffff;
        }

        .meta {
            text-align: center;
            font-size: 12px;
            background: #ffffff;
        }

        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
<table>
    <tr>
        <td colspan="12" class="title">BÁO CÁO CHẤM CÔNG</td>
    </tr>
    <tr>
        <td colspan="12" class="meta">
            Tháng {{ \Carbon\Carbon::parse($start)->format('m/Y') }}
            |
            Từ {{ \Carbon\Carbon::parse($start)->format('d/m/Y') }}
            đến {{ \Carbon\Carbon::parse($end)->format('d/m/Y') }}
        </td>
    </tr>

    <tr>
        <th>STT</th>
        <th>Ngày</th>
        <th>Nhân viên</th>
        <th>Phòng ban</th>
        <th>Chức vụ</th>
        <th>Check-in</th>
        <th>Địa chỉ vào</th>
        <th>Check-out</th>
        <th>Địa chỉ ra</th>
        <th>Đi muộn</th>
        <th>Về sớm</th>
        <th>Giờ công</th>
    </tr>

    @forelse($records as $index => $record)
        <tr>
            <td class="text-center">{{ $index + 1 }}</td>
            <td>{{ \Carbon\Carbon::parse($record->work_date)->format('d/m/Y') }}</td>
            <td>{{ $record->user->name ?? '-' }}</td>
            <td>{{ optional($record->user->department)->name ?? '-' }}</td>
            <td>{{ optional($record->user->position)->name ?? '-' }}</td>
            <td>
                {{ $record->check_in_at ? \Carbon\Carbon::parse($record->check_in_at)->format('H:i:s') : '-' }}
            </td>
            <td>{{ $record->check_in_address ?? '-' }}</td>
            <td>
                {{ $record->check_out_at ? \Carbon\Carbon::parse($record->check_out_at)->format('H:i:s') : '-' }}
            </td>
            <td>{{ $record->check_out_address ?? '-' }}</td>
            <td>{{ (int) $record->late_minutes }} phút</td>
            <td>{{ (int) $record->early_leave_minutes }} phút</td>
            <td>{{ round(((int) $record->work_minutes) / 60, 2) }} giờ</td>
        </tr>
    @empty
        <tr>
            <td colspan="12" class="text-center">Không có dữ liệu chấm công trong bộ lọc này</td>
        </tr>
    @endforelse
</table>
</body>
</html>
