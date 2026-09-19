@if(session('success'))
    <div class="gift-alert gift-alert--success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>
@endif
@if(session('error'))
    <div class="gift-alert gift-alert--danger"><i class="bi bi-exclamation-triangle-fill"></i><span>{{ session('error') }}</span></div>
@endif
@if($errors->any())
    <div class="gift-alert gift-alert--danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>
            <strong>Vui lòng kiểm tra dữ liệu:</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    </div>
@endif
