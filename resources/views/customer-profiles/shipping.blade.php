@extends('layouts.app')

@section('content')
@include('customer-profiles._style')

<style>
    .ship-hero{
        background:linear-gradient(135deg,#0f2f67,#0f8b8d);
        color:#fff;
        border-radius:22px;
        padding:22px;
        margin-bottom:16px;
        box-shadow:0 18px 48px rgba(15,47,103,.16);
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:16px;
    }

    .ship-hero h1{
        margin:0;
        font-size:26px;
        font-weight:950;
        letter-spacing:-.03em;
    }

    .ship-hero p{
        margin:6px 0 0;
        color:#d7f7ff;
        font-size:13px;
        font-weight:700;
    }

    .ship-count{
        min-width:110px;
        border:1px solid rgba(255,255,255,.22);
        background:rgba(255,255,255,.13);
        border-radius:18px;
        padding:14px;
        text-align:center;
    }

    .ship-count b{
        display:block;
        font-size:28px;
        line-height:1;
    }

    .ship-count span{
        display:block;
        margin-top:5px;
        font-size:12px;
        font-weight:800;
        color:#d7f7ff;
    }

    .ship-grid{
        display:grid;
        grid-template-columns:420px minmax(0,1fr);
        gap:14px;
        align-items:start;
    }

    .ship-form-grid{
        display:grid;
        grid-template-columns:1fr;
        gap:12px;
    }

    .ship-table-actions{
        display:flex;
        gap:8px;
        flex-wrap:wrap;
    }

    .ship-route{
        display:inline-flex;
        align-items:center;
        padding:7px 10px;
        border-radius:999px;
        background:#ecfeff;
        color:#0f766e;
        border:1px solid #99f6e4;
        font-size:12px;
        font-weight:900;
    }

    .ship-edit{
        display:none;
        margin-top:12px;
        padding:12px;
        border-radius:16px;
        border:1px dashed #93c5fd;
        background:#f8fbff;
    }

    .ship-edit.show{
        display:block;
    }

    .ship-edit-grid{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:10px;
    }

    .ship-edit-grid .wide{
        grid-column:1 / -1;
    }

    .ship-empty{
        padding:42px 18px;
        text-align:center;
        color:#64748b;
        font-weight:750;
    }

    @media(max-width:1100px){
        .ship-grid{
            grid-template-columns:1fr;
        }
    }

    @media(max-width:720px){
        .ship-hero{
            flex-direction:column;
            align-items:flex-start;
        }

        .ship-edit-grid{
            grid-template-columns:1fr;
        }

        .ship-edit-grid .wide{
            grid-column:1;
        }
    }
</style>

<div class="cp-page">
    <div class="ship-hero">
        <div>
            <h1>Danh sách vận chuyển</h1>
            <p>Quản lý riêng các đơn vị vận chuyển, SĐT, địa chỉ, tuyến giao và ghi chú để Sales/Kho tra cứu nhanh.</p>
        </div>

        <div class="ship-count">
            <b>{{ number_format($shippingUnits->total()) }}</b>
            <span>Đơn vị</span>
        </div>
    </div>

    @include('customer-profiles._messages')

    <div class="ship-grid">
        <div class="cp-card">
            <div class="cp-card-head">
                <div>
                    <div class="cp-card-title">+ Thêm đơn vị vận chuyển</div>
                    <div class="cp-small cp-muted">Nhập thông tin nhà xe, bưu cục hoặc đơn vị giao hàng.</div>
                </div>
            </div>

            <div class="cp-card-body">
                <form class="ship-form-grid" method="POST" action="{{ route('customer-profiles.shipping.store') }}">
                    @csrf

                    <div class="cp-field">
                        <label>Tên đơn vị *</label>
                        <input class="cp-input" name="name" value="{{ old('name') }}" placeholder="VD: Viettel Post, GHTK, Nhà xe A..." required>
                    </div>

                    <div class="cp-field">
                        <label>SĐT</label>
                        <input class="cp-input" name="phone" value="{{ old('phone') }}" placeholder="Số điện thoại liên hệ">
                    </div>

                    <div class="cp-field">
                        <label>Địa chỉ</label>
                        <input class="cp-input" name="address" value="{{ old('address') }}" placeholder="Địa chỉ bưu cục / nhà xe / điểm gửi">
                    </div>

                    <div class="cp-field">
                        <label>Tuyến</label>
                        <input class="cp-input" name="route" value="{{ old('route') }}" placeholder="VD: HCM - Tây Nguyên">
                    </div>

                    <div class="cp-field">
                        <label>Ghi chú</label>
                        <textarea class="cp-input" name="note" rows="3" placeholder="Giờ nhận hàng, COD, người phụ trách...">{{ old('note') }}</textarea>
                    </div>

                    <button class="cp-btn primary" type="submit">+ Thêm vận chuyển</button>
                </form>
            </div>
        </div>

        <div class="cp-card">
            <div class="cp-card-head">
                <div>
                    <div class="cp-card-title">Bảng đơn vị vận chuyển</div>
                    <div class="cp-small cp-muted">Tên đơn vị / SĐT / Địa chỉ / Tuyến / Ghi chú</div>
                </div>

                <form method="GET" action="{{ route('customer-profiles.shipping.index') }}" style="display:flex;gap:8px;align-items:center">
                    <input class="cp-input" style="min-width:260px" name="q" value="{{ $q }}" placeholder="Tìm tên, SĐT, tuyến, địa chỉ...">
                    <button class="cp-btn primary" type="submit">Lọc</button>
                    @if($q !== '')
                        <a class="cp-btn" href="{{ route('customer-profiles.shipping.index') }}">Xóa</a>
                    @endif
                </form>
            </div>

            <div class="cp-table-wrap">
                <table class="cp-table">
                    <thead>
                        <tr>
                            <th>Tên đơn vị</th>
                            <th>SĐT</th>
                            <th>Địa chỉ</th>
                            <th>Tuyến</th>
                            <th>Ghi chú</th>
                            <th style="min-width:180px">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($shippingUnits as $shipping)
                            <tr>
                                <td>
                                    <strong>{{ $shipping->name }}</strong>
                                </td>
                                <td>{{ $shipping->phone ?: '—' }}</td>
                                <td>{{ $shipping->address ?: '—' }}</td>
                                <td>
                                    @if($shipping->route)
                                        <span class="ship-route">{{ $shipping->route }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $shipping->note ?: '—' }}</td>
                                <td>
                                    <div class="ship-table-actions">
                                        <button type="button" class="cp-btn" onclick="toggleShippingEdit({{ $shipping->id }})">Sửa</button>

                                        <form method="POST" action="{{ route('customer-profiles.shipping.destroy', $shipping->id) }}" onsubmit="return confirm('Xóa đơn vị vận chuyển này?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="cp-btn danger" type="submit">Xóa</button>
                                        </form>
                                    </div>

                                    <div class="ship-edit" id="shipping-edit-{{ $shipping->id }}">
                                        <form class="ship-edit-grid" method="POST" action="{{ route('customer-profiles.shipping.update', $shipping->id) }}">
                                            @csrf
                                            @method('PUT')

                                            <div class="cp-field">
                                                <label>Tên đơn vị *</label>
                                                <input class="cp-input" name="name" value="{{ $shipping->name }}" required>
                                            </div>

                                            <div class="cp-field">
                                                <label>SĐT</label>
                                                <input class="cp-input" name="phone" value="{{ $shipping->phone }}">
                                            </div>

                                            <div class="cp-field wide">
                                                <label>Địa chỉ</label>
                                                <input class="cp-input" name="address" value="{{ $shipping->address }}">
                                            </div>

                                            <div class="cp-field">
                                                <label>Tuyến</label>
                                                <input class="cp-input" name="route" value="{{ $shipping->route }}">
                                            </div>

                                            <div class="cp-field">
                                                <label>Ghi chú</label>
                                                <input class="cp-input" name="note" value="{{ $shipping->note }}">
                                            </div>

                                            <div class="cp-field wide">
                                                <button class="cp-btn primary" type="submit">Lưu thay đổi</button>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="ship-empty">Chưa có đơn vị vận chuyển. Thêm đơn vị đầu tiên ở form bên trái.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($shippingUnits, 'links'))
                <div class="cp-card-body">
                    {{ $shippingUnits->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<script>
function toggleShippingEdit(id){
    var el = document.getElementById('shipping-edit-' + id);
    if (el) {
        el.classList.toggle('show');
    }
}
</script>
@endsection
