@extends('layouts.app')

@section('title', 'Khách hàng')

@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/ego-customers-promax-v3.css') }}?v={{ filemtime(public_path('css/ego-customers-promax-v3.css')) }}"
    >
@endpush

@section('content')
@php
    $authUser = auth()->user();

    $canFilterOwner = $authUser->canAny([
        'customer.view_all',
        'customer.view_sales_all',
        '*',
    ]);

    $initials = static function ($name): string {
        $parts = collect(
            preg_split(
                '/\s+/u',
                trim((string) $name)
            )
        )->filter();

        return $parts
            ->take(2)
            ->map(
                fn ($part) =>
                    mb_strtoupper(
                        mb_substr($part, 0, 1)
                    )
            )
            ->implode('') ?: 'KH';
    };

    $statusData = static function ($status): array {
        return match ((string) $status) {
            'member' => [
                'Member',
                'cx-badge--member',
            ],
            'retail' => [
                'Khách lẻ',
                'cx-badge--retail',
            ],
            default => [
                'Lead',
                'cx-badge--lead',
            ],
        };
    };

    $careTypeLabel = static function ($type): string {
        return match ((string) $type) {
            'call' => 'Cuộc gọi',
            'message' => 'Tin nhắn',
            'meeting' => 'Gặp mặt',
            'email' => 'Email',
            'note' => 'Ghi chú',
            default => 'Chưa chăm sóc',
        };
    };
@endphp

<div
    id="egoCustomers"
    data-duplicate-url="{{ route('customers.duplicate-check') }}"
>
    <div class="cx-shell">
        @if(session('success'))
            <div class="cx-alert cx-reveal">
                <i class="bi bi-check-circle"></i>
                {{ session('success') }}
            </div>
        @endif

        <header class="cx-header cx-panel cx-reveal">
            <div class="cx-heading">
                <div class="cx-heading__icon">
                    <i class="bi bi-people"></i>
                </div>

                <div>
                    <h1>Khách hàng</h1>

                    <p>
                        Quản lý thông tin, người phụ trách và lịch chăm sóc.
                    </p>
                </div>
            </div>

            <div class="cx-actions">
                @can('create', \App\Models\CRM\Customers\Customer::class)
                    <button
                        type="button"
                        class="cx-btn cx-btn--primary"
                        data-customer-form-url="{{ route('customers.popup-form') }}"
                    >
                        <i class="bi bi-person-plus"></i>
                        Thêm khách hàng
                    </button>
                @endcan
            </div>
        </header>

        @include('customers._module_nav')

        <section class="cx-stats">
            <article class="cx-stat cx-panel cx-reveal">
                <div class="cx-stat__label">
                    Tổng khách hàng
                </div>

                <div class="cx-stat__value">
                    {{ number_format($stats['total']) }}
                </div>

                <div class="cx-stat__note">
                    Trong phạm vi được phân quyền
                </div>
            </article>

            <article class="cx-stat cx-panel cx-reveal">
                <div class="cx-stat__label">
                    Lead
                </div>

                <div class="cx-stat__value">
                    {{ number_format($stats['lead']) }}
                </div>

                <div class="cx-stat__note">
                    Khách cần tiếp tục chăm sóc
                </div>
            </article>

            <article class="cx-stat cx-panel cx-reveal">
                <div class="cx-stat__label">
                    Member
                </div>

                <div class="cx-stat__value">
                    {{ number_format($stats['member']) }}
                </div>

                <div class="cx-stat__note">
                    Khách hàng giá trị cao
                </div>
            </article>

            <article class="cx-stat cx-panel cx-reveal">
                <div class="cx-stat__label">
                    Tiềm năng
                </div>

                <div class="cx-stat__value">
                    {{ number_format($stats['potential']) }}
                </div>

                <div class="cx-stat__note">
                    Được đội ngũ đánh dấu ưu tiên
                </div>
            </article>
        </section>

        <form
            method="GET"
            action="{{ route('customers.index') }}"
            class="cx-filter cx-panel cx-reveal"
        >
            <div class="cx-filter__grid">
                <div class="cx-field">
                    <label>Tìm kiếm</label>

                    <input
                        class="cx-input"
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Tên, số điện thoại hoặc email"
                    >
                </div>

                <div class="cx-field">
                    <label>Khu vực</label>

                    <select
                        class="cx-select"
                        name="region_id"
                    >
                        <option value="">Tất cả</option>

                        @foreach($regions as $region)
                            <option
                                value="{{ $region->id }}"
                                @selected(
                                    (string) request('region_id')
                                    === (string) $region->id
                                )
                            >
                                {{ $region->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="cx-field">
                    <label>Loại khách</label>

                    <select
                        class="cx-select"
                        name="customer_type_id"
                    >
                        <option value="">Tất cả</option>

                        @foreach($customerTypes as $type)
                            <option
                                value="{{ $type->id }}"
                                @selected(
                                    (string) request('customer_type_id')
                                    === (string) $type->id
                                )
                            >
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="cx-field">
                    <label>Trạng thái</label>

                    <select
                        class="cx-select"
                        name="customer_status"
                    >
                        <option value="">Tất cả</option>

                        <option
                            value="lead"
                            @selected(request('customer_status') === 'lead')
                        >
                            Lead
                        </option>

                        <option
                            value="member"
                            @selected(request('customer_status') === 'member')
                        >
                            Member
                        </option>

                        <option
                            value="retail"
                            @selected(request('customer_status') === 'retail')
                        >
                            Khách lẻ
                        </option>
                    </select>
                </div>

                <button
                    type="submit"
                    class="cx-btn cx-btn--primary"
                >
                    <i class="bi bi-search"></i>
                    Lọc
                </button>
            </div>

            @if($canFilterOwner)
                <div
                    style="
                        display:grid;
                        grid-template-columns:minmax(180px,280px) auto;
                        gap:9px;
                        margin-top:9px;
                        align-items:end;
                    "
                >
                    <div class="cx-field">
                        <label>Người phụ trách</label>

                        <select
                            class="cx-select"
                            name="owner_id"
                        >
                            <option value="">
                                Tất cả nhân sự
                            </option>

                            @foreach($users as $user)
                                <option
                                    value="{{ $user->id }}"
                                    @selected(
                                        (string) request('owner_id')
                                        === (string) $user->id
                                    )
                                >
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if(request()->query())
                        <a
                            href="{{ route('customers.index') }}"
                            class="cx-btn cx-btn--soft"
                        >
                            <i class="bi bi-arrow-counterclockwise"></i>
                            Xóa lọc
                        </a>
                    @endif
                </div>
            @endif
        </form>

        <section class="cx-table-panel cx-panel cx-reveal">
            <div class="cx-table-head">
                <div>
                    <h2>Danh sách khách hàng</h2>

                    <p>
                        Hiển thị
                        {{ $customers->firstItem() ?? 0 }}
                        –
                        {{ $customers->lastItem() ?? 0 }}
                        trên
                        {{ number_format($customers->total()) }}
                        khách hàng.
                    </p>
                </div>
            </div>

            <div class="cx-table-wrap">
                <table class="cx-table">
                    <thead>
                        <tr>
                            <th>Khách hàng</th>
                            <th>Liên hệ</th>
                            <th>Phân loại</th>
                            <th>Phụ trách</th>
                            <th>Chăm sóc gần nhất</th>
                            <th>Lịch tiếp theo</th>
                            <th>Đơn hàng</th>
                            <th>Khu vực</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($customers as $customer)
                            @php
                                [$statusLabel, $statusClass] =
                                    $statusData(
                                        $customer->customer_status
                                    );

                                $nextFollowup = $customer->_next_followup_at
                                    ? \Carbon\Carbon::parse(
                                        $customer->_next_followup_at
                                    )
                                    : null;
                            @endphp

                            <tr>
                                <td>
                                    <div class="cx-person">
                                        <div class="cx-avatar">
                                            {{ $initials($customer->name) }}
                                        </div>

                                        <div>
                                            <a
                                                href="{{ route('customers.show', $customer) }}"
                                                style="
                                                    color:inherit;
                                                    text-decoration:none;
                                                "
                                            >
                                                <strong>
                                                    {{ $customer->name }}
                                                </strong>
                                            </a>

                                            <small>
                                                {{ $customer->nickname ?: 'ID '.$customer->id }}

                                                @if(
                                                    (int) $customer->_duplicate_phone_count
                                                    > 1
                                                )
                                                    ·
                                                    <span
                                                        style="color:#c24557"
                                                    >
                                                        Trùng SĐT
                                                    </span>
                                                @endif
                                            </small>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <strong>
                                        {{ $customer->phone ?: '—' }}
                                    </strong>

                                    <span class="cx-cell-sub">
                                        {{ $customer->email ?: 'Chưa có email' }}
                                    </span>
                                </td>

                                <td>
                                    <span
                                        class="cx-badge {{ $statusClass }}"
                                    >
                                        {{ $statusLabel }}
                                    </span>

                                    <span class="cx-cell-sub">
                                        {{ $customer->customerType?->name ?: 'Chưa phân loại' }}

                                        @if($customer->is_potential)
                                            · Tiềm năng
                                        @endif
                                    </span>
                                </td>

                                <td>
                                    {{ $customer->assignedUser?->name ?: 'Chưa phân công' }}
                                </td>

                                <td>
                                    <div class="cx-care">
                                        @if($customer->_care_last_at)
                                            <span class="cx-care__date">
                                                {{ \Carbon\Carbon::parse($customer->_care_last_at)->format('d/m/Y H:i') }}
                                            </span>

                                            <span class="cx-cell-sub">
                                                {{ $careTypeLabel($customer->_care_type) }}

                                                @if($customer->_care_creator)
                                                    · {{ $customer->_care_creator }}
                                                @endif
                                            </span>
                                        @else
                                            <span class="cx-cell-sub">
                                                Chưa có lịch sử chăm sóc
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    @if($nextFollowup)
                                        <span
                                            class="cx-care__next is-{{ $customer->_care_state }}"
                                        >
                                            @if($customer->_care_state === 'overdue')
                                                <i class="bi bi-exclamation-circle"></i>
                                                Quá hạn
                                            @elseif($customer->_care_state === 'today')
                                                <i class="bi bi-clock"></i>
                                                Hôm nay
                                            @else
                                                <i class="bi bi-calendar-check"></i>
                                                Đã lên lịch
                                            @endif

                                            <br>

                                            {{ $nextFollowup->format('d/m/Y H:i') }}
                                        </span>
                                    @else
                                        <span class="cx-cell-sub">
                                            Chưa lên lịch
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    <span class="cx-badge cx-badge--info">
                                        {{ (int) $customer->_order_count }}
                                        đơn
                                    </span>
                                </td>

                                <td>
                                    {{ $customer->region?->name ?: '—' }}
                                </td>

                                <td>
                                    <div class="cx-row-actions">
                                        <a
                                            class="cx-icon-btn"
                                            href="{{ route('customers.show', $customer) }}"
                                            title="Xem hồ sơ"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </a>

                                        @can('update', $customer)
                                            <button
                                                type="button"
                                                class="cx-icon-btn"
                                                data-customer-form-url="{{ route('customers.popup-form', $customer->id) }}"
                                                title="Chỉnh sửa"
                                            >
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        @endcan

                                        @can('delete', $customer)
                                            <form
                                                method="POST"
                                                action="{{ route('customers.destroy', $customer) }}"
                                            >
                                                @csrf
                                                @method('DELETE')

                                                <button
                                                    type="submit"
                                                    class="cx-icon-btn"
                                                    data-confirm="Xác nhận xóa khách hàng {{ $customer->name }}?"
                                                    title="Xóa"
                                                >
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <div class="cx-empty">
                                        <i
                                            class="bi bi-people"
                                            style="font-size:28px"
                                        ></i>

                                        <div style="margin-top:8px">
                                            Không có khách hàng phù hợp.
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="cx-mobile-list">
                @foreach($customers as $customer)
                    @php
                        [$statusLabel, $statusClass] =
                            $statusData(
                                $customer->customer_status
                            );
                    @endphp

                    <article class="cx-mobile-card">
                        <div class="cx-mobile-card__top">
                            <div class="cx-person">
                                <div class="cx-avatar">
                                    {{ $initials($customer->name) }}
                                </div>

                                <div>
                                    <strong>
                                        {{ $customer->name }}
                                    </strong>

                                    <small>
                                        {{ $customer->phone ?: 'Chưa có SĐT' }}
                                    </small>
                                </div>
                            </div>

                            <span
                                class="cx-badge {{ $statusClass }}"
                            >
                                {{ $statusLabel }}
                            </span>
                        </div>

                        <div class="cx-mobile-card__meta">
                            <div class="cx-mobile-meta">
                                <span>Phụ trách</span>

                                <strong>
                                    {{ $customer->assignedUser?->name ?: 'Chưa phân công' }}
                                </strong>
                            </div>

                            <div class="cx-mobile-meta">
                                <span>Lịch tiếp theo</span>

                                <strong>
                                    {{ $customer->_next_followup_at
                                        ? \Carbon\Carbon::parse($customer->_next_followup_at)->format('d/m H:i')
                                        : 'Chưa lên lịch'
                                    }}
                                </strong>
                            </div>
                        </div>

                        <div
                            class="cx-actions"
                            style="margin-top:10px"
                        >
                            <a
                                class="cx-btn cx-btn--soft"
                                href="{{ route('customers.show', $customer) }}"
                            >
                                <i class="bi bi-eye"></i>
                                Hồ sơ
                            </a>

                            @can('update', $customer)
                                <button
                                    type="button"
                                    class="cx-btn cx-btn--soft"
                                    data-customer-form-url="{{ route('customers.popup-form', $customer->id) }}"
                                >
                                    <i class="bi bi-pencil"></i>
                                    Sửa
                                </button>
                            @endcan
                        </div>
                    </article>
                @endforeach
            </div>

            @if($customers->hasPages())
                <div class="cx-pagination">
                    {{ $customers->withQueryString()->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </section>
    </div>
</div>

<div
    class="modal fade"
    id="customerModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div
        class="modal-dialog modal-xl modal-dialog-scrollable"
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
