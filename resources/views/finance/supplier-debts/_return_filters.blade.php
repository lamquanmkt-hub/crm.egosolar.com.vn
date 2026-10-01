{{-- Mang theo bộ lọc hiện tại (kiểu lọc, từ khóa, trạng thái, tháng) để sau khi lưu/xóa vẫn quay về đúng danh sách đã lọc. --}}
@foreach (['period', 'keyword', 'status', 'month'] as $sdFilterKey)
    @if (is_string(request()->query($sdFilterKey)) && request()->query($sdFilterKey) !== '')
        <input type="hidden" name="ret[{{ $sdFilterKey }}]" value="{{ request()->query($sdFilterKey) }}">
    @endif
@endforeach
