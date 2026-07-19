@extends('layouts.app')

@section('content')
@include('customer-profiles._style')
<div class="cp-page">
    <div class="cp-head">
        <div>
            <h1 class="cp-title">{{ $profile->agent_name }}</h1>
            <div class="cp-sub">{{ $profile->customer_name ?: 'Hồ sơ chưa gắn khách hàng' }} • {{ $profile->agent_level ?: 'Chưa phân cấp' }}</div>
        </div>
        <div class="cp-actions">
            <a class="cp-btn" href="{{ route('customer-profiles.index') }}">← Danh sách</a>
            <a class="cp-btn primary" href="{{ route('customer-profiles.edit', $profile) }}">Sửa hồ sơ</a>
        </div>
    </div>

    @include('customer-profiles._messages')

    <div class="cp-grid-kpi">
        <div class="cp-kpi"><div class="label">Tiền đặt cọc</div><div class="value">{{ number_format((float) $profile->deposit_amount, 0, ',', '.') }} đ</div><div class="hint">{{ optional($profile->deposit_date)->format('d/m/Y') ?: 'Chưa có ngày' }}</div></div>
        <div class="cp-kpi"><div class="label">Trạng thái</div><div class="value" style="font-size:18px"><span class="cp-badge {{ $profile->status }}">{{ $statuses[$profile->status] ?? $profile->status }}</span></div><div class="hint">Cập nhật: {{ optional($profile->updated_at)->format('d/m/Y H:i') }}</div></div>
        <div class="cp-kpi"><div class="label">Cấp đại lý</div><div class="value" style="font-size:20px">{{ $profile->agent_level ?: ($profile->price_tier_name ?: '—') }}</div><div class="hint">Bậc giá/đại lý</div></div>
        <div class="cp-kpi"><div class="label">Giấy tờ</div><div class="value">{{ $documents->count() }}</div><div class="hint">File liên quan</div></div>
        <div class="cp-kpi"><div class="label">Ngày chăm sóc</div><div class="value" style="font-size:20px">{{ optional($profile->next_followup_date)->format('d/m/Y') ?: '—' }}</div><div class="hint">Nhắc việc nội bộ</div></div>
    </div>

    <div class="cp-detail">
        <div>
            <div class="cp-card">
                <div class="cp-card-head"><div class="cp-card-title">Thông tin tổng quan</div></div>
                <div class="cp-card-body">
                    <div class="cp-info-grid">
                        <div class="cp-info"><div class="k">Tên khách hàng</div><div class="v">{{ $profile->customer_name ?: '—' }}</div></div>
                        <div class="cp-info"><div class="k">Tên đại lý</div><div class="v">{{ $profile->agent_name }}</div></div>
                        <div class="cp-info"><div class="k">Cấp đại lý</div><div class="v">{{ $profile->agent_level ?: ($profile->price_tier_name ?: '—') }}</div></div>
                        <div class="cp-info"><div class="k">Số điện thoại</div><div class="v">{{ $profile->phone ?: '—' }}</div></div>
                        <div class="cp-info"><div class="k">Email</div><div class="v">{{ $profile->email ?: '—' }}</div></div>
                        <div class="cp-info"><div class="k">Mã số thuế</div><div class="v">{{ $profile->tax_code ?: '—' }}</div></div>
                        <div class="cp-info"><div class="k">Mã hợp đồng</div><div class="v">{{ $profile->contract_code ?: '—' }}</div></div>
                        <div class="cp-info"><div class="k">Ngày hợp đồng</div><div class="v">{{ optional($profile->contract_date)->format('d/m/Y') ?: '—' }}</div></div>
                        <div class="cp-info"><div class="k">Người đại diện</div><div class="v">{{ $profile->representative_name ?: '—' }}</div></div>
                    </div>
                    <div style="margin-top:12px" class="cp-info"><div class="k">Địa chỉ</div><div class="v">{{ $profile->address ?: '—' }}</div></div>
                    <div style="margin-top:12px" class="cp-info"><div class="k">Ghi chú</div><div class="v" style="white-space:pre-line">{{ $profile->note ?: '—' }}</div></div>
                </div>
            </div>

                        @include('ego_customer_profile_documents.preview_box')
        </div>

        <div>
            

<form class="cp-card" method="POST" action="{{ route('customer-profiles.documents.store', $profile) }}" enctype="multipart/form-data">
                @csrf
                <div class="cp-card-head"><div class="cp-card-title">Upload giấy tờ</div></div>
                <div class="cp-card-body">
                    <div class="cp-field" style="margin-bottom:10px"><label>Loại giấy tờ</label><select class="cp-select" name="document_type">@foreach($documentTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="cp-field" style="margin-bottom:10px"><label>File</label><input class="cp-input" type="file" name="documents[]" multiple required></div>
                    <div class="cp-field" style="margin-bottom:12px"><label>Ghi chú file</label><textarea class="cp-textarea" name="document_note"></textarea></div>
                    <button class="cp-btn primary" type="submit" style="width:100%">Tải lên</button>
                </div>
            </form>

            <div class="cp-card">
                <div class="cp-card-head"><div class="cp-card-title">Tiến độ hồ sơ</div></div>
                <div class="cp-card-body">
                    @php
                        $score = 0;
                        if($profile->customer_id) $score += 20;
                        if($profile->agent_level || $profile->price_tier_id) $score += 20;
                        if((float)$profile->deposit_amount > 0) $score += 20;
                        if($profile->contract_code || $profile->contract_date) $score += 20;
                        if($documents->count() > 0) $score += 20;
                    @endphp
                    <div style="display:flex;justify-content:space-between;font-weight:950;margin-bottom:8px"><span>Hoàn thiện</span><span>{{ $score }}%</span></div>
                    <div class="cp-progress"><span style="width:{{ $score }}%"></span></div>
                    <div class="cp-small cp-muted" style="margin-top:10px">Điểm dựa trên khách hàng, cấp đại lý, đặt cọc, hợp đồng và giấy tờ.</div>
                </div>
            </div>
        </div>
    </div>

    @include('ego_order_documents.profile_box')
</div>
@endsection
