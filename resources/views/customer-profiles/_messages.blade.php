@if(session('success'))
    <div class="cp-alert ok">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="cp-alert err">
        <div style="font-weight:950;margin-bottom:4px">Có lỗi cần kiểm tra:</div>
        @foreach($errors->all() as $error)
            <div>• {{ $error }}</div>
        @endforeach
    </div>
@endif
