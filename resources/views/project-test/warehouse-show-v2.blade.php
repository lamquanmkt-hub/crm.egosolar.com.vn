@extends('layouts.app')

@section('title', 'Cấp vật tư '.$materialRequest->code)

@push('styles')
<link rel="stylesheet" href="{{ asset('css/project-test.css') }}?v={{ file_exists(public_path('css/project-test.css')) ? filemtime(public_path('css/project-test.css')) : time() }}">
@endpush

@section('content')
@php
    $state = $materialRequest->computed_warehouse_state;
    $locked = $materialRequest->status === 'issued';
    $reserved = $materialRequest->warehouse_status === 'reserved';
@endphp
<div class="pt-page pt-wh-v2-page"
     data-warehouse-v2-root
     data-products-url="{{ route('project-test.warehouse.products',$materialRequest) }}"
     data-warehouses-url="{{ route('project-test.warehouse.warehouses',$materialRequest) }}"
     data-serials-url="{{ route('project-test.warehouse.serials',$materialRequest) }}">
<div class="pt-shell">
    @if(session('success'))<div class="pt-alert pt-alert--success" style="margin-bottom:14px">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="pt-alert" style="margin-bottom:14px">{{ $errors->first() }}</div>@endif

    <section class="pt-card pt-wh-v2-detail-head">
        <div>
            <a class="pt-wh-v2-back" href="{{ route('project-test.warehouse.index') }}"><i class="bi bi-arrow-left"></i> Danh sách cấp vật tư</a>
            <div class="pt-kicker"><i class="bi bi-box-seam"></i> {{ $materialRequest->code }} <span class="pt-wh-v2-state pt-wh-v2-state--{{ $state }}">{{ $states[$state] ?? $state }}</span></div>
            <h1>{{ $materialRequest->project?->name }}</h1>
            <div class="pt-meta">
                <span><i class="bi bi-upc-scan"></i> {{ $materialRequest->project?->code }}</span>
                <span><i class="bi bi-geo-alt"></i> {{ $materialRequest->project?->address ?: 'Chưa có địa chỉ' }}</span>
                <span><i class="bi bi-calendar-event"></i> Cần trước {{ optional($materialRequest->needed_at)->format('d/m/Y') ?: '—' }}</span>
                <span><i class="bi bi-person"></i> Đề xuất bởi {{ $materialRequest->requester?->name ?: '—' }}</span>
            </div>
        </div>
        <a href="{{ route('project-test.show',$materialRequest->project_id) }}" class="pt-btn pt-btn--light"><i class="bi bi-box-arrow-up-right"></i> Hồ sơ công trình</a>
    </section>

    <section class="pt-wh-v2-steps">
        <div class="pt-wh-v2-step done"><span>1</span><div><strong>Kỹ thuật yêu cầu</strong><small>{{ $summary['total'] }} dòng đã được Admin duyệt</small></div></div>
        <div class="pt-wh-v2-step {{ $summary['mapped'] ? 'active' : '' }}"><span>2</span><div><strong>Kho chọn SKU trước</strong><small>Sau đó hệ thống hiện các kho đang có tồn</small></div></div>
        <div class="pt-wh-v2-step {{ $reserved || $locked ? 'active' : '' }}"><span>3</span><div><strong>Giữ & bàn giao</strong><small>Chọn serial rồi giao cho Kỹ thuật</small></div></div>
    </section>

    <div class="pt-alert pt-alert--info pt-wh-v2-safe" style="margin-top:14px"><i class="bi bi-shield-check"></i><span><strong>Thứ tự đúng:</strong> chọn sản phẩm thật → chọn kho đang có sản phẩm → chọn số lượng/serial → giữ hàng → bàn giao. Bản Test chưa trừ tồn kho thật.</span></div>

    <div class="pt-wh-v2-detail-grid">
        <main>
            @if(!$locked)
            <form method="POST" action="{{ route('project-test.warehouse.mapping.save',$materialRequest) }}" class="pt-card pt-wh-v2-match-form" data-warehouse-mapping-form>
                @csrf
                <div class="pt-wh-v2-section-head">
                    <div><span class="pt-wh-v2-number">2</span><div><h2>Ghép yêu cầu với hàng thật</h2><p>Mỗi dòng có thể lấy từ kho khác nhau. Kho chỉ hiện sau khi đã chọn SKU.</p></div></div>
                    @if(!$reserved)<button class="pt-btn pt-btn--brand"><i class="bi bi-save"></i> Lưu ghép hàng</button>@else<span class="pt-wh-v2-state pt-wh-v2-state--reserved">Đang giữ hàng · bỏ giữ để sửa</span>@endif
                </div>

                <div class="pt-wh-v2-order-note">
                    <span><b>1</b> Chọn sản phẩm</span><i class="bi bi-arrow-right"></i>
                    <span><b>2</b> Chọn kho có tồn</span><i class="bi bi-arrow-right"></i>
                    <span><b>3</b> Chọn số lượng & serial</span>
                </div>

                <div class="pt-wh-v2-items">
                    @foreach($materialRequest->items as $item)
                        @php $allocation=$item->allocations->first(); @endphp
                        <article class="pt-wh-v2-item"
                                 data-allocation-row
                                 data-item-id="{{ $item->id }}"
                                 data-row-state="{{ $allocation?->status ?: 'waiting_match' }}"
                                 data-row-locked="{{ $reserved ? '1' : '0' }}"
                                 data-selected-warehouse-id="{{ $allocation?->warehouse_id }}"
                                 data-selected-serials='@json($allocation?->selected_serial_unit_ids ?: [])'>
                            <section class="pt-wh-v2-request-side">
                                <div class="pt-wh-v2-side-label">Kỹ thuật yêu cầu</div>
                                <h3>{{ $item->item_name }}</h3>
                                <div class="pt-wh-v2-request-qty"><strong>{{ rtrim(rtrim(number_format((float)$item->quantity,3,'.',''),'0'),'.') }}</strong> {{ $item->unit }}</div>
                                <p>{{ $item->note ?: 'Không có yêu cầu thông số bổ sung.' }}</p>
                            </section>

                            <section class="pt-wh-v2-stock-side">
                                <div class="pt-wh-v2-side-label">Kho cấp thực tế</div>

                                <div class="pt-wh-v2-picker-step">
                                    <div class="pt-wh-v2-picker-step__head"><span>1</span><div><strong>Chọn sản phẩm thật</strong><small>Tìm theo tên, SKU hoặc barcode trên toàn bộ danh mục.</small></div></div>
                                    <input type="hidden" name="items[{{ $item->id }}][product_id]" value="{{ $allocation?->product_id }}" data-product-id>
                                    <div class="pt-wh-v2-product-picker">
                                        <i class="bi bi-search"></i>
                                        <input type="text" class="pt-input" value="{{ $allocation?->product?->name }}" placeholder="Gõ tên, SKU hoặc barcode..." data-product-search autocomplete="off" {{ $reserved ? 'disabled' : '' }}>
                                        <div class="pt-wh-v2-product-results" data-product-results hidden></div>
                                    </div>
                                    <div class="pt-wh-v2-selected-product {{ $allocation ? '' : 'is-empty' }}" data-selected-product>
                                        @if($allocation)
                                            <div><strong>{{ $allocation->product?->name }}</strong><small>{{ $allocation->product?->sku ?: 'Không có SKU' }} · {{ $allocation->product?->unit ?: $item->unit }}</small></div>
                                            <div class="pt-wh-v2-stock-metrics"><span>{{ $allocation->is_serialized ? 'Quản lý serial' : 'Theo số lượng' }}</span></div>
                                        @else
                                            <span>Chưa chọn sản phẩm thật</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="pt-wh-v2-picker-step {{ $allocation ? '' : 'is-disabled' }}" data-warehouse-step>
                                    <div class="pt-wh-v2-picker-step__head"><span>2</span><div><strong>Chọn kho đang có hàng</strong><small>Hệ thống xếp kho còn nhiều hàng lên trước.</small></div></div>
                                    <input type="hidden" name="items[{{ $item->id }}][warehouse_id]" value="{{ $allocation?->warehouse_id }}" data-row-warehouse-id>
                                    <select class="pt-select" data-row-warehouse {{ $reserved ? 'disabled' : '' }}>
                                        @if($allocation)
                                            <option value="{{ $allocation->warehouse_id }}" selected>{{ $allocation->warehouse?->name }} · Có thể cấp lúc ghép: {{ rtrim(rtrim(number_format((float)$allocation->available_snapshot,3,'.',''),'0'),'.') }}</option>
                                        @else
                                            <option value="">-- Chọn sản phẩm trước --</option>
                                        @endif
                                    </select>
                                    <div class="pt-wh-v2-warehouse-stock {{ $allocation ? '' : 'is-empty' }}" data-warehouse-stock>
                                        @if($allocation)
                                            <span>Kho đã chọn</span><strong>{{ $allocation->warehouse?->name }}</strong><small>Tồn khả dụng lúc ghép: {{ rtrim(rtrim(number_format((float)$allocation->available_snapshot,3,'.',''),'0'),'.') }}</small>
                                        @else
                                            <span>Chưa có kho để hiển thị</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="pt-wh-v2-picker-step {{ $allocation ? '' : 'is-disabled' }}" data-quantity-step>
                                    <div class="pt-wh-v2-picker-step__head"><span>3</span><div><strong>Số lượng và serial</strong><small>Không được vượt số lượng Kỹ thuật yêu cầu hoặc tồn khả dụng.</small></div></div>
                                    <div class="pt-wh-v2-allocation-fields">
                                        <label><span>Số lượng cấp</span><input class="pt-input" type="number" step="0.001" min="0.001" max="{{ $item->quantity }}" name="items[{{ $item->id }}][quantity]" value="{{ $allocation?->allocated_quantity ?: $item->quantity }}" data-allocated-qty required {{ $reserved ? 'readonly' : '' }}></label>
                                        <label><span>Ghi chú Kho</span><input class="pt-input" name="items[{{ $item->id }}][note]" value="{{ $allocation?->note }}" placeholder="Hàng thay thế, lô ưu tiên..." {{ $reserved ? 'readonly' : '' }}></label>
                                    </div>

                                    <div class="pt-wh-v2-serial-box" data-serial-box @if(!$allocation?->is_serialized) hidden @endif>
                                        <div class="pt-wh-v2-serial-head"><strong>Chọn serial thật trong kho</strong><span data-serial-count>{{ count($allocation?->selected_serial_unit_ids ?: []) }}/{{ (int)ceil((float)($allocation?->allocated_quantity ?: 0)) }}</span></div>
                                        <div class="pt-wh-v2-serial-list" data-serial-list>
                                            @if($allocation?->selected_serial_codes)<div class="pt-help">Đã chọn: {!! nl2br(e($allocation->selected_serial_codes)) !!}</div>@else<div class="pt-help">Chọn sản phẩm và kho để tải serial khả dụng.</div>@endif
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-wh-v2-row-result" data-row-result>
                                    @if($allocation)
                                        <span class="pt-wh-v2-state pt-wh-v2-state--{{ $allocation->status }}">{{ $allocation->status==='shortage' ? 'Thiếu hàng' : ($allocation->status==='reserved' ? 'Đã giữ' : 'Đã ghép SKU & kho') }}</span>
                                    @else
                                        <span class="pt-wh-v2-state pt-wh-v2-state--waiting_match">Chưa ghép</span>
                                    @endif
                                </div>
                            </section>
                        </article>
                    @endforeach
                </div>
            </form>
            @else
                <section class="pt-card pt-wh-v2-match-form">
                    <div class="pt-wh-v2-section-head"><div><span class="pt-wh-v2-number"><i class="bi bi-check2"></i></span><div><h2>Hàng đã bàn giao</h2><p>Phiếu đã khóa chỉnh sửa SKU và kho xuất.</p></div></div><button type="button" class="pt-btn pt-btn--light" onclick="window.print()"><i class="bi bi-printer"></i> In phiếu</button></div>
                    <div class="pt-wh-v2-items">
                        @foreach($materialRequest->items as $item)
                            @php $allocation=$item->allocations->first(); @endphp
                            <article class="pt-wh-v2-item">
                                <section class="pt-wh-v2-request-side"><div class="pt-wh-v2-side-label">Kỹ thuật yêu cầu</div><h3>{{ $item->item_name }}</h3><div class="pt-wh-v2-request-qty"><strong>{{ $item->quantity }}</strong> {{ $item->unit }}</div><p>{{ $item->note }}</p></section>
                                <section class="pt-wh-v2-stock-side"><div class="pt-wh-v2-side-label">Kho đã bàn giao</div><div class="pt-wh-v2-selected-product"><div><strong>{{ $allocation?->product?->name ?: '—' }}</strong><small>{{ $allocation?->product?->sku }} · {{ $allocation?->warehouse?->name }}</small></div></div><div class="pt-summary" style="margin-top:10px"><div><small>Số lượng</small><strong>{{ $allocation?->issued_quantity }} {{ $allocation?->product?->unit ?: $item->unit }}</strong></div><div><small>Serial</small><strong>{!! $allocation?->selected_serial_codes ? nl2br(e($allocation->selected_serial_codes)) : 'Không quản lý serial' !!}</strong></div></div></section>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </main>

        <aside class="pt-wh-v2-summary-side">
            <section class="pt-card pt-wh-v2-summary-card" data-live-summary>
                <div class="pt-wh-v2-section-head"><div><span class="pt-wh-v2-number">3</span><div><h2>Kiểm tra & bàn giao</h2><p>Chỉ bấm xuất khi mọi dòng đã đủ SKU, kho và serial.</p></div></div></div>
                <div class="pt-wh-v2-summary-grid">
                    <div><small>Dòng yêu cầu</small><strong data-summary-total>{{ $summary['total'] }}</strong></div>
                    <div><small>Đã chọn SKU & kho</small><strong data-summary-mapped>{{ $summary['mapped'] }}/{{ $summary['total'] }}</strong></div>
                    <div><small>Dòng thiếu hàng</small><strong data-summary-shortage>{{ $summary['shortage'] }}</strong></div>
                    <div><small>Serial đã chọn</small><strong data-summary-serial>{{ $summary['serialSelected'] }}/{{ $summary['serialRequired'] }}</strong></div>
                </div>

                <div class="pt-wh-v2-ready-banner {{ in_array($state,['ready','reserved','issued'],true) ? 'is-ready' : '' }}" data-ready-banner>
                    <i class="bi {{ in_array($state,['ready','reserved','issued'],true) ? 'bi-check-circle' : 'bi-exclamation-circle' }}"></i>
                    <div><strong>{{ in_array($state,['ready','reserved','issued'],true) ? 'Hàng đã sẵn sàng' : 'Chưa đủ điều kiện' }}</strong><small>{{ $states[$state] ?? $state }}</small></div>
                </div>

                @if(!$locked)
                    @if(!$reserved)
                        <form method="POST" action="{{ route('project-test.warehouse.reserve',$materialRequest) }}" data-confirm="Giữ số hàng này cho công trình trong module Test?">@csrf<button class="pt-btn pt-btn--dark pt-wh-v2-full" @disabled($state!=='ready')><i class="bi bi-bookmark-check"></i> Giữ hàng Test</button></form>
                    @else
                        <form method="POST" action="{{ route('project-test.warehouse.release',$materialRequest) }}" data-confirm="Bỏ giữ toàn bộ hàng của phiếu này?">@csrf<button class="pt-btn pt-btn--light pt-wh-v2-full"><i class="bi bi-bookmark-x"></i> Bỏ giữ hàng</button></form>
                    @endif

                    <form method="POST" action="{{ route('project-test.warehouse.issue',$materialRequest) }}" data-confirm="Xác nhận bàn giao hàng cho Kỹ thuật? Tồn thật chưa bị trừ trong bản Test." class="pt-wh-v2-issue-form">@csrf
                        <label class="pt-label">Người nhận hàng</label>
                        <select class="pt-select" name="receiver_id" required @disabled(!$reserved)>
                            <option value="">-- Chọn Kỹ thuật nhận --</option>
                            @foreach($receivers as $receiver)<option value="{{ $receiver->id }}" @selected($materialRequest->receiver_id==$receiver->id || (!$materialRequest->receiver_id && $materialRequest->project?->lead_technician_id==$receiver->id))>{{ $receiver->name }}</option>@endforeach
                        </select>
                        <label class="pt-label" style="margin-top:10px">Ghi chú bàn giao</label>
                        <textarea class="pt-textarea" name="issue_note" placeholder="Tình trạng hàng, số kiện, người vận chuyển..." @disabled(!$reserved)>{{ $materialRequest->issue_note }}</textarea>
                        <button class="pt-btn pt-btn--brand pt-wh-v2-full" style="margin-top:10px" @disabled(!$reserved)><i class="bi bi-box-arrow-up-right"></i> Xuất kho Test & bàn giao</button>
                    </form>
                @else
                    <div class="pt-alert pt-alert--success"><strong>Đã bàn giao:</strong> {{ optional($materialRequest->handed_over_at ?: $materialRequest->issued_at)->format('d/m/Y H:i') }}<br>Người nhận: {{ $materialRequest->receiver?->name ?: '—' }}<br>Người xuất: {{ $materialRequest->issuer?->name ?: '—' }}</div>
                @endif
            </section>

            <section class="pt-card pt-section pt-wh-v2-rule-card">
                <h3>Kho chỉ cần làm đúng thứ tự</h3>
                <ol>
                    <li><strong>Chọn SKU thật</strong> phù hợp yêu cầu Kỹ thuật.</li>
                    <li><strong>Chọn kho có tồn</strong> từ danh sách hệ thống gợi ý.</li>
                    <li><strong>Chọn số lượng/serial</strong> đúng kho vừa chọn.</li>
                    <li><strong>Giữ và bàn giao</strong> cho người Kỹ thuật nhận.</li>
                </ol>
            </section>
        </aside>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/project-test.js') }}?v={{ file_exists(public_path('js/project-test.js')) ? filemtime(public_path('js/project-test.js')) : time() }}"></script>
<script src="{{ asset('js/project-test-warehouse-v2.js') }}?v={{ file_exists(public_path('js/project-test-warehouse-v2.js')) ? filemtime(public_path('js/project-test-warehouse-v2.js')) : time() }}"></script>
@endpush
