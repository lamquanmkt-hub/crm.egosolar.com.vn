<ol class="wx2-timeline" aria-label="Tiến trình xử lý">
    @foreach($timeline as $step)
        <li class="{{ $step['state'] }}">
            <span class="no">@if($step['state'] === 'done')<i class="bi bi-check-lg"></i>@else{{ $step['no'] }}@endif</span>
            <b>{{ $step['label'] }}</b>
            @if(!empty($step['detail']))<em>{{ $step['detail'] }}</em>@endif
            @if($step['state'] === 'current' && isset($stepActions[$step['key']]))
                <div class="wx2-stepact"><button type="button" class="wx-btn primary tiny" data-wx-open="{{ $stepActions[$step['key']]['modal'] }}">{{ $stepActions[$step['key']]['label'] }}</button></div>
            @endif
        </li>
    @endforeach
</ol>