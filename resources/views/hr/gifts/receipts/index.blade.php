@extends('layouts.app')
@section('title', 'Nhập kho quà tặng')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/ego-gifts.css') }}?v={{ file_exists(public_path('css/ego-gifts.css')) ? filemtime(public_path('css/ego-gifts.css')) : '1.0.0' }}">
@endpush

@section('content')
<div class="gift-page">
    <div class="gift-page-head">
        <div>
            <div class="gift-eyebrow">QUẢN LÝ QUÀ TẶNG</div>
            <h1>Nhập kho</h1>
            <p>Gõ trực tiếp tên quà → lưu phiếu nháp → gửi duyệt → duyệt để tự động cộng tồn.</p>
        </div>
        <button class="gift-btn gift-btn--primary" data-bs-toggle="collapse" data-bs-target="#receiptForm">
            <i class="bi bi-plus-lg"></i>Tạo phiếu nhập
        </button>
    </div>

    @include('hr.gifts.partials.nav')
    @include('hr.gifts.partials.alerts')

    <div class="collapse {{ $errors->any() ? 'show' : '' }}" id="receiptForm">
        <section class="gift-card gift-form-card" id="tao-phieu">
            <div class="gift-card-head">
                <div>
                    <h2>Phiếu nhập mới</h2>
                    <p>Không cần tạo danh mục trước, chỉ cần nhập tên quà trực tiếp.</p>
                </div>
            </div>

            <form method="post" action="{{ route('hr.gifts.receipts.store') }}">
                @csrf

                <div class="gift-form-grid">
                    <label>
                        Ngày nhập
                        <input class="gift-input" type="date" name="receipt_date" value="{{ old('receipt_date', now()->format('Y-m-d')) }}" required>
                    </label>

                    <label>
                        Nhà cung cấp
                        <input class="gift-input" name="supplier_name" value="{{ old('supplier_name') }}">
                    </label>

                    <label class="gift-col-2">
                        Ghi chú
                        <textarea class="gift-textarea" name="note">{{ old('note') }}</textarea>
                    </label>
                </div>

                <div class="gift-lines" id="receiptLines"></div>

                <div class="gift-line-actions">
                    <button class="gift-btn gift-btn--light" type="button" id="addReceiptLine">
                        <i class="bi bi-plus-circle"></i>Thêm dòng quà
                    </button>
                    <button class="gift-btn gift-btn--primary">
                        <i class="bi bi-save"></i>Lưu phiếu nháp
                    </button>
                </div>
            </form>
        </section>
    </div>

    <section class="gift-card">
        <form class="gift-filter" method="get">
            <select class="gift-select" name="status">
                <option value="">Tất cả trạng thái</option>
                @foreach(['draft'=>'Nháp','pending'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Từ chối','cancelled'=>'Đã hủy'] as $key=>$label)
                    <option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="gift-btn gift-btn--secondary">Lọc</button>
        </form>

        <div class="gift-table-wrap">
            <table class="gift-table">
                <thead>
                    <tr>
                        <th>Mã phiếu</th>
                        <th>Ngày nhập</th>
                        <th>Nhà cung cấp</th>
                        <th>Số dòng</th>
                        <th>Người tạo</th>
                        <th>Trạng thái</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receipts as $receipt)
                        <tr>
                            <td>
                                <a class="gift-code" href="{{ route('hr.gifts.receipts.show',$receipt) }}">{{ $receipt->code }}</a>
                                <small>{{ optional($receipt->created_at)->format('d/m/Y H:i') }}</small>
                            </td>
                            <td>{{ optional($receipt->receipt_date)->format('d/m/Y') }}</td>
                            <td>{{ $receipt->supplier_name ?: '—' }}</td>
                            <td>{{ $receipt->items_count }}</td>
                            <td>{{ $receipt->creator->name ?? '—' }}</td>
                            <td>@include('hr.gifts.partials.status',['status'=>$receipt->status])</td>
                            <td class="text-end">
                                <a class="gift-icon-btn" href="{{ route('hr.gifts.receipts.show',$receipt) }}"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="gift-empty">Chưa có phiếu nhập quà tặng.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="gift-pagination">{{ $receipts->links() }}</div>
    </section>
</div>

<template id="receiptLineTemplate">
    <div class="gift-line">
        <label>
            Tên quà
            <input class="gift-input" name="gift_name[]" placeholder="Ví dụ: Bình giữ nhiệt / Áo mưa / Mũ bảo hiểm" required>
        </label>

        <label>
            Số lượng
            <input class="gift-input" type="number" min="0.001" step="0.001" name="quantity[]" value="1" required>
        </label>

        <label>
            Đơn giá nhập
            <input class="gift-input" type="number" min="0" step="0.01" name="unit_cost[]" value="0">
        </label>

        <label>
            Ghi chú
            <input class="gift-input" name="item_note[]">
        </label>

        <button type="button" class="gift-remove-line" title="Xóa dòng">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
</template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const box = document.getElementById('receiptLines');
    const tpl = document.getElementById('receiptLineTemplate');

    function add() {
        const node = tpl.content.cloneNode(true);
        const line = node.querySelector('.gift-line');
        line.querySelector('.gift-remove-line').addEventListener('click', () => line.remove());
        box.appendChild(node);
    }

    document.getElementById('addReceiptLine')?.addEventListener('click', add);
    add();
});
</script>
@endpush
