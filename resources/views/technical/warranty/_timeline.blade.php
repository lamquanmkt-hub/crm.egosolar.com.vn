<ol class="wx2-timeline" aria-label="Tiến trình xử lý">
    @foreach($timeline as $step)
        <li class="{{ $step['state'] }}">
            <span class="no">@if($step['state'] === 'done')<i class="bi bi-check-lg"></i>@else{{ $step['no'] }}@endif</span>
            <b>{{ $step['label'] }}</b>
            @if(!empty($step['detail']))<em>{{ $step['detail'] }}</em>@endif
        </li>
    @endforeach
</ol>
