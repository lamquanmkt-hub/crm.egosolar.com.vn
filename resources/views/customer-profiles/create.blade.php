@extends('layouts.app')

@section('content')
@include('customer-profiles._style')
<div class="cp-page">
    <div class="cp-head">
        <div><h1 class="cp-title">Thêm hồ sơ khách hàng</h1><div class="cp-sub">Tạo hồ sơ đại lý từ khách hàng có sẵn hoặc nhập thủ công.</div></div>
        <div class="cp-actions"><a class="cp-btn" href="{{ route('customer-profiles.index') }}">← Quay lại</a></div>
    </div>

    @include('customer-profiles._messages')

    <form method="POST" action="{{ route('customer-profiles.store') }}" enctype="multipart/form-data" class="cp-card">
        @csrf
        <div class="cp-card-head"><div class="cp-card-title">Thông tin hồ sơ</div><button class="cp-btn primary" type="submit">Lưu hồ sơ</button></div>
        <div class="cp-card-body">@include('customer-profiles._form')</div>
    </form>
</div>
@endsection
