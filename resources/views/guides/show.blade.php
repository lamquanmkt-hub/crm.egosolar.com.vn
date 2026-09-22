@extends('layouts.app')

@section('title', $guide['title'])

@section('content')
<div class="wx-page"><div class="wx-shell" style="max-width:1100px;margin:0 auto">
    <header class="wx-hero" style="padding:14px 18px;display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
        <div><div class="wx-kicker">HƯỚNG DẪN SỬ DỤNG</div><h1 style="font-size:22px">{{ $guide['title'] }}</h1>
            <p>{{ $guide['intro'] }}</p></div>
        <a href="{{ $back }}" class="wx-btn secondary" style="white-space:nowrap"><i class="bi bi-arrow-left"></i> Quay lại trang đang làm việc</a>
    </header>

    <div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap">
        <nav class="wx-panel" style="padding:12px 14px;min-width:220px;position:sticky;top:12px">
            <strong style="display:block;margin-bottom:8px">Mục lục</strong>
            <ol style="padding-left:18px;margin:0">
                @foreach($guide['steps'] as $i => $s)
                    <li><a href="#step-{{ $i + 1 }}">{{ $s['title'] }}</a></li>
                @endforeach
            </ol>
        </nav>

        <div style="flex:1;min-width:280px;display:flex;flex-direction:column;gap:16px">
            @foreach($guide['steps'] as $i => $s)
                <section id="step-{{ $i + 1 }}" class="wx-panel" style="padding:16px 18px">
                    <h3 style="margin:0 0 6px">{{ $s['title'] }}</h3>
                    <p style="margin:0 0 10px;color:#334155">{{ $s['desc'] }}</p>
                    @if(file_exists(public_path($s['image'])))
                        <img src="{{ asset($s['image']) }}" alt="{{ $s['title'] }}" style="max-width:100%;border:1px solid #e2e8f0;border-radius:8px">
                    @else
                        <div class="wx-empty" style="padding:24px"><i class="bi bi-image"></i> Ảnh minh họa chưa có.</div>
                    @endif
                    @if($s['note'])
                        <div class="wx2-info" style="margin-top:10px"><i class="bi bi-info-circle"></i> {{ $s['note'] }}</div>
                    @endif
                </section>
            @endforeach
        </div>
    </div>

    <div style="margin:16px 0"><a href="{{ $back }}" class="wx-btn secondary"><i class="bi bi-arrow-left"></i> Quay lại trang đang làm việc</a></div>
</div></div>
@endsection
