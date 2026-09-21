{{--
    Một bước thao tác.

    Tham số:
      $num      số thứ tự bước
      $title    tên bước
      $desc     mô tả ngắn (HTML cho phép — nội dung tĩnh do lập trình viên viết)
      $shot     đường dẫn ảnh trong public/ (tuỳ chọn)
      $alt      alt tiếng Việt của ảnh
      $caption  chú thích ảnh (tuỳ chọn)
      $result   kết quả sau bước (tuỳ chọn)
      $bullets  mảng gạch đầu dòng chi tiết (tuỳ chọn)
--}}
<div class="tg-step">
    <div class="tg-step__head">
        <span class="tg-step__num">{{ $num ?? '•' }}</span>
        <div>
            <h3 class="tg-step__title">{{ $title ?? '' }}</h3>
            @if(!empty($desc))
                <p class="tg-step__desc">{!! $desc !!}</p>
            @endif
        </div>
    </div>

    <div class="tg-step__body">
        @if(!empty($bullets))
            <ul class="mb-0 mt-1">
                @foreach($bullets as $tgBullet)
                    <li>{!! $tgBullet !!}</li>
                @endforeach
            </ul>
        @endif

        @if(!empty($shot))
            @include('technical.guides.partials.lightbox-image', [
                'src' => $shot,
                'alt' => $alt ?? ($title ?? 'Ảnh minh hoạ'),
                'caption' => $caption ?? '',
            ])
        @endif
    </div>

    @if(!empty($result))
        <div class="tg-step__result">
            <strong>Kết quả:</strong> {!! $result !!}
        </div>
    @endif
</div>
