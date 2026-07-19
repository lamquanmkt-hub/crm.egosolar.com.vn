@extends('layouts.app')

@section('title', 'Chọn người chat')

@section('content')
<div style="padding:24px">
    <h2 style="font-weight:900;margin-bottom:14px">Chọn người để chat</h2>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px">
        @foreach($users as $u)
            <form method="POST" action="{{ route('chat.direct') }}" style="background:#fff;border:1px solid #e5edf7;border-radius:16px;padding:14px">
                @csrf
                <input type="hidden" name="user_id" value="{{ $u->id }}">
                <div style="font-weight:900">{{ $u->name }}</div>
                <div style="color:#64748b;font-size:13px;margin-bottom:10px">{{ $u->email }}</div>
                <button type="submit" style="height:38px;border:0;border-radius:12px;background:#2563eb;color:#fff;font-weight:900;padding:0 14px">Chat</button>
            </form>
        @endforeach
    </div>
</div>
@endsection
