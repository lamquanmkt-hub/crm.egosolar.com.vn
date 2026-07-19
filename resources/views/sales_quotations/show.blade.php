@extends('layouts.app')

@section('content')
<style>
    body {
        background: #f3f6fa;
    }

    .quote-page {
        max-width: 1100px;
        margin: 0 auto;
        padding: 22px;
    }

    .quote-toolbar {
        background: #ffffff;
        border: 1px solid #dbe5f0;
        border-radius: 16px;
        padding: 14px 16px;
        margin-bottom: 14px;
        display: flex;
        justify-content: space-between;
        gap: 14px;
        align-items: center;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
    }

    .quote-toolbar h1 {
        margin: 0;
        font-size: 24px;
        font-weight: 900;
        color: #0f172a;
    }

    .quote-toolbar p {
        margin: 4px 0 0;
        color: #64748b;
        font-size: 13px;
    }

    .quote-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: flex-end;
    }

    .q-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        padding: 9px 12px;
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #0f172a;
        cursor: pointer;
    }

    .q-btn-primary {
        background: #0ea5e9;
        border-color: #0ea5e9;
        color: #fff;
    }

    .q-btn-green {
        background: #0f766e;
        border-color: #0f766e;
        color: #fff;
    }

    .success {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #bbf7d0;
        border-radius: 12px;
        padding: 12px 14px;
        margin-bottom: 12px;
        font-weight: 800;
        font-size: 13px;
    }

    .paper-wrap {
        background: #ffffff;
        border: 1px solid #dbe5f0;
        border-radius: 16px;
        padding: 18px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .06);
    }

    @media print {
        .quote-toolbar,
        .success {
            display: none !important;
        }

        .quote-page {
            padding: 0;
            max-width: none;
        }

        .paper-wrap {
            border: 0;
            box-shadow: none;
            padding: 0;
        }
    }

    @media (max-width: 900px) {
        .quote-toolbar {
            flex-direction: column;
            align-items: flex-start;
        }

        .quote-actions {
            justify-content: flex-start;
        }
    }

    .paper-wrap .ego-pdf {
        width: 100% !important;
        max-width: none !important;
        margin: 0 auto !important;
        font-size: 14px !important;
    }

    .paper-wrap .logo-img {
        height: 72px !important;
        max-width: 280px !important;
    }


    /* EGO_QUOTE_SHOW_MOBILE_PDF_START */
    .paper-wrap {
        overflow-x: auto;
    }

    .paper-wrap .ego-pdf {
        transform-origin: top center;
    }

    @media (max-width: 768px) {
        .quote-page {
            padding: 10px;
        }

        .paper-wrap {
            padding: 10px;
            border-radius: 14px;
        }

        .paper-wrap .ego-pdf {
            min-width: 980px;
            font-size: 15px !important;
        }

        .paper-wrap .items-table th,
        .paper-wrap .items-table td,
        .paper-wrap .info-table th,
        .paper-wrap .info-table td,
        .paper-wrap .quote-meta td {
            font-size: 13px !important;
            padding: 8px 7px !important;
        }

        .paper-wrap .title-box h1 {
            font-size: 24px !important;
        }
    }
    /* EGO_QUOTE_SHOW_MOBILE_PDF_END */

</style>

<div class="quote-page">
    @if(session('success'))
        <div class="success">{{ session('success') }}</div>
    @endif

    <div class="quote-toolbar">
        <div>
            <h1>{{ $quotation->quote_code }}</h1>
            <p>{{ $quotation->customer_name }} · {{ $quotation->project_name ?: 'Báo giá dự án' }}</p>
        </div>

        <div class="quote-actions">
            <a class="q-btn" href="{{ route('sales-quotations.index') }}">← Danh sách</a>
            <a class="q-btn" href="{{ route('sales-quotations.edit', $quotation) }}">Sửa</a>
            <a class="q-btn q-btn-primary" href="{{ route('sales-quotations.pdf', $quotation) }}">Xuất PDF</a>
            <a class="q-btn q-btn-green" href="{{ route('sales-quotations.pdf.download', $quotation) }}">Tải PDF</a>
            <a class="q-btn q-btn-green" href="{{ route('sales-quotations.excel', $quotation) }}">Xuất Excel</a>

            <form method="POST" action="{{ route('sales-quotations.sent', $quotation) }}">
                @csrf
                <button class="q-btn" type="submit">Đã gửi khách</button>
            </form>
        </div>
    </div>

    <div class="paper-wrap">
        @include('sales_quotations.pdf', ['quotation' => $quotation, 'embed' => true])
    </div>
</div>
@endsection
