@php
    // Display the same legacy result as the scorer. Never create a declaration or a confirmation.
    $akLegacyCheck = fn ($value) => in_array($value, [true, 1, '1'], true) ? 'Đạt (hồ sơ cũ)' : 'Chưa có xác nhận đạt';
@endphp
<div class="pak-legacy" data-pak-legacy>
    <p class="pak-legacy-note"><strong>Kết quả từ hồ sơ đã xác nhận</strong> (đánh giá dạng cũ) — đây là nguồn hiện đang dùng trên trang KPI. Mỗi nhóm bên dưới ghi rõ đủ dữ liệu hay còn thiếu; không phải bản nháp mới.</p>
    <div class="pak-groups">
        @foreach(\App\Services\Projects\AcceptanceKpiService::GROUPS as $group => $groupLabel)
            @php
                $legacyValue = $akResult[$akFieldKey[$group]];
                $legacyMissing = $akResult[$group === 'evn' ? 'evn_missing' : $group.'_missing'];
                $legacyStamp = $akResult[$group.'_confirmed'] ?? null;
                $docs = $akDocs($group);
            @endphp
            <article class="pak-group" data-pak-legacy-group="{{ $group }}">
                <header>
                    <div><strong>{{ $groupLabel }}</strong><small>Tiêu chí «{{ $akPeriod['names'][$group] }}» · trọng số {{ $akPeriod['weights'][$group] !== null ? number_format($akPeriod['weights'][$group] * 100, 0).'%' : 'chưa áp dụng' }} · kỳ {{ $akMonthLabel }}</small></div>
                    <span class="ewd-document-status {{ $legacyValue === 1 ? 'is-done' : 'is-pending' }}">{{ $legacyValue === null ? 'Hồ sơ cũ còn thiếu dữ liệu' : ($legacyValue === 1 ? 'Đã xác nhận — đạt' : 'Đã xác nhận — không đạt') }}</span>
                </header>
                <div class="pak-body">
                    @if($group === 'quality')
                        <div class="pak-hse-row"><span>Khách hàng có phản ánh về chất lượng/thẩm mỹ?</span><strong>{{ !$akResult['complaint_recorded'] ? 'Chưa có xác nhận' : ($akResult['complaint'] ? 'Có phản ánh' : 'Không có phản ánh') }}</strong></div>
                        @if($akResult['complaint_note'])<p class="pak-note">{{ $akResult['complaint_note'] }}</p>@endif
                        <div class="pak-auto">Lịch sử trả lại Thi công / Nghiệm thu: <strong>{{ $akReworks }} lần</strong></div>
                    @elseif($group === 'hse')
                        @foreach(\App\Services\TechnicalKpi\ProjectKpiLinkService::ACCEPTANCE_HSE_CHECKS as $key => $label)
                            <div class="pak-hse-row"><span>{{ $label }}</span><strong>{{ $akLegacyCheck($akLegacyData[$key] ?? null) }}</strong></div>
                        @endforeach
                    @else
                        <div class="pak-auto">Cần đấu nối điện lực: <strong>{{ $akGrid['label'] }}</strong></div>
                        <div class="pak-hse-row"><span>Đóng điện thử / hòa lưới EVN</span><strong>{{ $akGrid['required'] === false ? 'Không bắt buộc — không cần đấu nối' : $akLegacyCheck($akLegacyData['kpi_evn_grid_tested'] ?? null) }}</strong></div>
                        <div class="pak-hse-row"><span>Cài đặt và bàn giao App giám sát</span><strong>{{ $akLegacyCheck($akLegacyData['kpi_app_installed'] ?? null) }}</strong></div>
                    @endif
                    <div class="pak-evidence">
                        <span>Minh chứng hiện có:</span>
                        @forelse($docs->take(3) as $doc)
                            <a href="{{ asset('storage/'.$doc->path) }}" target="_blank" rel="noopener">{{ $doc->original_name ?: $doc->title }} <small>v{{ $doc->version }}</small></a>
                        @empty
                            <em>Chưa có file tại bảng hồ sơ.</em>
                        @endforelse
                        @if($docs->count() > 3)<small>… và {{ $docs->count() - 3 }} file khác ở bảng hồ sơ phía trên.</small>@endif
                    </div>
                </div>
                <footer class="pak-foot">
                    @if($legacyStamp)<div class="pak-confirmed">Hồ sơ cũ · {{ $legacyStamp }}</div>@endif
                    <div class="pak-kpi-note" data-pak-kpi="{{ $group }}">
                        @if($legacyValue === null)
                            KPI: <strong>chưa tính</strong> — {{ $legacyMissing }}.
                        @else
                            KPI: công trình được tính <strong>{{ $legacyValue === 1 ? 'đạt' : 'không đạt' }}</strong>{{ $akPeriod['approved'] ? ' trong kỳ '.$akMonthLabel : ' khi nghiệm thu được duyệt' }}.
                        @endif
                        <small>Điểm tháng của từng kỹ sư = theo bộ chấm trên trang KPI.</small>
                    </div>
                </footer>
            </article>
        @endforeach
    </div>
</div>
