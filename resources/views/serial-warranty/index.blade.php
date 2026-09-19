@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/serial-warranty-desk-v4.css') }}?v={{ @filemtime(public_path('css/serial-warranty-desk-v4.css')) ?: time() }}">
<script defer src="{{ asset('js/serial-warranty-desk-v4.js') }}?v={{ @filemtime(public_path('js/serial-warranty-desk-v4.js')) ?: time() }}"></script>

@php
    $today = now()->startOfDay();
    $user = auth()->user();
    $roleText = strtolower(implode(' ', array_filter([
        $user->role ?? null,
        $user->role_name ?? null,
        $user->department ?? null,
        $user->current_department ?? null,
        $user->position ?? null,
        $user->type ?? null,
        $user->permission ?? null,
        $user->email ?? null,
        $user->name ?? null,
    ])));

    $canManageWarranty = $user && (
        !empty($user->is_admin)
        || !empty($user->is_super_admin)
        || str_contains($roleText, 'admin')
        || str_contains($roleText, 'kho')
        || str_contains($roleText, 'warehouse')
        || str_contains($roleText, 'lamquanmkt')
        || str_contains($roleText, 'lâm quân')
        || str_contains($roleText, 'lam quân')
    );

    $totalSerial = (int) ($stats['total'] ?? 0);
    $activeWarranty = (int) ($stats['warranty_active'] ?? 0);
    $inactiveWarranty = max(0, $totalSerial - $activeWarranty);
    $resultTotal = method_exists($serials, 'total') ? (int) $serials->total() : count($serials);
    $q = $q ?? request('q', '');
    $state = $state ?? request('state', '');
    $productId = $productId ?? (int) request('product_id', 0);
@endphp

<div class="wd-page" data-wd-auto-open-add="{{ $errors->any() && old('serials') ? '1' : '0' }}">
    <div class="wd-shell">
        <header class="wd-topbar">
            <div class="wd-heading">
                <div class="wd-icon"><i class="bi bi-shield-check"></i></div>
                <div>
                    <div class="wd-eyebrow">Warranty Desk</div>
                    <h1>Serial & Bảo hành</h1>
                    <p>Tra cứu, kiểm tra và cập nhật serial đã bán trên một không gian làm việc.</p>
                </div>
            </div>

            <div class="wd-top-actions">
                <span class="wd-access-badge">
                    <i class="bi {{ $canManageWarranty ? 'bi-unlock' : 'bi-eye' }}"></i>
                    {{ $canManageWarranty ? 'Kho / Admin quản lý' : 'Quyền tra cứu' }}
                </span>
                <a href="{{ route('serial-warranty.index') }}" class="wd-btn wd-btn-light">
                    <i class="bi bi-arrow-clockwise"></i> Làm mới
                </a>
                @if($canManageWarranty)
                    <button type="button" class="wd-btn wd-btn-primary" data-wd-open="add">
                        <i class="bi bi-plus-lg"></i> Thêm serial
                    </button>
                @endif
            </div>
        </header>

        @if(session('success'))
            <div class="wd-alert wd-alert-success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>
        @endif
        @if(session('error'))
            <div class="wd-alert wd-alert-error"><i class="bi bi-exclamation-triangle-fill"></i><span>{{ session('error') }}</span></div>
        @endif
        @if($errors->any())
            <div class="wd-alert wd-alert-error">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
            </div>
        @endif

        <div class="wd-workspace">
            <aside class="wd-sidebar">
                <section class="wd-panel wd-search-panel">
                    <div class="wd-panel-title">
                        <span><i class="bi bi-search"></i></span>
                        <div><strong>Tìm kiếm</strong><small>Lọc dữ liệu chính xác</small></div>
                    </div>

                    <form method="GET" action="{{ route('serial-warranty.index') }}" class="wd-filter-form">
                        <label class="wd-label" for="wd-q">Serial, khách hàng hoặc đơn</label>
                        <div class="wd-input-icon">
                            <i class="bi bi-upc-scan"></i>
                            <input id="wd-q" class="wd-input" type="search" name="q" value="{{ $q }}" placeholder="Nhập nội dung cần tìm...">
                        </div>

                        <label class="wd-label" for="wd-product">Sản phẩm</label>
                        <select id="wd-product" class="wd-select" name="product_id">
                            <option value="">Tất cả sản phẩm</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" @selected((int)$productId === (int)$product->id)>
                                    {{ $product->sku ? $product->sku.' · ' : '' }}{{ $product->name }}
                                </option>
                            @endforeach
                        </select>

                        <label class="wd-label" for="wd-state">Trạng thái giao dịch</label>
                        <select id="wd-state" class="wd-select" name="state">
                            <option value="">Đã bán và đã giao</option>
                            <option value="sold" @selected($state === 'sold')>Đã bán</option>
                            <option value="delivered" @selected($state === 'delivered')>Đã giao</option>
                        </select>

                        <div class="wd-filter-actions">
                            <button class="wd-btn wd-btn-primary" type="submit"><i class="bi bi-search"></i> Tra cứu</button>
                            <a class="wd-btn wd-btn-light" href="{{ route('serial-warranty.index') }}"><i class="bi bi-x-lg"></i></a>
                        </div>
                    </form>
                </section>

                <section class="wd-panel wd-stats-panel">
                    <div class="wd-panel-title compact">
                        <span><i class="bi bi-bar-chart"></i></span>
                        <div><strong>Tổng quan</strong><small>Dữ liệu toàn hệ thống</small></div>
                    </div>
                    <div class="wd-stat-grid">
                        <article><small>Tổng serial</small><strong>{{ number_format($totalSerial) }}</strong><span>đã bán / đã xuất</span></article>
                        <article class="success"><small>Còn bảo hành</small><strong>{{ number_format($activeWarranty) }}</strong><span>đang hiệu lực</span></article>
                        <article class="warning"><small>Hết / chưa BH</small><strong>{{ number_format($inactiveWarranty) }}</strong><span>cần kiểm tra</span></article>
                        <article class="info"><small>Kết quả lọc</small><strong>{{ number_format($resultTotal) }}</strong><span>serial phù hợp</span></article>
                    </div>
                </section>

                <section class="wd-panel wd-guide-panel">
                    <div class="wd-panel-title compact">
                        <span><i class="bi bi-lightbulb"></i></span>
                        <div><strong>Thao tác nhanh</strong><small>Chọn một serial ở danh sách</small></div>
                    </div>
                    <ul>
                        <li><i class="bi bi-mouse"></i><span>Bấm vào thẻ để xem chi tiết bên phải.</span></li>
                        <li><i class="bi bi-copy"></i><span>Bấm mã serial để sao chép nhanh.</span></li>
                        <li><i class="bi bi-pencil-square"></i><span>Kho/Admin được sửa thời hạn bảo hành.</span></li>
                    </ul>
                </section>
            </aside>

            <main class="wd-list-column">
                <section class="wd-list-header">
                    <div>
                        <span class="wd-section-kicker">Danh sách serial</span>
                        <h2>{{ number_format($resultTotal) }} kết quả</h2>
                    </div>
                    <div class="wd-view-note"><i class="bi bi-layout-text-window-reverse"></i> Chọn serial để mở hồ sơ</div>
                </section>

                <div class="wd-quick-filters" role="group" aria-label="Lọc nhanh bảo hành">
                    <button type="button" class="wd-chip is-active" data-wd-filter="all">Tất cả</button>
                    <button type="button" class="wd-chip" data-wd-filter="active"><span class="dot green"></span>Còn bảo hành</button>
                    <button type="button" class="wd-chip" data-wd-filter="expired"><span class="dot red"></span>Hết bảo hành</button>
                    <button type="button" class="wd-chip" data-wd-filter="inactive"><span class="dot amber"></span>Chưa kích hoạt</button>
                    <button type="button" class="wd-chip" data-wd-filter="manual"><i class="bi bi-pencil"></i>Bổ sung thủ công</button>
                </div>

                <div class="wd-serial-list" id="wdSerialList">
                    @forelse($serials as $row)
                        @php
                            $end = $row->warranty_end_at ? \Carbon\Carbon::parse($row->warranty_end_at)->startOfDay() : null;
                            $daysLeft = $end ? $today->diffInDays($end, false) : null;
                            $warrantyState = !$end ? 'inactive' : ($daysLeft >= 0 ? 'active' : 'expired');
                            $stateLabel = $row->state === 'delivered' ? 'Đã giao' : 'Đã bán';
                            $serialLabel = $row->serial_code ?: ('#'.$row->id);
                            $orderLabel = $row->order_code ?: ($row->order_id ? ('Đơn #'.$row->order_id) : 'Chưa gắn đơn');
                            $orderUrl = $row->order_id
                                ? (\Illuminate\Support\Facades\Route::has('orders.show') ? route('orders.show', $row->order_id) : url('/orders/'.$row->order_id))
                                : '';
                            $noteText = trim((string)($row->note ?? ''));
                            $isManual = str_contains(strtolower($noteText), 'quên nhập kho') || str_contains(strtolower($noteText), 'bổ sung serial');
                        @endphp

                        <article
                            class="wd-serial-card {{ $loop->first ? 'is-selected' : '' }}"
                            tabindex="0"
                            data-wd-card
                            data-id="{{ $row->id }}"
                            data-filter-warranty="{{ $warrantyState }}"
                            data-filter-manual="{{ $isManual ? '1' : '0' }}"
                            data-serial="{{ e($serialLabel) }}"
                            data-product-id="{{ $row->product_id }}"
                            data-product="{{ e($row->product_name ?: 'Chưa có tên sản phẩm') }}"
                            data-sku="{{ e($row->product_sku ?: 'Chưa có SKU') }}"
                            data-state="{{ e($stateLabel) }}"
                            data-customer-id="{{ $row->customer_id }}"
                            data-customer="{{ e($row->customer_name ?: 'Chưa gắn khách hàng') }}"
                            data-phone="{{ e($row->customer_phone ?: '—') }}"
                            data-order-id="{{ $row->order_id }}"
                            data-order="{{ e($orderLabel) }}"
                            data-order-url="{{ e($orderUrl) }}"
                            data-sold-at="{{ $row->sold_at ? \Carbon\Carbon::parse($row->sold_at)->format('Y-m-d') : '' }}"
                            data-sold-display="{{ $row->sold_at ? \Carbon\Carbon::parse($row->sold_at)->format('d/m/Y') : '—' }}"
                            data-start="{{ $row->warranty_start_at ? \Carbon\Carbon::parse($row->warranty_start_at)->format('Y-m-d') : '' }}"
                            data-start-display="{{ $row->warranty_start_at ? \Carbon\Carbon::parse($row->warranty_start_at)->format('d/m/Y') : '—' }}"
                            data-months="{{ $row->warranty_months ?: 60 }}"
                            data-end="{{ $row->warranty_end_at ? \Carbon\Carbon::parse($row->warranty_end_at)->format('Y-m-d') : '' }}"
                            data-end-display="{{ $row->warranty_end_at ? \Carbon\Carbon::parse($row->warranty_end_at)->format('d/m/Y') : '—' }}"
                            data-days="{{ $daysLeft ?? '' }}"
                            data-note="{{ e($noteText ?: 'Chưa có ghi chú') }}"
                        >
                            <div class="wd-card-accent {{ $warrantyState }}"></div>
                            <div class="wd-card-main">
                                <div class="wd-card-topline">
                                    <button type="button" class="wd-serial-code" data-wd-copy="{{ e($serialLabel) }}" title="Sao chép serial">
                                        <i class="bi bi-upc-scan"></i><span>{{ $serialLabel }}</span><i class="bi bi-copy copy-icon"></i>
                                    </button>
                                    <span class="wd-transaction-badge"><i class="bi bi-check2-circle"></i>{{ $stateLabel }}</span>
                                </div>

                                <h3>{{ $row->product_name ?: 'Chưa có tên sản phẩm' }}</h3>
                                <div class="wd-card-meta">
                                    <span><i class="bi bi-box-seam"></i>{{ $row->product_sku ?: 'Chưa có SKU' }}</span>
                                    <span><i class="bi bi-building"></i>{{ $row->customer_name ?: 'Chưa gắn khách' }}</span>
                                </div>

                                <div class="wd-card-footer">
                                    <span class="wd-warranty-badge {{ $warrantyState }}">
                                        @if($warrantyState === 'active')
                                            <i class="bi bi-shield-check"></i>Còn {{ number_format($daysLeft) }} ngày
                                        @elseif($warrantyState === 'expired')
                                            <i class="bi bi-shield-x"></i>Hết {{ number_format(abs($daysLeft)) }} ngày
                                        @else
                                            <i class="bi bi-shield-exclamation"></i>Chưa kích hoạt
                                        @endif
                                    </span>
                                    <span class="wd-order-mini"><i class="bi bi-receipt"></i>{{ $orderLabel }}</span>
                                </div>
                            </div>

                            <button type="button" class="wd-card-arrow" aria-label="Xem chi tiết"><i class="bi bi-chevron-right"></i></button>

                            @if($canManageWarranty)
                                <form id="wd-delete-{{ $row->id }}" method="POST" action="{{ route('serial-warranty.serial.remove-from-lookup', $row->id) }}" class="wd-hidden-form">
                                    @csrf
                                </form>
                            @endif
                        </article>
                    @empty
                        <div class="wd-empty-state">
                            <div><i class="bi bi-search"></i></div>
                            <h3>Không tìm thấy serial phù hợp</h3>
                            <p>Kiểm tra lại serial, mã đơn, khách hàng hoặc thay đổi bộ lọc.</p>
                            <a href="{{ route('serial-warranty.index') }}" class="wd-btn wd-btn-light">Xóa bộ lọc</a>
                        </div>
                    @endforelse
                </div>

                @if(method_exists($serials, 'links'))
                    <div class="wd-pagination">{{ $serials->links() }}</div>
                @endif
            </main>

            <aside class="wd-detail-column">
                <section class="wd-detail-panel" id="wdDetailPanel">
                    <div class="wd-detail-empty" id="wdDetailEmpty">
                        <div><i class="bi bi-cursor"></i></div>
                        <h3>Chọn một serial</h3>
                        <p>Thông tin sản phẩm, khách hàng, đơn hàng và bảo hành sẽ hiển thị tại đây.</p>
                    </div>

                    <div class="wd-detail-content" id="wdDetailContent" hidden>
                        <div class="wd-detail-head">
                            <span class="wd-detail-label">Hồ sơ serial</span>
                            <button type="button" class="wd-copy-detail" id="wdDetailCopy"><i class="bi bi-copy"></i></button>
                        </div>
                        <h2 id="wdDetailSerial">—</h2>
                        <div class="wd-detail-state-row">
                            <span id="wdDetailTransaction" class="wd-transaction-badge">—</span>
                            <span id="wdDetailWarranty" class="wd-warranty-badge inactive">—</span>
                        </div>

                        <div class="wd-detail-section">
                            <div class="wd-detail-section-title"><i class="bi bi-box-seam"></i>Sản phẩm</div>
                            <strong id="wdDetailProduct">—</strong>
                            <small id="wdDetailSku">—</small>
                        </div>

                        <div class="wd-detail-grid">
                            <div><span>Khách hàng</span><strong id="wdDetailCustomer">—</strong><small id="wdDetailPhone">—</small></div>
                            <div><span>Đơn hàng</span><a id="wdDetailOrder" href="#">—</a><small>Ngày bán: <b id="wdDetailSold">—</b></small></div>
                        </div>

                        <div class="wd-warranty-progress">
                            <div class="wd-progress-head"><span>Thời hạn bảo hành</span><strong id="wdDetailMonths">—</strong></div>
                            <div class="wd-progress-track"><span id="wdProgressBar"></span></div>
                            <div class="wd-progress-dates"><span id="wdDetailStart">—</span><span id="wdDetailEnd">—</span></div>
                        </div>

                        <div class="wd-detail-note">
                            <span><i class="bi bi-sticky"></i>Ghi chú</span>
                            <p id="wdDetailNote">—</p>
                        </div>

                        <div class="wd-detail-actions">
                            <a id="wdOpenOrder" href="#" class="wd-btn wd-btn-light"><i class="bi bi-box-arrow-up-right"></i>Mở đơn</a>
                            @if($canManageWarranty)
                                <button type="button" class="wd-btn wd-btn-primary" id="wdEditSelected"><i class="bi bi-pencil-square"></i>Sửa bảo hành</button>
                                <button type="button" class="wd-btn wd-btn-danger" id="wdDeleteSelected"><i class="bi bi-trash3"></i>Xóa khỏi tra cứu</button>
                            @endif
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</div>

@if($canManageWarranty)
    <div class="wd-modal-backdrop" id="wdModalBackdrop"></div>

    <section class="wd-modal" id="wdAddModal" role="dialog" aria-modal="true" aria-labelledby="wdAddTitle" hidden>
        <header>
            <div><span>Thêm mới</span><h2 id="wdAddTitle">Bổ sung serial bảo hành</h2><p>Dành cho serial đã bán nhưng quên nhập trước đó.</p></div>
            <button type="button" data-wd-close aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
        </header>
        <form method="POST" action="{{ route('serial-warranty.manual-add') }}">
            @csrf
            <div class="wd-modal-body">
                <div class="wd-field full">
                    <label>Danh sách serial <b>*</b></label>
                    <textarea name="serials" class="wd-textarea" rows="4" required placeholder="Mỗi dòng một serial">{{ old('serials') }}</textarea>
                    <small>Có thể dán nhiều serial cùng lúc.</small>
                </div>
                <div class="wd-form-grid">
                    <div class="wd-field full">
                        <label>Sản phẩm <b>*</b></label>
                        <select name="product_id" class="wd-select" required>
                            <option value="">Chọn sản phẩm</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" @selected((int)old('product_id') === (int)$product->id)>{{ $product->sku ? $product->sku.' · ' : '' }}{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wd-field">
                        <label>Khách hàng</label>
                        <select name="customer_id" class="wd-select">
                            <option value="">Chọn khách hàng</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @selected((int)old('customer_id') === (int)$customer->id)>{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wd-field">
                        <label>Đơn hàng</label>
                        <select name="order_id" class="wd-select">
                            <option value="">Chọn đơn hàng</option>
                            @foreach($orders as $order)
                                <option value="{{ $order->id }}" @selected((int)old('order_id') === (int)$order->id)>{{ $order->order_code ?: ('Đơn #'.$order->id) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wd-field"><label>Ngày bán / xuất</label><input type="date" name="sold_at" class="wd-input" value="{{ old('sold_at', now()->format('Y-m-d')) }}"></div>
                    <div class="wd-field"><label>Bắt đầu bảo hành</label><input type="date" name="warranty_start_at" class="wd-input" value="{{ old('warranty_start_at', now()->format('Y-m-d')) }}" data-wd-date-start="add"></div>
                    <div class="wd-field"><label>Số tháng bảo hành <b>*</b></label><input type="number" name="warranty_months" class="wd-input" min="1" max="240" value="{{ old('warranty_months', 60) }}" required data-wd-months="add"></div>
                    <div class="wd-field wd-end-preview"><label>Kết thúc dự kiến</label><div id="wdAddEndPreview">—</div></div>
                    <div class="wd-field full"><label>Ghi chú</label><textarea name="note" class="wd-textarea" rows="3" placeholder="Lý do bổ sung hoặc thông tin cần lưu">{{ old('note', 'Bổ sung serial bảo hành thủ công do quên nhập kho') }}</textarea></div>
                </div>
            </div>
            <footer><button type="button" class="wd-btn wd-btn-light" data-wd-close>Hủy</button><button type="submit" class="wd-btn wd-btn-primary"><i class="bi bi-check2"></i>Lưu serial</button></footer>
        </form>
    </section>

    <section class="wd-modal" id="wdEditModal" role="dialog" aria-modal="true" aria-labelledby="wdEditTitle" hidden>
        <header>
            <div><span>Cập nhật</span><h2 id="wdEditTitle">Sửa thông tin bảo hành</h2><p id="wdEditSubtitle">Serial: —</p></div>
            <button type="button" data-wd-close aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
        </header>
        <form id="wdEditForm" method="POST" data-action-template="{{ url('/serial-warranty/serial/__ID__/warranty') }}">
            @csrf
            <div class="wd-modal-body">
                <div class="wd-form-grid">
                    <div class="wd-field full">
                        <label>Sản phẩm <b>*</b></label>
                        <select name="product_id" id="wdEditProduct" class="wd-select" required>
                            <option value="">Chọn sản phẩm</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->sku ? $product->sku.' · ' : '' }}{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="wd-field"><label>Khách hàng</label><select name="customer_id" id="wdEditCustomer" class="wd-select"><option value="">Chọn khách hàng</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</option>@endforeach</select></div>
                    <div class="wd-field"><label>Đơn hàng</label><select name="order_id" id="wdEditOrder" class="wd-select"><option value="">Chọn đơn hàng</option>@foreach($orders as $order)<option value="{{ $order->id }}">{{ $order->order_code ?: ('Đơn #'.$order->id) }}</option>@endforeach</select></div>
                    <div class="wd-field"><label>Ngày bán / xuất</label><input type="date" name="sold_at" id="wdEditSold" class="wd-input"></div>
                    <div class="wd-field"><label>Bắt đầu bảo hành</label><input type="date" name="warranty_start_at" id="wdEditStart" class="wd-input" data-wd-date-start="edit"></div>
                    <div class="wd-field"><label>Số tháng bảo hành <b>*</b></label><input type="number" name="warranty_months" id="wdEditMonths" class="wd-input" min="1" max="240" required data-wd-months="edit"></div>
                    <div class="wd-field"><label>Kết thúc bảo hành</label><input type="date" name="warranty_end_at" id="wdEditEnd" class="wd-input"></div>
                    <div class="wd-field full"><label>Ghi chú</label><textarea name="note" id="wdEditNote" class="wd-textarea" rows="3"></textarea></div>
                </div>
            </div>
            <footer><button type="button" class="wd-btn wd-btn-light" data-wd-close>Đóng</button><button type="submit" class="wd-btn wd-btn-primary"><i class="bi bi-check2"></i>Lưu thay đổi</button></footer>
        </form>
    </section>
@endif
@endsection
