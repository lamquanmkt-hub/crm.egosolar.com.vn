<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <style>
        /* Ép 1 trang: margin đều nhưng gọn hơn */
        @page { margin: 28px; }

        @font-face {
            font-family: "TimesNewRomanCustom";
            src: url("{{ storage_path('fonts/times.ttf') }}") format("truetype");
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: "TimesNewRomanCustom";
            src: url("{{ storage_path('fonts/timesbd.ttf') }}") format("truetype");
            font-weight: bold;
            font-style: normal;
        }
        @font-face {
            font-family: "TimesNewRomanCustom";
            src: url("{{ storage_path('fonts/timesi.ttf') }}") format("truetype");
            font-weight: normal;
            font-style: italic;
        }
        @font-face {
            font-family: "TimesNewRomanCustom";
            src: url("{{ storage_path('fonts/timesbi.ttf') }}") format("truetype");
            font-weight: bold;
            font-style: italic;
        }

        * { box-sizing: border-box; }

        body, *{
            font-family: "TimesNewRomanCustom" !important;
            font-size: 14px;
            color: #000;
            line-height: 1.25; /* gọn hơn để đủ 1 trang */
        }
        body{ margin:0; padding:0; }

        /* ===== HEADER ===== */
        .header{
            width:100%;
            table-layout: fixed; /* canh giữa chuẩn */
            border-collapse: collapse;
            margin: 0 0 6px 0;
        }
        .header td{
            vertical-align: top; /* logo & title cùng top */
            padding: 0;
        }

        /* 2 cột hai bên bằng nhau */
        .col-side{ width: 130px; }

        .logo{
            text-align: left;  /* logo sát trái, “ngang hàng đầu” với chữ nhìn tự nhiên hơn */
            padding-top: 0;
        }
        .logo img{
            width: 120px;
            height: auto;
            display: block;
            margin: 0;         /* bỏ auto center để sát top/left */
        }

        .title-box{
            text-align:center;
            padding-top: 0;
        }

        .title{
            display: inline-block;
            border: 1px solid #333;
            padding: 7px 16px;
            font-weight: bold;
            font-size: 18px;
            letter-spacing: 0.3px;
            white-space: nowrap; /* luôn 1 dòng */
        }

        .date{
            margin-top: 8px;
            text-align:center;
            font-style: normal;
        }

        /* ===== CONTENT (BỎ KHUNG) ===== */
        .box{
            margin-top: 10px;
            width: 100%;
            padding: 0;        /* bỏ padding của khung */
            border: none;      /* bỏ viền */
            border-radius: 0;  /* bỏ bo góc */
            page-break-inside: avoid;
        }

        .row{
            width:100%;
            border-collapse: collapse;
        }
        .row td{
            padding: 3px 0;    /* gọn hơn để đủ 1 trang */
            vertical-align: top;
        }
        .label{
            width: 190px;
            font-weight: normal;
        }
        .value{
            font-weight: bold;
        }

        /* dòng chấm full */
        .dots{
            display:block;
            width:100%;
            height: 14px;
            border-bottom: 1px dotted #000;
        }

        /* ===== SIGN (GIẢM CHIỀU CAO ĐỂ KHÔNG RỚT TRANG) ===== */
        .sign{
            margin-top: 14px;         /* giảm khoảng cách */
            width:100%;
            table-layout: fixed;
            border-collapse: collapse;
            text-align:center;
            page-break-inside: avoid;
        }
        .sign td{
            width:33.33%;
            vertical-align: top;
            padding: 0;
        }
        .sign-title{
            font-weight:bold;
            font-size: 14px;
            padding-bottom: 4px;
        }
        .sign-space{
            height: 70px;             /* giảm từ 95 -> 70 */
        }
        .sign-img{
            max-height: 50px;         /* giảm để đủ 1 trang */
            height: auto;
            display:block;
            margin: 4px auto 0;
        }
        .sign-name{
            margin-top: 6px;
            font-weight:bold;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

@php
    $dt = $item->created_at ? \Carbon\Carbon::parse($item->created_at) : now();

    // ✅ Tiêu đề theo loại phiếu
    $docTitle = match($item->doc_type ?? 'payment_request') {
        'payment_voucher' => 'PHIẾU CHI',
        'refund_request' => 'ĐỀ NGHỊ HOÀN TIỀN',
        'advance' => 'PHIẾU ĐỀ NGHỊ TẠM ỨNG',
        default => 'PHIẾU ĐỀ NGHỊ THANH TOÁN',
    };
@endphp

<table class="header">
    <tr>
        <td class="logo col-side">
            @if(!empty($logoDataUri))
                <img src="{{ $logoDataUri }}" alt="logo">
            @endif
        </td>

        <td class="title-box">
            <div class="title">{{ $docTitle }}</div>
            <div class="date">
                TP.HCM, Ngày {{ $dt->format('d') }} tháng {{ $dt->format('m') }} năm {{ $dt->format('Y') }}
            </div>
        </td>

        <td class="col-side"></td>
    </tr>
</table>

<div class="box">
    <table class="row">
        <tr>
            <td class="label">Họ tên người nhận:</td>
            <td class="value">{{ $item->receiver_name }}</td>
        </tr>
        <tr>
            <td class="label">Đơn vị:</td>
            <td class="value">{{ $item->department ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Lý do chi:</td>
            <td class="value">{{ $item->reason }}</td>
        </tr>
        <tr>
            <td class="label">Số tiền:</td>
            <td class="value">{{ number_format((int)$item->amount) }} vnd</td>
        </tr>
        <tr>
            <td class="label">Bằng chữ:</td>
            <td class="value">{{ $amountText ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Chứng từ kèm theo:</td>
            <td><span class="dots"></span></td>
        </tr>
        <tr>
            <td class="label">Thông tin chuyển khoản:</td>
            <td class="value">{{ $item->bank_info ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Đơn vị thụ hưởng:</td>
            <td class="value">{{ $item->receiver_name }}</td>
        </tr>
    </table>
</div>

<table class="sign">
    <tr>
        <td>
            <div class="sign-title">QUẢN LÝ TÀI CHÍNH</div>
            <div class="sign-space">
                @if(!empty($financeSigDataUri))
                    <img class="sign-img" src="{{ $financeSigDataUri }}" alt="finance-sign">
                @endif
            </div>
            <div class="sign-name">BÙI THỊ BÍCH THẢO</div>
        </td>

        <td>
            <div class="sign-title">KẾ TOÁN</div>
            <div class="sign-space">
                @if(!empty($accSigDataUri))
                    <img class="sign-img" src="{{ $accSigDataUri }}" alt="acc-sign">
                @endif
            </div>
            {{-- BỎ TÊN KẾ TOÁN --}}
        </td>

        <td>
            <div class="sign-title">NGƯỜI ĐỀ NGHỊ</div>
            <div class="sign-space"></div>
            {{-- BỎ TÊN NGƯỜI ĐỀ NGHỊ --}}
        </td>
    </tr>
</table>


@includeIf('payment-requests._buibichthao_actions')
</body>
</html>
