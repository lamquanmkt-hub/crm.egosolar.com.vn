{{--
    Bảng "Các trạng thái có thể gặp".

    Badge dùng ĐÚNG tone Bootstrap mà module Kỹ thuật đang dùng
    (`TechnicalDailyReport::STATUS_TONES`, `TechnicalPlanItem::STATUS_TONES`,
    `TechnicalWeekPlan::STATUS_TONES`) — không tự bịa màu mới.

    Tham số:
      $items  mảng [ ['label' => ..., 'tone' => 'secondary|warning|success|danger|info', 'text' => ...], ... ]
--}}
<div class="tg-status-list">
    @foreach(($items ?? []) as $tgStatus)
        <div class="tg-status-row">
            <span class="tg-status-row__badge">
                <span class="badge text-bg-{{ $tgStatus['tone'] ?? 'secondary' }}">{{ $tgStatus['label'] ?? '' }}</span>
            </span>
            <span class="tg-status-row__text">{!! $tgStatus['text'] ?? '' !!}</span>
        </div>
    @endforeach
</div>
