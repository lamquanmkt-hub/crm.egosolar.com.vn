{{-- Kết quả kiểm tra kế hoạch tuần: lỗi CHẶN và cảnh báo (vẫn cho hoàn tất). --}}
@if(!empty($check['errors']) || !empty($check['warnings']))
    <div class="tp-checks">
        @if(!empty($check['errors']))
            <div class="tp-check tp-check--error">
                <p class="tp-check__title">
                    <i class="bi bi-x-octagon"></i>
                    Chưa thể hoàn tất kế hoạch tuần ({{ count($check['errors']) }} lỗi)
                </p>
                <ul>
                    @foreach($check['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(!empty($check['warnings']))
            <div class="tp-check tp-check--warn">
                <p class="tp-check__title">
                    <i class="bi bi-exclamation-triangle"></i>
                    Cảnh báo ({{ count($check['warnings']) }}) — vẫn có thể hoàn tất
                </p>
                <ul>
                    @foreach($check['warnings'] as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
