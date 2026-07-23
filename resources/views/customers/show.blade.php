@extends('layouts.app')

@section('title', 'Hồ sơ khách hàng')

@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/ego-customers-promax-v3.css') }}?v={{ filemtime(public_path('css/ego-customers-promax-v3.css')) }}"
    >
@endpush

@section('content')
@php
    $initials = collect(
        preg_split('/\s+/u', trim((string) $customer->name))
    )
        ->filter()
        ->take(2)
        ->map(
            fn ($part) =>
                mb_strtoupper(
                    mb_substr($part, 0, 1)
                )
        )
        ->implode('') ?: 'KH';

    $statusData = match ((string) $customer->customer_status) {
        'member' => ['Member', 'cx-badge--member'],
        'retail' => ['Khách lẻ', 'cx-badge--retail'],
        default => ['Lead', 'cx-badge--lead'],
    };

    $interactionLabels = [
        'call' => ['Cuộc gọi', 'bi-telephone'],
        'message' => ['Tin nhắn', 'bi-chat-dots'],
        'meeting' => ['Gặp mặt', 'bi-people'],
        'email' => ['Email', 'bi-envelope'],
        'note' => ['Ghi chú', 'bi-journal-text'],
    ];

    $outcomeLabels = [
        'positive' => ['Tích cực', 'cx-badge--good'],
        'neutral' => ['Bình thường', 'cx-badge--info'],
        'negative' => ['Tiêu cực', 'cx-badge--danger'],
        'no_answer' => ['Không liên lạc được', 'cx-badge--warning'],
    ];

    $nextFollowupCarbon = $nextFollowup?->next_followup_date
        ? \Carbon\Carbon::parse(
            $nextFollowup->next_followup_date
        )
        : null;

    $billingAvailable =
        $customer->billing_company_name
        || $customer->billing_tax_code
        || $customer->billing_address
        || $customer->billing_email;

    $siteStatus = static function ($status): array {
        return match ((string) $status) {
            'done' => [
                'Hoàn thành',
                'cx-badge--good',
            ],
            'warranty' => [
                'Bảo hành',
                'cx-badge--warning',
            ],
            'cancelled' => [
                'Đã hủy',
                'cx-badge--danger',
            ],
            default => [
                'Đang triển khai',
                'cx-badge--info',
            ],
        };
    };

    $siteStage = static function ($stage): string {
        return match ((string) $stage) {
            'survey' => 'Khảo sát',
            'design' => 'Thiết kế',
            'installation' => 'Lắp đặt',
            'operation' => 'Vận hành',
            default => 'Chuẩn bị',
        };
    };
@endphp

<div
    id="egoCustomerProfile"
    data-duplicate-url="{{ route('customers.duplicate-check') }}"
>
    <div class="cx-profile-ambient" aria-hidden="true">
        <span></span>
        <span></span>
    </div>

    <div class="cx-shell">
        @if(session('success'))
            <div class="cx-alert cx-reveal">
                <i class="bi bi-check-circle"></i>
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="cx-alert cx-alert--danger cx-reveal">
                <i class="bi bi-exclamation-circle"></i>

                <div>
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        <header class="cx-profile-hero cx-panel cx-reveal">
            <div class="cx-profile-hero__glow"></div>

            <div class="cx-profile-person">
                <div class="cx-profile-avatar">
                    {{ $initials }}
                </div>

                <div>
                    <div class="cx-profile-eyebrow">
                        HỒ SƠ KHÁCH HÀNG
                    </div>

                    <h1>{{ $customer->name }}</h1>

                    <div class="cx-profile-meta">
                        <span class="cx-badge {{ $statusData[1] }}">
                            {{ $statusData[0] }}
                        </span>

                        <span class="cx-badge cx-badge--info">
                            ID {{ $customer->id }}
                        </span>

                        @if($customer->is_potential)
                            <span class="cx-badge cx-badge--warning">
                                <i class="bi bi-star"></i>
                                Tiềm năng
                            </span>
                        @endif

                        @if($customer->phone)
                            <span class="cx-profile-meta-text">
                                <i class="bi bi-telephone"></i>
                                {{ $customer->phone }}
                            </span>
                        @endif

                        <span class="cx-profile-meta-text">
                            <i class="bi bi-person-check"></i>
                            {{ $customer->assignedUser?->name ?: 'Chưa phân công' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="cx-actions">
                @if($customer->phone)
                    <a
                        class="cx-btn cx-btn--soft"
                        href="tel:{{ preg_replace('/\s+/', '', $customer->phone) }}"
                    >
                        <i class="bi bi-telephone"></i>
                        Gọi
                    </a>

                    <a
                        class="cx-btn cx-btn--soft"
                        href="https://zalo.me/{{ preg_replace('/\D+/', '', $customer->phone) }}"
                        target="_blank"
                        rel="noopener"
                    >
                        <i class="bi bi-chat"></i>
                        Zalo
                    </a>
                @endif

                {{-- EGO_CUSTOMER_HEADER_ACTIONS_V32 --}}
                @if($customer->ai_chatbot_link)
                    <a
                        class="cx-btn cx-btn--ai"
                        href="{{ $customer->ai_chatbot_link }}"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <i class="bi bi-robot"></i>
                        Chat Bot AI
                    </a>
                @endif

                @if($canHandover)
                    <button
                        type="button"
                        class="cx-btn cx-btn--handover"
                        data-open-handover-drawer
                    >
                        <i class="bi bi-person-up"></i>
                        Bàn giao
                    </button>
                @endif

                @can('update', $customer)
                    <button
                        type="button"
                        class="cx-btn cx-btn--primary"
                        data-customer-form-url="{{ route('customers.popup-form', $customer->id) }}"
                    >
                        <i class="bi bi-pencil"></i>
                        Chỉnh sửa
                    </button>
                @endcan

                <a
                    class="cx-btn cx-btn--soft"
                    href="{{ route('customers.index') }}"
                >
                    <i class="bi bi-arrow-left"></i>
                    Quay lại
                </a>
            </div>
        </header>

        <section class="cx-kpis cx-kpis--six">
            <article class="cx-kpi cx-panel cx-reveal">
                <div class="cx-kpi__icon">
                    <i class="bi bi-buildings"></i>
                </div>

                <div class="cx-kpi__label">
                    Công trình
                </div>

                <div
                    class="cx-kpi__value"
                    data-count-value="{{ $summary['sites'] }}"
                >
                    {{ number_format($summary['sites']) }}
                </div>

                <div class="cx-kpi__sub">
                    {{ number_format(
                        $summary['site_contract_value'],
                        0,
                        ',',
                        '.'
                    ) }}đ hợp đồng
                </div>
            </article>

            <article class="cx-kpi cx-panel cx-reveal">
                <div class="cx-kpi__icon">
                    <i class="bi bi-bag-check"></i>
                </div>

                <div class="cx-kpi__label">
                    Đơn hàng
                </div>

                <div
                    class="cx-kpi__value"
                    data-count-value="{{ $summary['orders'] }}"
                >
                    {{ number_format($summary['orders']) }}
                </div>
            </article>

            <article class="cx-kpi cx-panel cx-reveal">
                <div class="cx-kpi__icon">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>

                <div class="cx-kpi__label">
                    Doanh thu
                </div>

                <div class="cx-kpi__value">
                    {{ number_format(
                        $summary['revenue'],
                        0,
                        ',',
                        '.'
                    ) }}đ
                </div>
            </article>

            <article class="cx-kpi cx-panel cx-reveal">
                <div class="cx-kpi__icon">
                    <i class="bi bi-wallet2"></i>
                </div>

                <div class="cx-kpi__label">
                    Công nợ
                </div>

                <div class="cx-kpi__value">
                    {{ number_format(
                        $summary['debt'],
                        0,
                        ',',
                        '.'
                    ) }}đ
                </div>
            </article>

            <article class="cx-kpi cx-panel cx-reveal">
                <div class="cx-kpi__icon">
                    <i class="bi bi-file-earmark-text"></i>
                </div>

                <div class="cx-kpi__label">
                    Báo giá
                </div>

                <div
                    class="cx-kpi__value"
                    data-count-value="{{ $summary['quotations'] }}"
                >
                    {{ number_format($summary['quotations']) }}
                </div>
            </article>

            <article class="cx-kpi cx-panel cx-reveal">
                <div class="cx-kpi__icon">
                    <i class="bi bi-shield-check"></i>
                </div>

                <div class="cx-kpi__label">
                    Bảo hành
                </div>

                <div
                    class="cx-kpi__value"
                    data-count-value="{{ $summary['warranty_events'] }}"
                >
                    {{ number_format($summary['warranty_events']) }}
                </div>
            </article>
        </section>

        <div class="cx-profile-layout">
            <main class="cx-profile-main">
                <section class="cx-panel cx-reveal">
                    <div class="cx-card-head">
                        <div>
                            <h2>
                                <i class="bi bi-person-vcard"></i>
                                Tổng quan khách hàng
                            </h2>

                            <p>
                                Thông tin định danh, liên hệ và phân loại.
                            </p>
                        </div>
                    </div>

                    <div class="cx-card-body">
                        <div class="cx-info-grid">
                            @foreach([
                                'Tên khách hàng' => $customer->name,
                                'Biệt danh' => $customer->nickname,
                                'Số điện thoại' => $customer->phone,
                                'Email' => $customer->email,
                                'Địa chỉ' => $customer->address,
                                'Khu vực' => $customer->region?->name,
                                'Loại khách' => $customer->customerType?->name,
                                'Người phụ trách' => $customer->assignedUser?->name,
                                'Facebook' => $customer->facebook_name,
                                'Zalo ID' => $customer->zalo_id,
                                'Ngày tạo' => $customer->created_at
                                    ? $customer->created_at->format('d/m/Y H:i')
                                    : null,
                                'Cập nhật cuối' => $customer->updated_at
                                    ? $customer->updated_at->format('d/m/Y H:i')
                                    : null,
                            ] as $label => $value)
                                @if(filled($value))
                                    <div class="cx-info">
                                        <div class="cx-info__label">
                                            {{ $label }}
                                        </div>

                                        <div class="cx-info__value">
                                            {{ $value }}
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        @if($customer->group_note)
                            <div class="cx-note-box">
                                <i class="bi bi-journal-text"></i>

                                <div>
                                    <strong>Ghi chú nội bộ</strong>
                                    <p>{{ $customer->group_note }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="cx-panel cx-section cx-reveal">
                    <div class="cx-card-head">
                        <div>
                            <h2>
                                <i class="bi bi-buildings"></i>
                                Công trình
                            </h2>

                            <p>
                                Công trình được liên kết theo thông tin khách hàng.
                            </p>
                        </div>

                        @if(
                            \Illuminate\Support\Facades\Route::has(
                                'sites.create'
                            )
                        )
                            <a
                                class="cx-btn cx-btn--primary"
                                href="{{ route('sites.create', [
                                    'customer_id' => $customer->id,
                                    'contact_name' => $customer->name,
                                    'contact_phone' => $customer->phone,
                                    'address' => $customer->address,
                                ]) }}"
                            >
                                <i class="bi bi-plus-lg"></i>
                                Tạo công trình
                            </a>
                        @endif
                    </div>

                    <div class="cx-card-body">
                        @if($sites->isEmpty())
                            <div class="cx-empty cx-empty--compact">
                                <i class="bi bi-buildings"></i>

                                <strong>
                                    Chưa có công trình liên kết
                                </strong>

                                <span>
                                    Công trình sẽ tự liên kết theo số điện thoại, email hoặc mã số thuế.
                                </span>
                            </div>
                        @else
                            <div class="cx-site-grid">
                                @foreach($sites as $site)
                                    @php
                                        [$siteStatusLabel, $siteStatusClass] =
                                            $siteStatus(
                                                $site->status ?? null
                                            );
                                    @endphp

                                    <article class="cx-site-card">
                                        <div class="cx-site-card__top">
                                            <div class="cx-site-card__icon">
                                                <i class="bi bi-sun"></i>
                                            </div>

                                            <div class="cx-site-card__status">
                                                <span
                                                    class="cx-badge {{ $siteStatusClass }}"
                                                >
                                                    {{ $siteStatusLabel }}
                                                </span>
                                            </div>
                                        </div>

                                        <h3>
                                            {{ $site->name ?: 'Công trình #'.$site->id }}
                                        </h3>

                                        <div class="cx-site-card__address">
                                            <i class="bi bi-geo-alt"></i>
                                            {{ $site->address ?: 'Chưa cập nhật địa chỉ' }}
                                        </div>

                                        <div class="cx-site-metrics">
                                            <div>
                                                <span>Công suất</span>
                                                <strong>
                                                    {{ filled($site->system_kwp ?? null)
                                                        ? number_format((float) $site->system_kwp, 2, ',', '.').' kWp'
                                                        : '—'
                                                    }}
                                                </strong>
                                            </div>

                                            <div>
                                                <span>Giai đoạn</span>
                                                <strong>
                                                    {{ $siteStage($site->stage ?? null) }}
                                                </strong>
                                            </div>

                                            <div>
                                                <span>Giá trị</span>
                                                <strong>
                                                    {{ number_format(
                                                        (float) ($site->contract_amount ?? 0),
                                                        0,
                                                        ',',
                                                        '.'
                                                    ) }}đ
                                                </strong>
                                            </div>
                                        </div>

                                        <div class="cx-site-card__footer">
                                            <div>
                                                @if($site->installed_at ?? null)
                                                    <span>
                                                        <i class="bi bi-calendar-check"></i>
                                                        Lắp đặt:
                                                        {{ \Carbon\Carbon::parse($site->installed_at)->format('d/m/Y') }}
                                                    </span>
                                                @endif

                                                @if($site->warranty_to ?? null)
                                                    <span>
                                                        <i class="bi bi-shield-check"></i>
                                                        BH:
                                                        {{ \Carbon\Carbon::parse($site->warranty_to)->format('d/m/Y') }}
                                                    </span>
                                                @endif
                                            </div>

                                            @if(
                                                \Illuminate\Support\Facades\Route::has(
                                                    'sites.show'
                                                )
                                            )
                                                <a
                                                    class="cx-icon-btn"
                                                    href="{{ route('sites.show', $site->id) }}"
                                                    title="Xem công trình"
                                                >
                                                    <i class="bi bi-arrow-up-right"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>

                <section
                    class="cx-panel cx-section cx-reveal"
                    id="customer-care"
                >
                    <div class="cx-card-head">
                        <div>
                            <h2>
                                <i class="bi bi-clock-history"></i>
                                Lịch sử chăm sóc
                            </h2>

                            <p>
                                Cuộc gọi, tin nhắn, email và lịch follow-up.
                            </p>
                        </div>

                        @can('update', $customer)
                            <button
                                type="button"
                                class="cx-btn cx-btn--primary"
                                data-open-care-drawer
                            >
                                <i class="bi bi-plus-lg"></i>
                                Ghi nhận chăm sóc
                            </button>
                        @endcan
                    </div>

                    <div class="cx-card-body">
                        @if($interactions->isEmpty())
                            <div class="cx-empty cx-empty--compact">
                                <i class="bi bi-chat-square-text"></i>

                                <strong>
                                    Chưa có lịch sử chăm sóc
                                </strong>

                                <span>
                                    Ghi nhận cuộc gọi, tin nhắn hoặc lịch hẹn tiếp theo.
                                </span>
                            </div>
                        @else
                            <div class="cx-timeline">
                                @foreach($interactions as $interaction)
                                    @php
                                        [$typeLabel, $typeIcon] =
                                            $interactionLabels[
                                                $interaction->interaction_type
                                            ] ?? [
                                                'Tương tác',
                                                'bi-chat',
                                            ];

                                        $outcome =
                                            $interaction->outcome
                                            ? (
                                                $outcomeLabels[
                                                    $interaction->outcome
                                                ] ?? null
                                            )
                                            : null;
                                    @endphp

                                    <article class="cx-timeline-item">
                                        <div class="cx-timeline-icon">
                                            <i class="bi {{ $typeIcon }}"></i>
                                        </div>

                                        <div class="cx-timeline-content">
                                            <div class="cx-timeline-top">
                                                <strong>
                                                    {{ $interaction->subject ?: $typeLabel }}
                                                </strong>

                                                <time>
                                                    {{ \Carbon\Carbon::parse($interaction->interaction_date)->format('d/m/Y H:i') }}
                                                </time>
                                            </div>

                                            <p>{{ $interaction->content }}</p>

                                            <div class="cx-timeline-meta">
                                                <span class="cx-badge cx-badge--info">
                                                    {{ $typeLabel }}
                                                </span>

                                                @if($outcome)
                                                    <span
                                                        class="cx-badge {{ $outcome[1] }}"
                                                    >
                                                        {{ $outcome[0] }}
                                                    </span>
                                                @endif

                                                @if($interaction->creator_name)
                                                    <span class="cx-cell-sub">
                                                        Thực hiện:
                                                        {{ $interaction->creator_name }}
                                                    </span>
                                                @endif

                                                @if($interaction->next_followup_date)
                                                    <span class="cx-badge cx-badge--warning">
                                                        Hẹn tiếp:
                                                        {{ \Carbon\Carbon::parse($interaction->next_followup_date)->format('d/m/Y H:i') }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>

                <section class="cx-panel cx-section cx-reveal">
                    <div class="cx-card-head">
                        <div>
                            <h2>
                                <i class="bi bi-bag-check"></i>
                                Đơn hàng
                            </h2>

                            <p>
                                Các giao dịch đã phát sinh với khách hàng.
                            </p>
                        </div>

                        @if(
                            \Illuminate\Support\Facades\Route::has(
                                'orders.create'
                            )
                        )
                            <a
                                class="cx-btn cx-btn--primary"
                                href="{{ route('orders.create', [
                                    'customer_id' => $customer->id,
                                ]) }}"
                            >
                                <i class="bi bi-plus-lg"></i>
                                Tạo đơn
                            </a>
                        @endif
                    </div>

                    <div class="cx-table-wrap">
                        <table class="cx-mini-table">
                            <thead>
                                <tr>
                                    <th>Mã đơn</th>
                                    <th>Ngày đặt</th>
                                    <th>Trạng thái</th>
                                    <th>Tổng tiền</th>
                                    <th></th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse($orders->take(20) as $order)
                                    <tr>
                                        <td>
                                            <strong>
                                                {{ $order->order_code ?: '#'.$order->id }}
                                            </strong>
                                        </td>

                                        <td>
                                            {{ $order->order_date
                                                ? \Carbon\Carbon::parse($order->order_date)->format('d/m/Y')
                                                : (
                                                    $order->created_at
                                                        ? \Carbon\Carbon::parse($order->created_at)->format('d/m/Y')
                                                        : '—'
                                                )
                                            }}
                                        </td>

                                        <td>
                                            {{ $order->currentStatusType?->name
                                                ?? $order->current_department
                                                ?? 'Đang xử lý'
                                            }}
                                        </td>

                                        <td>
                                            <strong>
                                                {{ number_format(
                                                    (float) ($order->total_amount ?? 0),
                                                    0,
                                                    ',',
                                                    '.'
                                                ) }}đ
                                            </strong>
                                        </td>

                                        <td>
                                            @if(
                                                \Illuminate\Support\Facades\Route::has(
                                                    'orders.show'
                                                )
                                            )
                                                <a
                                                    class="cx-icon-btn"
                                                    href="{{ route('orders.show', $order->id) }}"
                                                >
                                                    <i class="bi bi-arrow-right"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">
                                            <div class="cx-empty">
                                                Chưa có đơn hàng.
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>

            <aside class="cx-profile-aside">
                <section class="cx-panel cx-reveal">
                    <div class="cx-card-head">
                        <div>
                            <h2>
                                <i class="bi bi-calendar2-check"></i>
                                Chăm sóc tiếp theo
                            </h2>

                            <p>
                                Lịch cần thực hiện với khách hàng.
                            </p>
                        </div>
                    </div>

                    <div class="cx-card-body">
                        @if($nextFollowupCarbon)
                            <div
                                class="cx-followup-card {{ $nextFollowupCarbon->isPast() ? 'is-overdue' : '' }}"
                            >
                                <div class="cx-followup-card__icon">
                                    <i class="bi bi-calendar-event"></i>
                                </div>

                                <div>
                                    <strong>
                                        {{ $nextFollowupCarbon->isPast()
                                            ? 'Đã quá hạn chăm sóc'
                                            : 'Đã lên lịch chăm sóc'
                                        }}
                                    </strong>

                                    <span>
                                        {{ $nextFollowupCarbon->format('d/m/Y H:i') }}
                                    </span>
                                </div>
                            </div>
                        @else
                            <div class="cx-followup-card">
                                <div class="cx-followup-card__icon">
                                    <i class="bi bi-calendar-plus"></i>
                                </div>

                                <div>
                                    <strong>
                                        Chưa có lịch chăm sóc
                                    </strong>

                                    <span>
                                        Tạo lịch để tránh bỏ quên khách hàng.
                                    </span>
                                </div>
                            </div>
                        @endif

                        @can('update', $customer)
                            <button
                                type="button"
                                class="cx-btn cx-btn--primary cx-btn--block"
                                data-open-care-drawer
                            >
                                <i class="bi bi-plus-circle"></i>
                                Ghi nhận chăm sóc
                            </button>
                        @endcan
                    </div>
                </section>

                <section class="cx-panel cx-section cx-reveal">
                    <div class="cx-card-head">
                        <div>
                            <h2>
                                <i class="bi bi-lightning-charge"></i>
                                Thao tác nhanh
                            </h2>
                        </div>
                    </div>

                    <div class="cx-card-body">
                        <div class="cx-quick-actions">
                            {{-- EGO_CUSTOMER_CHATBOT_QUICK_V32 --}}
                            @if($customer->ai_chatbot_link)
                                <a
                                    href="{{ $customer->ai_chatbot_link }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="cx-quick-action-ai"
                                >
                                    <i class="bi bi-robot"></i>

                                    <span>
                                        <strong>
                                            Chat Bot AI
                                        </strong>

                                        <small>
                                            Mở cuộc hội thoại AI
                                        </small>
                                    </span>

                                    <i class="bi bi-arrow-up-right"></i>
                                </a>
                            @endif
                            @if($customer->phone)
                                <a
                                    href="tel:{{ preg_replace('/\s+/', '', $customer->phone) }}"
                                >
                                    <i class="bi bi-telephone"></i>

                                    <span>
                                        <strong>Gọi khách hàng</strong>
                                        <small>{{ $customer->phone }}</small>
                                    </span>
                                </a>

                                <a
                                    href="https://zalo.me/{{ preg_replace('/\D+/', '', $customer->phone) }}"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <i class="bi bi-chat"></i>

                                    <span>
                                        <strong>Mở Zalo</strong>
                                        <small>Nhắn tin nhanh</small>
                                    </span>
                                </a>
                            @endif

                            @if(
                                \Illuminate\Support\Facades\Route::has(
                                    'sales-quotations.create'
                                )
                            )
                                <a
                                    href="{{ route('sales-quotations.create', [
                                        'customer_id' => $customer->id,
                                    ]) }}"
                                >
                                    <i class="bi bi-file-earmark-plus"></i>

                                    <span>
                                        <strong>Tạo báo giá</strong>
                                        <small>Khởi tạo giao dịch mới</small>
                                    </span>
                                </a>
                            @endif
                        </div>
                    </div>
                </section>

                <section class="cx-panel cx-section cx-reveal">
                    <div class="cx-card-head">
                        <div>
                            <h2>
                                <i class="bi bi-receipt"></i>
                                Thông tin hóa đơn
                            </h2>
                        </div>
                    </div>

                    <div class="cx-card-body">
                        @if($billingAvailable)
                            @foreach([
                                'Tên công ty / cá nhân' => $customer->billing_company_name,
                                'Mã số thuế' => $customer->billing_tax_code,
                                'Email hóa đơn' => $customer->billing_email,
                                'Địa chỉ hóa đơn' => $customer->billing_address,
                            ] as $label => $value)
                                @if(filled($value))
                                    <div class="cx-info cx-info--stacked">
                                        <div class="cx-info__label">
                                            {{ $label }}
                                        </div>

                                        <div class="cx-info__value">
                                            {{ $value }}
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @else
                            <div class="cx-empty cx-empty--compact">
                                <i class="bi bi-receipt"></i>

                                <span>
                                    Chưa có thông tin xuất hóa đơn.
                                </span>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="cx-panel cx-section cx-reveal">
                    <div class="cx-card-head">
                        <div>
                            <h2>
                                <i class="bi bi-wallet2"></i>
                                Công nợ
                            </h2>
                        </div>
                    </div>

                    <div class="cx-card-body">
                        @forelse($debtRows->take(5) as $debt)
                            <div class="cx-debt-row">
                                <span>
                                    Đơn #{{ $debt->order_id ?: '—' }}
                                </span>

                                <strong>
                                    {{ number_format(
                                        (float) $debt->debt_amount,
                                        0,
                                        ',',
                                        '.'
                                    ) }}đ
                                </strong>
                            </div>
                        @empty
                            <div class="cx-empty cx-empty--compact">
                                <i class="bi bi-check-circle"></i>

                                <span>
                                    Không có công nợ.
                                </span>
                            </div>
                        @endforelse
                    </div>
                </section>
            </aside>
        </div>
    </div>
</div>

@can('update', $customer)
    <div
        class="cx-care-drawer-overlay"
        data-care-drawer-overlay
    ></div>

    <aside
        class="cx-care-drawer"
        id="customerCareDrawer"
        aria-hidden="true"
    >
        <header class="cx-care-drawer__header">
            <div>
                <span>CHĂM SÓC KHÁCH HÀNG</span>
                <h2>Ghi nhận tương tác</h2>
                <p>{{ $customer->name }}</p>
            </div>

            <button
                type="button"
                data-close-care-drawer
                aria-label="Đóng"
            >
                <i class="bi bi-x-lg"></i>
            </button>
        </header>

        <form
            method="POST"
            action="{{ route('customers.interactions.store', $customer) }}"
            class="cx-care-drawer__body"
        >
            @csrf

            <div class="cx-form-grid">
                <div class="cx-field">
                    <label>Hình thức</label>

                    <select
                        class="cx-select"
                        name="interaction_type"
                        required
                    >
                        <option value="call">Cuộc gọi</option>
                        <option value="message">Tin nhắn</option>
                        <option value="meeting">Gặp mặt</option>
                        <option value="email">Email</option>
                        <option value="note">Ghi chú</option>
                    </select>
                </div>

                <div class="cx-field">
                    <label>Kết quả</label>

                    <select
                        class="cx-select"
                        name="outcome"
                    >
                        <option value="">
                            Chưa đánh giá
                        </option>

                        <option value="positive">
                            Tích cực
                        </option>

                        <option value="neutral">
                            Bình thường
                        </option>

                        <option value="negative">
                            Tiêu cực
                        </option>

                        <option value="no_answer">
                            Không liên lạc được
                        </option>
                    </select>
                </div>

                <div class="cx-field is-full">
                    <label>Tiêu đề</label>

                    <input
                        class="cx-input"
                        name="subject"
                        placeholder="Ví dụ: Tư vấn hệ pin lưu trữ"
                    >
                </div>

                <div class="cx-field is-full">
                    <label>
                        Nội dung chăm sóc
                        <em>*</em>
                    </label>

                    <textarea
                        class="cx-textarea"
                        name="content"
                        required
                        rows="7"
                        placeholder="Nội dung trao đổi, nhu cầu và kết quả..."
                    ></textarea>
                </div>

                <div class="cx-field">
                    <label>Thời gian tương tác</label>

                    <input
                        type="datetime-local"
                        class="cx-input"
                        name="interaction_date"
                        value="{{ now()->format('Y-m-d\TH:i') }}"
                    >
                </div>

                <div class="cx-field">
                    <label>Thời lượng phút</label>

                    <input
                        type="number"
                        class="cx-input"
                        name="duration_minutes"
                        min="0"
                        max="1440"
                    >
                </div>

                <div class="cx-field is-full">
                    <label>Lịch chăm sóc tiếp theo</label>

                    <input
                        type="datetime-local"
                        class="cx-input"
                        name="next_followup_date"
                    >
                </div>
            </div>

            <div class="cx-care-drawer__footer">
                <button
                    type="button"
                    class="cx-btn cx-btn--soft"
                    data-close-care-drawer
                >
                    Hủy
                </button>

                <button
                    type="submit"
                    class="cx-btn cx-btn--primary"
                >
                    <i class="bi bi-check2-circle"></i>
                    Lưu lịch sử chăm sóc
                </button>
            </div>
        </form>
    </aside>
@endcan


{{-- EGO_CUSTOMER_HANDOVER_DRAWER_V32 --}}
@if($canHandover)
    <div
        class="cx-handover-drawer-overlay"
        data-handover-drawer-overlay
    ></div>

    <aside
        class="cx-handover-drawer"
        id="customerHandoverDrawer"
        aria-hidden="true"
    >
        <header class="cx-handover-drawer__header">
            <div class="cx-handover-drawer__icon">
                <i class="bi bi-person-up"></i>
            </div>

            <div>
                <span>BÀN GIAO KHÁCH HÀNG</span>

                <h2>Chuyển người phụ trách</h2>

                <p>
                    {{ $customer->name }}
                </p>
            </div>

            <button
                type="button"
                data-close-handover-drawer
                aria-label="Đóng"
            >
                <i class="bi bi-x-lg"></i>
            </button>
        </header>

        <form
            method="POST"
            action="{{ route(
                'customers.handover',
                $customer
            ) }}"
            class="cx-handover-drawer__body"
            data-handover-form
        >
            @csrf

            <div class="cx-current-owner">
                <div>
                    <i class="bi bi-person-check"></i>
                </div>

                <span>
                    <small>Người phụ trách hiện tại</small>

                    <strong>
                        {{ $customer->assignedUser?->name
                            ?: 'Chưa phân công'
                        }}
                    </strong>
                </span>
            </div>

            <div class="cx-field">
                <label>
                    Nhân viên nhận bàn giao
                    <em>*</em>
                </label>

                <select
                    class="cx-select"
                    name="owner_id"
                    required
                >
                    <option value="">
                        Chọn nhân viên
                    </option>

                    @foreach($handoverUsers as $handoverUser)
                        <option
                            value="{{ $handoverUser->id }}"
                        >
                            {{ $handoverUser->name }}

                            @if($handoverUser->email)
                                — {{ $handoverUser->email }}
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="cx-field">
                <label>Ghi chú bàn giao</label>

                <textarea
                    class="cx-textarea"
                    name="handover_note"
                    rows="5"
                    maxlength="2000"
                    placeholder="Tình trạng khách hàng, nhu cầu hiện tại và nội dung cần tiếp tục xử lý..."
                ></textarea>
            </div>

            <div class="cx-handover-warning">
                <i class="bi bi-info-circle"></i>

                <span>
                    Sau khi bàn giao, nhân viên mới sẽ trở thành người phụ trách chính của khách hàng.
                </span>
            </div>

            <div class="cx-handover-drawer__actions">
                <button
                    type="button"
                    class="cx-btn cx-btn--soft"
                    data-close-handover-drawer
                >
                    Hủy
                </button>

                <button
                    type="submit"
                    class="cx-btn cx-btn--handover"
                    @disabled($handoverUsers->isEmpty())
                >
                    <i class="bi bi-arrow-left-right"></i>
                    Xác nhận bàn giao
                </button>
            </div>

            <section class="cx-handover-history">
                <div class="cx-handover-history__heading">
                    <i class="bi bi-clock-history"></i>

                    <div>
                        <strong>Lịch sử bàn giao</strong>

                        <span>
                            Theo dõi các lần thay đổi người phụ trách.
                        </span>
                    </div>
                </div>

                @forelse($handoverHistory as $handover)
                    <article class="cx-handover-item">
                        <div class="cx-handover-item__line">
                            <span>
                                {{ $handover->from_user_name
                                    ?: 'Chưa phân công'
                                }}
                            </span>

                            <i class="bi bi-arrow-right"></i>

                            <strong>
                                {{ $handover->to_user_name
                                    ?: 'Không xác định'
                                }}
                            </strong>
                        </div>

                        <div class="cx-handover-item__meta">
                            {{ \Carbon\Carbon::parse(
                                $handover->created_at
                            )->format('d/m/Y H:i') }}

                            @if($handover->action_user_name)
                                · Thực hiện bởi
                                {{ $handover->action_user_name }}
                            @endif
                        </div>

                        @if($handover->note)
                            <p>{{ $handover->note }}</p>
                        @endif
                    </article>
                @empty
                    <div class="cx-handover-empty">
                        Chưa có lịch sử bàn giao.
                    </div>
                @endforelse
            </section>
        </form>
    </aside>
@endif

<div
    class="modal fade cx-customer-modal"
    id="customerModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div
        class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"
    >
        <div
            class="modal-content cx-modal"
            id="customerModalContent"
        ></div>
    </div>
</div>
@endsection

@push('scripts')
    <script
        src="{{ asset('js/ego-customers-promax-v3.js') }}?v={{ filemtime(public_path('js/ego-customers-promax-v3.js')) }}"
        defer
    ></script>
@endpush
