{{--
    Sơ đồ quy trình bằng HTML/CSS thuần — KHÔNG ảnh, KHÔNG thư viện, KHÔNG
    dịch vụ ngoài. Mũi tên nối là `::before`/`::after` trong
    `public/css/ego-technical-guide.css`; desktop xếp hàng ngang, dưới 768px
    tự chuyển thành cột dọc và mũi tên xoay 90°.

    Tham số:
      $steps   mảng [ ['icon' => 'bi-...', 'label' => ..., 'description' => ..., 'color' => 'ok|warn|accent'], ... ]
      $branch  (tuỳ chọn) ['label' => ..., 'steps' => [...] ] — nhánh rẽ
--}}
<div class="tg-flow">
    @foreach(($steps ?? []) as $tgStep)
        <div class="tg-flow__step {{ !empty($tgStep['color']) ? 'is-'.$tgStep['color'] : '' }}">
            <span class="tg-flow__icon"><i class="bi {{ $tgStep['icon'] ?? 'bi-dot' }}"></i></span>
            <span class="tg-flow__label">{{ $tgStep['label'] ?? '' }}</span>
            @if(!empty($tgStep['description']))
                <span class="tg-flow__desc">{{ $tgStep['description'] }}</span>
            @endif
        </div>
    @endforeach
</div>

@if(!empty($branch) && !empty($branch['steps']))
    <div class="tg-flow__branch">
        <span class="tg-flow__branch-label">
            <i class="bi bi-signpost-split"></i> {{ $branch['label'] ?? 'Rẽ nhánh' }}
        </span>
        <div class="tg-flow">
            @foreach($branch['steps'] as $tgBranchStep)
                <div class="tg-flow__step {{ !empty($tgBranchStep['color']) ? 'is-'.$tgBranchStep['color'] : 'is-warn' }}">
                    <span class="tg-flow__icon"><i class="bi {{ $tgBranchStep['icon'] ?? 'bi-dot' }}"></i></span>
                    <span class="tg-flow__label">{{ $tgBranchStep['label'] ?? '' }}</span>
                    @if(!empty($tgBranchStep['description']))
                        <span class="tg-flow__desc">{{ $tgBranchStep['description'] }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
