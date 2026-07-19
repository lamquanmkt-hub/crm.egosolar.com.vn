@extends('layouts.app')

@section('content')
@include('hr.office-supply-process._style')

<div class="vpp-page">
    @if(session('success'))
        <div class="vpp-alert">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="vpp-error">{{ $errors->first() }}</div>
    @endif

    <div class="vpp-mini-hero">
        <div>
            <h1 class="vpp-mini-title">Chi tiết phiếu cấp phát VPP</h1>
            <p class="vpp-mini-sub">
                Mã phiếu: {{ $requestRow->code ?? '-' }}
                · Người nhận: {{ $requestRow->receiver_name ?: $requestRow->requester_name }}
                · Phòng ban: {{ $requestRow->department_name ?? '-' }}
            </p>
        </div>

        <div class="vpp-hero-actions">
            <a class="vpp-btn-outline" href="{{ url('/nhan-su/quy-trinh-phan-bo-vpp') }}">← Quay lại</a>
        </div>
    </div>

    <div class="vpp-layout">
        <div>
            <div class="vpp-card">
                <div class="vpp-card-head">
                    <div>
                        <h2 class="vpp-card-title">Vật phẩm đã cấp phát</h2>
                        <div class="vpp-card-note">
                            Ngày cấp:
                            {{ !empty($requestRow->created_at) ? date('d/m/Y H:i', strtotime($requestRow->created_at)) : '-' }}
                        </div>
                    </div>
                </div>

                <div class="vpp-card-body">
                    @if(isset($items) && $items->count())
                        <div class="vpp-table-wrap">
                            <table class="vpp-table">
                                <thead>
                                    <tr>
                                        <th>Vật phẩm</th>
                                        <th>ĐVT</th>
                                        <th>SL đề nghị</th>
                                        <th>SL cấp phát</th>
                                        <th>Tồn hiện tại</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $i)
                                        <tr>
                                            <td><strong>{{ $i->item_name }}</strong></td>
                                            <td>{{ $i->unit ?: 'cái' }}</td>
                                            <td>{{ number_format($i->requested_qty ?? 0, 0) }}</td>
                                            <td>{{ number_format($i->issued_qty ?? $i->hr_qty ?? $i->requested_qty ?? 0, 0) }}</td>
                                            <td>
                                                @if(isset($i->current_stock) && $i->current_stock !== null)
                                                    {{ number_format($i->current_stock, 0) }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="vpp-empty">Phiếu này chưa có vật phẩm.</div>
                    @endif
                </div>
            </div>

            <div class="vpp-card">
                <div class="vpp-card-head">
                    <div>
                        <h2 class="vpp-card-title">Lịch sử trừ / cộng VPP riêng</h2>
                        <div class="vpp-card-note">Lịch sử này chỉ thuộc sổ VPP HR, không liên quan kho chính.</div>
                    </div>
                </div>

                <div class="vpp-card-body">
                    @if(isset($movements) && $movements->count())
                        <div class="vpp-table-wrap">
                            <table class="vpp-table">
                                <thead>
                                    <tr>
                                        <th>Thời gian</th>
                                        <th>Loại</th>
                                        <th>Vật phẩm</th>
                                        <th>SL</th>
                                        <th>Tồn trước</th>
                                        <th>Tồn sau</th>
                                        <th>Người thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($movements as $m)
                                        <tr>
                                            <td>{{ $m->moved_at ? date('d/m/Y H:i', strtotime($m->moved_at)) : '-' }}</td>
                                            <td>
                                                @if($m->type === 'in')
                                                    <span class="vpp-badge vpp-in">Nhập</span>
                                                @else
                                                    <span class="vpp-badge vpp-out">Cấp</span>
                                                @endif
                                            </td>
                                            <td>{{ $m->product_name }}</td>
                                            <td>{{ number_format($m->qty ?? 0, 0) }} {{ $m->unit }}</td>
                                            <td>{{ number_format($m->before_qty ?? 0, 0) }}</td>
                                            <td>{{ number_format($m->after_qty ?? 0, 0) }}</td>
                                            <td>{{ $m->user_name ?: 'Hệ thống' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="vpp-empty">Chưa có lịch sử xuất nhập cho phiếu này.</div>
                    @endif
                </div>
            </div>
        </div>

        <div>
            <div class="vpp-card">
                <div class="vpp-card-head">
                    <div>
                        <h2 class="vpp-card-title">Thông tin phiếu</h2>
                        <div class="vpp-card-note">Thông tin người nhận và phòng ban.</div>
                    </div>
                </div>

                <div class="vpp-card-body">
                    <table class="vpp-table">
                        <tbody>
                            <tr>
                                <th>Mã phiếu</th>
                                <td><strong>{{ $requestRow->code ?? '-' }}</strong></td>
                            </tr>
                            <tr>
                                <th>Người nhận</th>
                                <td>{{ $requestRow->receiver_name ?: $requestRow->requester_name }}</td>
                            </tr>
                            <tr>
                                <th>Phòng ban</th>
                                <td>{{ $requestRow->department_name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Mục đích</th>
                                <td>{{ $requestRow->purpose ?: '-' }}</td>
                            </tr>
                            <tr>
                                <th>Ghi chú</th>
                                <td>{{ $requestRow->note ?: '-' }}</td>
                            </tr>
                            <tr>
                                <th>Ngày tạo</th>
                                <td>{{ !empty($requestRow->created_at) ? date('d/m/Y H:i', strtotime($requestRow->created_at)) : '-' }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div style="height:10px"></div>

                    <form method="POST"
                          action="{{ url('/nhan-su/quy-trinh-phan-bo-vpp/'.$requestRow->id) }}"
                          onsubmit="return confirm('Xoá phiếu này? Nếu phiếu có cấp phát, hệ thống sẽ hoàn lại tồn VPP riêng nếu controller đang hỗ trợ hoàn kho.')">
                        @csrf
                        @method('DELETE')
                        <button class="vpp-btn-danger" type="submit">Xoá phiếu</button>
                    </form>
                </div>
            </div>

            <div class="vpp-card">
                <div class="vpp-card-head">
                    <div>
                        <h2 class="vpp-card-title">Ghi chú</h2>
                        <div class="vpp-card-note">Trang chi tiết đã bỏ biến logs cũ.</div>
                    </div>
                </div>

                <div class="vpp-card-body">
                    <div class="vpp-muted">
                        Trang này chỉ hiển thị chi tiết phiếu cấp phát và lịch sử VPP riêng.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
