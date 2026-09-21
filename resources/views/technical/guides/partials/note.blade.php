{{--
    Ghi chú trong bài hướng dẫn.

    Tham số:
      $tone   'info' (mặc định) | 'warning' | 'danger'
      $title  tiêu đề ngắn (tuỳ chọn)
      $text   nội dung (bỏ qua nếu dùng slot $slot của @include kiểu mảng)
--}}
@php
    $tgTone = in_array(($tone ?? 'info'), ['info', 'warning', 'danger'], true) ? ($tone ?? 'info') : 'info';
    $tgIcon = match ($tgTone) {
        'warning' => 'bi-exclamation-triangle',
        'danger' => 'bi-x-octagon',
        default => 'bi-info-circle',
    };
    $tgClass = $tgTone === 'info' ? '' : ' tg-note--'.$tgTone;
@endphp

<div class="tg-note{{ $tgClass }}">
    <i class="bi {{ $tgIcon }}"></i>
    <div>
        @if(!empty($title))
            <span class="tg-note__title">{{ $title }}</span>
        @endif
        <p>{!! $text ?? '' !!}</p>
    </div>
</div>
