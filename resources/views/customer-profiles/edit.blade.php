@extends('layouts.app')

@section('content')
@include('customer-profiles._style')
<div class="cp-page">
    <div class="cp-head">
        <div><h1 class="cp-title">Sửa hồ sơ khách hàng</h1><div class="cp-sub">{{ $profile->agent_name }}</div></div>
        <div class="cp-actions"><a class="cp-btn" href="{{ route('customer-profiles.show', $profile) }}">Xem</a><a class="cp-btn" href="{{ route('customer-profiles.index') }}">← Quay lại</a></div>
    </div>

    @include('customer-profiles._messages')

    <form method="POST" action="{{ route('customer-profiles.update', $profile) }}" enctype="multipart/form-data" class="cp-card">
        @csrf @method('PUT')
        <div class="cp-card-head"><div class="cp-card-title">Thông tin hồ sơ</div><button class="cp-btn primary" type="submit">Lưu thay đổi</button></div>
        <div class="cp-card-body">@include('customer-profiles._form')</div>
    </form>

    <div class="cp-card">
        <div class="cp-card-head"><div class="cp-card-title">Giấy tờ hiện có</div></div>
        <div class="cp-card-body">
            <div class="cp-doc-grid">
                @forelse($documents as $doc)
                    <div class="cp-doc">
                        <div class="cp-doc-name">{{ $doc->original_name }}</div>
                        <div class="cp-doc-meta">{{ $documentTypes[$doc->document_type] ?? $doc->document_type }} • {{ number_format($doc->size_bytes / 1024, 1) }} KB</div>
                        <div class="cp-actions" style="justify-content:flex-start">
                            <a class="cp-btn" href="{{ route('customer-profiles.documents.download', [$profile, $doc]) }}">Tải</a>
                            <form method="POST" action="{{ route('customer-profiles.documents.destroy', [$profile, $doc]) }}" onsubmit="return confirm('Xóa file này?')">
                                @csrf @method('DELETE')
                                <button class="cp-btn danger" type="submit">Xóa</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="cp-muted">Chưa có giấy tờ.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
