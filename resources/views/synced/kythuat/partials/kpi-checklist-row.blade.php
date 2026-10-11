<li class="kpx-cl-row" data-kpx-row-bucket="{{ $row['bucket'] }}" data-kpx-row-state="{{ $row['state'] }}">
    <div class="kpx-cl-src">
        @if($row['url'])
            <a href="{{ $row['url'] }}" target="_blank" rel="noopener">{{ $row['title'] }} <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
        @else
            <strong>{{ $row['title'] }}</strong>
        @endif
        @if($row['meta'] !== '')<small>{{ $row['meta'] }}</small>@endif
    </div>
    <div class="kpx-cl-work">
        <span class="kpx-cl-label">Thực hiện</span>
        <span class="kpx-tag kpx-tag--{{ $row['work_tone'] }}" data-kpx-row-work>{{ $row['work'] }}</span>
    </div>
    <div class="kpx-cl-kpi">
        <span class="kpx-cl-label">KPI</span>
        <span class="kpx-tag kpx-tag--{{ $row['kpi_tone'] }}" data-kpx-row-kpi>{{ $row['kpi'] }}</span>
        @if($row['reason'] !== '')<small>{{ $row['reason'] }}</small>@endif
    </div>
</li>
