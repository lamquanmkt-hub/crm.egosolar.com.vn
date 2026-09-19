@extends('layouts.app')
@section('title', 'Công trình đã chuyển về hệ thống chung')
@section('content')
<div class="container py-4"><div class="card"><div class="card-body">
<h1 class="h4">Biểu mẫu cũ đã ngừng sử dụng</h1>
<p>Thao tác vừa gửi chưa được lưu. Công trình Sales đã gộp về Công trình chung; hãy mở hồ sơ chuẩn và thực hiện lại ở đó.</p>
<a href="{{ $url }}" class="btn btn-primary">Mở Công trình</a>
</div></div></div>
@endsection
