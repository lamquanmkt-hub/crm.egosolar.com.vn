@php
    /*
     * Kết quả nghiệm thu tính KPI kỹ thuật (bước Nghiệm thu và bàn giao).
     * Người được giao nhập & gửi → Trưởng phòng KT/Admin (không thuộc nhóm thực hiện) xác nhận từng nhóm hoặc trả bổ sung.
     * Dữ liệu: project_workflow_steps.data.kpi_acceptance — xem App\Services\Projects\AcceptanceKpiService.
     * Không tính điểm trong view: kết quả lấy từ ProjectKpiLinkService::acceptanceKpiResult() (cùng nguồn bộ chấm KPI).
     */
    $akService = app(\App\Services\Projects\AcceptanceKpiService::class);
    $akUser = auth()->user();
    $akState = $akService->state($wfRow);
    $akSummary = $akService->summary($wfRow);
    $akGrid = $akService->gridDecision((int) $site->id);
    $akReworks = $akService->revisionCount((int) $site->id);
    $akLocked = $akService->isLocked($wfRow);
    $akUnlocked = app(\App\Services\Projects\ProjectWorkflowV2Service::class)->isStepUnlocked($site, 'acceptance');
    $akIsAssignee = $akService->isActiveAssignee($akUser, $wfRow);
    // Khóa kỳ KPI (phiếu KPI kỹ thuật/bảng lương HR đã duyệt): không ai sửa nguồn, kể cả Admin.
    $akPeriodLock = $akService->kpiSourceLock((int) $site->id);
    $akCanDeclare = ($akIsAssignee || $wfIsAdmin) && ($wfIsAdmin || (! $akLocked && $akUnlocked)) && $akPeriodLock === null;
    [$akCanReview, $akReviewException, $akReviewDeny] = $akService->reviewAccess($akUser, $wfRow, $akState);
    $akAnyAwaiting = collect($akSummary)->contains(fn ($s) => $s['status'] === 'submitted');
    $akReviewOpen = $akCanReview && $akAnyAwaiting && $akPeriodLock === null;
    $akPeriod = $akService->kpiPeriod($wfRow);
    $akResult = app(\App\Services\TechnicalKpi\ProjectKpiLinkService::class)->acceptanceKpiResult($wfRow);
    $akLegacyData = app(\App\Services\Projects\ProjectWorkflowV2Service::class)->decodeData($wfRow->data ?? null);
    $akLegacy = ($akResult['format'] ?? '') === 'legacy'
        && !empty($akLegacyData['kpi_reviewed_by']) && !empty($akLegacyData['kpi_reviewed_at']);
    $akV = $akState['values'];
    $akOld = fn (string $key, $current) => session()->hasOldInput('kpi') ? data_get(session()->getOldInput('kpi'), $key) : $current;
    $akIds = collect([$akState['updated_by'], $akState['submitted']['by'] ?? null, $akState['returned']['by'] ?? null, $akGrid['by']])
        ->push($akLegacy ? $akLegacyData['kpi_reviewed_by'] : null)
        ->merge(collect($akState['confirmations'])->pluck('by'))->filter()->unique()->values();
    $akNames = $akIds->isEmpty() ? collect() : \App\Models\User::query()->whereIn('id', $akIds)->pluck('name', 'id');
    $akName = fn ($id) => $id ? ($akNames[(int) $id] ?? 'Người dùng #'.$id) : '—';
    $akTime = fn ($at) => $at ? \Illuminate\Support\Carbon::parse($at)->format('H:i d/m/Y') : '';
    $akPassFail = ['' => 'Chưa ghi nhận', 'pass' => 'Đạt', 'fail' => 'Không đạt'];
    $akComplaintLabels = ['' => 'Chưa ghi nhận', 'none' => 'Không có phản ánh', 'has' => 'Có phản ánh'];
    $akStatusClass = ['empty' => 'is-optional', 'draft' => 'is-optional', 'submitted' => 'is-pending', 'returned' => 'is-pending', 'confirmed' => 'is-done'];
    $akMonthLabel = \Illuminate\Support\Carbon::createFromFormat('Y-m', $akPeriod['month'])->format('m/Y');
    $akKpiUrl = \Illuminate\Support\Facades\Route::has('ky-thuat.kpis.index')
        ? route('ky-thuat.kpis.index', ['month' => $akPeriod['month'], 'site_id' => $site->id]) : null;
    $akFieldKey = ['quality' => 'quality_first_pass', 'hse' => 'hse_pass', 'evn' => 'evn_app_completed'];
    $akDocs = fn (string $group) => $wfDocuments->whereIn('document_code', \App\Services\Projects\AcceptanceKpiService::EVIDENCE[$group]);
    $akDocLabels = fn (string $group) => collect(\App\Services\Projects\AcceptanceKpiService::EVIDENCE[$group])
        ->map(fn ($code) => (string) config('project_workflow_v2.steps.acceptance.documents.'.$code.'.label', $code))->implode(' / ');
    $akMonitoring = trim((string) ($site->monitoring_link ?? '')) !== '' || trim((string) ($site->monitoring_account ?? '')) !== '';
    $akCounts = collect($akSummary)->countBy('status');
    $akTotal = count($akSummary);
    $akOverall = match (true) {
        $akLegacy => 'Kết quả đã xác nhận theo hồ sơ cũ',
        ! $akState['exists'] => 'Chưa nhập',
        ($akCounts['confirmed'] ?? 0) === $akTotal => 'Trưởng phòng đã xác nhận đủ '.$akTotal.' nhóm',
        ($akCounts['returned'] ?? 0) > 0 => 'Bị trả bổ sung — người thực hiện cần cập nhật và gửi lại',
        ($akCounts['submitted'] ?? 0) > 0 => 'Chờ Trưởng phòng kiểm tra'.(($akCounts['confirmed'] ?? 0) > 0 ? ' (đã xác nhận '.$akCounts['confirmed'].'/'.$akTotal.' nhóm)' : ''),
        default => 'Đang nhập (nháp) — chưa gửi kiểm tra'.(($akCounts['confirmed'] ?? 0) > 0 ? ' (đã xác nhận '.$akCounts['confirmed'].'/'.$akTotal.' nhóm)' : ''),
    };
@endphp

<section class="ewd-doc-section pak" id="acceptance-kpi">
    <header class="ewd-doc-section-head">
        <div>
            <h3>KẾT QUẢ NGHIỆM THU TÍNH KPI</h3>
            <small>
                Người được giao nhập và gửi → Trưởng phòng Kỹ thuật xác nhận hoặc trả bổ sung.
                Xác nhận KPI <strong>không</strong> thay cho bước Duyệt nghiệm thu (Admin). KPI chỉ tính khi nghiệm thu được duyệt, kỳ = tháng duyệt.
            </small>
        </div>
        @if($akKpiUrl)
            <a class="pword-btn light" href="{{ $akKpiUrl }}" target="_blank" rel="noopener"><i class="bi bi-graph-up"></i> Xem nguồn trên trang KPI ({{ $akMonthLabel }})</a>
        @endif
    </header>

    <div class="pak-meta">
        <span><small>Trạng thái</small><strong data-pak-overall>{{ $akOverall }}</strong></span>
        <span><small>{{ $akLegacy ? 'Người xác nhận hồ sơ cũ' : 'Bản khai báo' }}</small><strong>{{ $akLegacy ? $akName($akLegacyData['kpi_reviewed_by']) : ($akState['version'] > 0 ? 'Phiên bản '.$akState['version'].' · '.$akName($akState['updated_by']).' · '.$akTime($akState['updated_at']) : '—') }}</strong></span>
        <span><small>{{ $akLegacy ? 'Thời gian xác nhận' : 'Lần gửi gần nhất' }}</small><strong>{{ $akLegacy ? $akTime($akLegacyData['kpi_reviewed_at']) : (!empty($akState['submitted']) ? 'Bản '.$akState['submitted']['version'].' · '.$akName($akState['submitted']['by']).' · '.$akTime($akState['submitted']['at']) : 'Chưa gửi') }}</strong></span>
        <span><small>Kỳ KPI</small><strong>{{ $akPeriod['approved'] ? 'Tháng '.$akMonthLabel.' (đã duyệt nghiệm thu)' : 'Theo tháng Admin duyệt nghiệm thu' }}</strong></span>
    </div>

    @if($akLegacy)
        @include('projects-unified.partials.workflow-kpi-legacy')
        @if($akCanDeclare)
            <details class="pak-redeclare" @if(session()->hasOldInput('kpi')) open @endif>
                <summary>Nhập lại kết quả theo luồng mới</summary>
                <p class="pak-missing">Khi lưu bản mới, hệ thống chuyển sang dùng bản mới: nhóm chưa được xác nhận sẽ báo thiếu dữ liệu trên KPI. Kết quả cũ không tự điền vào bản mới.</p>
        @endif
    @endif

    @if(! $akLegacy || $akCanDeclare)
    @if($akState['status'] === 'returned' && !empty($akState['returned']))
        <div class="pword-revision-card pak-returned">
            <i class="bi bi-arrow-counterclockwise"></i>
            <div>
                <small>TRẢ BỔ SUNG KẾT QUẢ KPI · {{ $akTime($akState['returned']['at']) }}</small>
                <strong>Người trả: {{ $akName($akState['returned']['by']) }}{{ !empty($akState['returned']['groups']) ? ' · Nhóm: '.collect($akState['returned']['groups'])->map(fn ($g) => \App\Services\Projects\AcceptanceKpiService::GROUPS[$g] ?? $g)->implode(', ') : ' · Tất cả nhóm' }}</strong>
                <p>{{ $akState['returned']['reason'] }}</p>
            </div>
        </div>
    @endif

    @if($akPeriodLock !== null)
        <div class="pword-note pak-lock" data-pak-period-lock><i class="bi bi-lock-fill"></i> Kỳ KPI tháng {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $akPeriodLock['month'])->format('m/Y') }} đã chốt ({{ implode('; ', $akPeriodLock['reasons']) }}). Nguồn KPI của công trình này bị khóa với mọi người, kể cả Admin — muốn điều chỉnh phải mở lại kỳ theo quy trình duyệt lương.</div>
    @elseif($akLocked && ! $wfIsAdmin)
        <div class="pword-note pak-lock"><i class="bi bi-lock"></i> Nghiệm thu đã duyệt: kết quả KPI và minh chứng đã dùng để xác nhận được khóa. Chỉ Admin được sửa (có ghi lịch sử).</div>
    @elseif(! $akUnlocked && ! $wfIsAdmin)
        <div class="pword-note pak-lock"><i class="bi bi-hourglass"></i> Bước Nghiệm thu chưa mở (các bước trước chưa duyệt) — chưa nhập được kết quả KPI.</div>
    @endif

    @if($akCanDeclare)
        <form method="POST" action="{{ route('projects-unified.workflow.kpi.save', [$site, 'acceptance']) }}" class="pak-form" id="pak-declare-form">
            @csrf
    @endif

    <div class="pak-groups">
        @foreach(\App\Services\Projects\AcceptanceKpiService::GROUPS as $group => $groupLabel)
            @php
                $s = $akSummary[$group];
                $docs = $akDocs($group);
                $weight = $akPeriod['weights'][$group];
                $kpiValue = $akResult[$akFieldKey[$group]];
                $kpiMissing = $akResult[$group === 'evn' ? 'evn_missing' : $group.'_missing'];
            @endphp
            <article class="pak-group" data-pak-group="{{ $group }}">
                <header>
                    <div>
                        <strong>{{ $groupLabel }}</strong>
                        <small>Tiêu chí «{{ $akPeriod['names'][$group] }}» · trọng số {{ $weight !== null ? number_format($weight * 100, 0).'%' : 'chưa áp dụng' }} (bộ chấm kỳ {{ $akMonthLabel }})</small>
                    </div>
                    <span class="ewd-document-status {{ $akStatusClass[$s['status']] }}" data-pak-status="{{ $s['status'] }}">
                        {{ $s['status'] === 'confirmed' ? 'Đã xác nhận — '.($s['result'] === 'pass' ? 'đạt' : 'không đạt') : $s['status_label'] }}
                    </span>
                </header>

                <div class="pak-body">
                    @if($group === 'quality')
                        <div class="pak-auto"><i class="bi bi-clock-history"></i> Lịch sử “Trả lại” ở Thi công / Nghiệm thu (hệ thống tự lấy): <strong>{{ $akReworks }} lần</strong>{{ $akReworks > 0 ? ' — không đạt lần đầu' : '' }}</div>
                        <fieldset class="pak-choice">
                            <legend>Khách hàng có phản ánh về chất lượng/thẩm mỹ?</legend>
                            @php($cur = (string) $akOld('complaint', $akV['complaint']))
                            @if($akCanDeclare)
                                @foreach($akComplaintLabels as $value => $label)
                                    <label><input type="radio" name="kpi[complaint]" value="{{ $value }}" @checked($cur === (string) $value)>{{ $label }}</label>
                                @endforeach
                            @else
                                <strong>{{ $akComplaintLabels[$cur] ?? 'Chưa ghi nhận' }}</strong>
                            @endif
                        </fieldset>
                        @if($akCanDeclare)
                            <textarea name="kpi[complaint_note]" rows="2" placeholder="Nội dung phản ánh (bắt buộc nếu có phản ánh)">{{ $akOld('complaint_note', $akV['complaint_note']) }}</textarea>
                        @elseif(trim((string) $akV['complaint_note']) !== '')
                            <p class="pak-note">{{ $akV['complaint_note'] }}</p>
                        @endif
                    @elseif($group === 'hse')
                        <div class="pak-hse">
                            @foreach(\App\Services\TechnicalKpi\ProjectKpiLinkService::ACCEPTANCE_HSE_CHECKS as $key => $label)
                                @php($cur = (string) $akOld('hse.'.$key, $akV['hse'][$key] ?? null))
                                <div class="pak-hse-row">
                                    <span>{{ $label }}</span>
                                    @if($akCanDeclare)
                                        <span class="pak-radios">
                                            @foreach($akPassFail as $value => $text)
                                                <label><input type="radio" name="kpi[hse][{{ $key }}]" value="{{ $value }}" @checked($cur === (string) $value)>{{ $text }}</label>
                                            @endforeach
                                        </span>
                                    @else
                                        <strong class="pak-val pak-val-{{ $cur ?: 'none' }}">{{ $akPassFail[$cur] ?? 'Chưa ghi nhận' }}</strong>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if($akCanDeclare)
                            <textarea name="kpi[hse_note]" rows="2" placeholder="Ghi chú HSE (không bắt buộc)">{{ $akOld('hse_note', $akV['hse_note']) }}</textarea>
                        @elseif(trim((string) $akV['hse_note']) !== '')
                            <p class="pak-note">{{ $akV['hse_note'] }}</p>
                        @endif
                    @else
                        <div class="pak-auto {{ $akGrid['required'] === null ? 'is-warning' : '' }}">
                            <i class="bi bi-lightning-charge"></i> Cần đấu nối điện lực (theo Hồ sơ pháp lý): <strong>{{ $akGrid['label'] }}</strong>
                            @if($akGrid['at']) <small>· ghi nhận {{ $akName($akGrid['by']) }} · {{ $akTime($akGrid['at']) }}</small>@endif
                            @if($akGrid['required'] === null)<small>· Ghi ở tab <a href="{{ route('projects-unified.show', ['site' => $site->id, 'step' => 'contract']) }}#workflow">HĐ &amp; Pháp lý</a>; thiếu thì KS05 báo thiếu, không tự miễn.</small>@endif
                        </div>
                        @foreach(['grid_tested' => 'Đóng điện thử / hòa lưới EVN'.($akGrid['required'] === false ? ' (không bắt buộc vì không cần đấu nối)' : ''), 'app_handover' => 'Cài đặt và bàn giao App giám sát cho chủ nhà'] as $key => $label)
                            @php($cur = (string) $akOld($key, $akV[$key]))
                            <div class="pak-hse-row">
                                <span>{{ $label }}</span>
                                @if($akCanDeclare)
                                    <span class="pak-radios">
                                        @foreach($akPassFail as $value => $text)
                                            <label><input type="radio" name="kpi[{{ $key }}]" value="{{ $value }}" @checked($cur === (string) $value)>{{ $text }}</label>
                                        @endforeach
                                    </span>
                                @else
                                    <strong class="pak-val pak-val-{{ $cur ?: 'none' }}">{{ $akPassFail[$cur] ?? 'Chưa ghi nhận' }}</strong>
                                @endif
                            </div>
                        @endforeach
                        @if($akMonitoring)
                            <div class="pak-auto"><i class="bi bi-link-45deg"></i> Công trình có link/tài khoản App giám sát — chỉ là minh chứng hỗ trợ, không tự thành “đạt”.</div>
                        @endif
                        @if($akCanDeclare)
                            <textarea name="kpi[evn_note]" rows="2" placeholder="Ghi chú EVN/App (không bắt buộc)">{{ $akOld('evn_note', $akV['evn_note']) }}</textarea>
                        @elseif(trim((string) $akV['evn_note']) !== '')
                            <p class="pak-note">{{ $akV['evn_note'] }}</p>
                        @endif
                    @endif

                    <div class="pak-evidence">
                        <i class="bi bi-paperclip"></i> Minh chứng ({{ $akDocLabels($group) }}):
                        @forelse($docs->take(3) as $doc)
                            <a href="{{ asset('storage/'.$doc->path) }}" target="_blank" rel="noopener">{{ $doc->original_name ?: $doc->title }} <small>v{{ $doc->version }}</small></a>
                        @empty
                            <em>chưa có file — tải ở bảng hồ sơ phía trên</em>
                        @endforelse
                        @if($docs->count() > 3)<small>… và {{ $docs->count() - 3 }} file khác (xem bảng hồ sơ phía trên)</small>@endif
                    </div>
                </div>

                <footer class="pak-foot">
                    @if($s['missing'] !== [])
                        <div class="pak-missing"><strong>Còn thiếu:</strong> {{ implode('; ', $s['missing']) }}</div>
                    @endif
                    @if($s['confirmation'])
                        <div class="pak-confirmed"><i class="bi bi-patch-check"></i> Xác nhận: {{ $akName($s['confirmation']['by']) }} · {{ $akTime($s['confirmation']['at']) }} · bản {{ $s['confirmation']['version'] }}{{ !empty($s['confirmation']['admin_exception']) ? ' · ngoại lệ Admin' : '' }}</div>
                    @elseif($s['stale'])
                        <div class="pak-missing"><i class="bi bi-exclamation-triangle"></i> Xác nhận cũ không còn áp dụng: nội dung, minh chứng hoặc lịch sử trả lại đã thay đổi sau khi xác nhận. Cần gửi lại và kiểm tra lại.</div>
                    @endif
                    <div class="pak-kpi-note" data-pak-kpi="{{ $group }}">
                        <i class="bi bi-calculator"></i>
                        @if($akLegacy)
                            Bản khai báo mới: <strong>chưa được xác nhận</strong>. Kết quả đang dùng trên KPI được hiển thị phía trên.
                        @elseif($kpiValue === 1)
                            KPI: công trình được tính <strong>đạt</strong> ở tiêu chí này{{ $akPeriod['approved'] ? ' trong kỳ '.$akMonthLabel : ' khi nghiệm thu được duyệt' }}.
                        @elseif($kpiValue === 0)
                            KPI: công trình được tính <strong>không đạt</strong> ở tiêu chí này{{ $group === 'quality' ? ($akReworks > 0 ? ' (bị trả lại '.$akReworks.' lần)' : ' (có phản ánh của khách hàng)') : '' }}.
                        @else
                            KPI: <strong>chưa tính</strong> — {{ $kpiMissing ?: 'chưa có kết quả được xác nhận' }}.
                        @endif
                        <small>Điểm tháng của từng kỹ sư = theo bộ chấm trên trang KPI.</small>
                    </div>
                </footer>
            </article>
        @endforeach
    </div>

    @if($akCanDeclare)
            <div class="pak-actions">
                @if($wfIsAdmin && ! $akIsAssignee)<small class="pak-exception"><i class="bi bi-shield-exclamation"></i> Bạn không được giao bước này — Admin nhập thay sẽ được ghi là ngoại lệ.</small>@endif
                <button class="pword-btn light" type="submit" name="intent" value="draft"><i class="bi bi-save"></i> Lưu nháp</button>
                <button class="pword-btn primary" type="submit" name="intent" value="submit"><i class="bi bi-send-check"></i> Gửi kiểm tra</button>
            </div>
        </form>
    @endif

    @if($akReviewOpen)
        <div class="pak-review">
            <form method="POST" action="{{ route('projects-unified.workflow.kpi.confirm', [$site, 'acceptance']) }}" class="pak-review-confirm">
                @csrf
                <h4><i class="bi bi-clipboard-check"></i> Trưởng phòng kiểm tra — xác nhận từng nhóm đã đủ dữ liệu</h4>
                @if($akReviewException)<small class="pak-exception"><i class="bi bi-shield-exclamation"></i> Bạn có tham gia bước này — Admin xác nhận sẽ được ghi là ngoại lệ.</small>@endif
                @foreach(\App\Services\Projects\AcceptanceKpiService::GROUPS as $group => $groupLabel)
                    @php($s = $akSummary[$group])
                    <input type="hidden" name="fingerprints[{{ $group }}]" value="{{ $s['fingerprint'] }}">
                    <label class="pak-review-row {{ $s['status'] !== 'submitted' || $s['missing'] !== [] ? 'is-disabled' : '' }}">
                        <input type="checkbox" name="groups[]" value="{{ $group }}" @disabled($s['status'] !== 'submitted' || $s['missing'] !== [])>
                        <span>
                            <strong>{{ $groupLabel }}</strong>
                            @if($s['status'] === 'confirmed')
                                <small>Đã xác nhận.</small>
                            @elseif($s['status'] === 'returned')
                                <small>Đang trả bổ sung — chờ người thực hiện gửi lại.</small>
                            @elseif($s['missing'] !== [])
                                <small>Chưa đủ dữ liệu — không xác nhận được.</small>
                            @elseif($s['status'] !== 'submitted')
                                <small>Chưa gửi, hoặc đã sửa sau khi gửi — chờ người thực hiện gửi lại.</small>
                            @else
                                <small>Kết quả theo khai báo: <strong>{{ $s['declared_result'] === 'pass' ? 'đạt' : 'không đạt' }}</strong></small>
                            @endif
                        </span>
                    </label>
                @endforeach
                <input type="text" name="note" maxlength="2000" placeholder="Ghi chú kiểm tra (không bắt buộc)">
                <button class="pword-btn primary" type="submit"><i class="bi bi-patch-check"></i> Xác nhận nhóm đã chọn</button>
            </form>
            <form method="POST" action="{{ route('projects-unified.workflow.kpi.return', [$site, 'acceptance']) }}" class="pak-review-return">
                @csrf
                <h4><i class="bi bi-arrow-counterclockwise"></i> Trả bổ sung</h4>
                <div class="pak-radios">
                    @foreach(\App\Services\Projects\AcceptanceKpiService::GROUPS as $group => $groupLabel)
                        <label><input type="checkbox" name="groups[]" value="{{ $group }}">{{ $groupLabel }}</label>
                    @endforeach
                </div>
                <textarea name="reason" rows="2" minlength="5" required placeholder="Nội dung cần bổ sung/sửa (bắt buộc). Không chọn nhóm = trả tất cả."></textarea>
                <button class="pword-btn danger" type="submit"><i class="bi bi-arrow-counterclockwise"></i> Trả bổ sung</button>
            </form>
        </div>
    @elseif(!empty($wfPermissions['can_assign']) && ! $akCanReview && $akReviewDeny && $akAnyAwaiting)
        <div class="pword-note pak-lock"><i class="bi bi-person-x"></i> {{ $akReviewDeny }}</div>
    @elseif($akCanReview && ! $akAnyAwaiting && ! $akCanDeclare && collect($akSummary)->contains(fn ($s) => $s['status'] !== 'confirmed'))
        <div class="pword-note pak-lock"><i class="bi bi-hourglass-split"></i> Chờ người thực hiện gửi kiểm tra trước khi xác nhận.</div>
    @endif
    @endif
    @if($akLegacy && $akCanDeclare)
        </details>
    @endif
</section>

@once
<style>
    .pak .ewd-doc-section-head{gap:12px;flex-wrap:wrap}
    .pak .ewd-doc-section-head small strong{color:#17314e}
    .pak-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:8px;margin:10px 0}
    .pak-meta span{display:grid;gap:2px;background:#f6f8fb;border:1px solid #e3e9f1;border-radius:8px;padding:8px 10px;min-width:0}
    .pak-meta small{font-size:11px;color:#738499;text-transform:uppercase;letter-spacing:.02em}
    .pak-meta strong{font-size:13px;color:#17314e;overflow-wrap:anywhere}
    .pak-groups{display:grid;gap:12px}
    .pak-group{border:1px solid #dfe6ef;border-radius:10px;background:#fff;overflow:hidden}
    .pak-group>header{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;padding:10px 12px;background:#f8fafc;border-bottom:1px solid #e8edf3}
    .pak-group>header div{display:grid;gap:2px;min-width:0}
    .pak-group>header strong{font-size:14px;color:#17314e}
    .pak-group>header small{font-size:11.5px;color:#64748b}
    .pak-body{display:grid;gap:8px;padding:10px 12px}
    .pak-choice{border:0;padding:0;margin:0;display:flex;flex-wrap:wrap;gap:6px 16px;align-items:center;min-width:0}
    .pak-choice legend{font-size:12.5px;font-weight:700;color:#17314e;margin-bottom:4px;width:100%}
    .pak-choice>strong{font-size:13px;color:#17314e}
    .pak label{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;color:#17314e;cursor:pointer}
    .pak input[type=radio],.pak input[type=checkbox]{width:15px;height:15px;min-height:0;margin:0;box-shadow:none;flex:0 0 auto}
    .pak textarea,.pak input[type=text]{width:100%;font-size:13px;min-height:0}
    .pak-hse{display:grid;gap:4px}
    .pak-hse-row{display:grid;grid-template-columns:minmax(180px,1fr) auto;gap:8px;align-items:center;padding:6px 0;border-bottom:1px dashed #edf1f6}
    .pak-hse-row>span:first-child{font-size:12.5px;color:#334155}
    .pak-radios{display:flex;flex-wrap:wrap;gap:4px 14px}
    .pak-val{font-size:12.5px}.pak-val-pass{color:#15803d}.pak-val-fail{color:#b91c1c}.pak-val-none{color:#94a3b8}
    .pak-auto{font-size:12.5px;color:#334155;background:#f1f5f9;border-radius:6px;padding:6px 8px}
    .pak-auto.is-warning{background:#fff7ed;color:#9a3412}
    .pak-note{margin:0;font-size:12.5px;color:#334155;white-space:pre-line}
    .pak-evidence{font-size:12px;color:#475569;display:flex;flex-wrap:wrap;gap:4px 10px;align-items:center}
    .pak-evidence a{color:#1d4ed8}
    .pak-foot{display:grid;gap:6px;padding:8px 12px;border-top:1px solid #edf1f6;background:#fcfdfe}
    .pak-missing{font-size:12px;color:#9a3412}
    .pak-confirmed{font-size:12px;color:#166534}
    .pak-kpi-note{font-size:12px;color:#1e3a5f}
    .pak-kpi-note small{display:block;color:#64748b}
    .pak-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;align-items:center;gap:8px;margin-top:12px}
    .pak-exception{font-size:12px;color:#9a3412;margin-right:auto}
    .pak-review{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:12px;margin-top:14px}
    .pak-review form{display:grid;gap:8px;align-content:start;border:1px solid #c7d6ea;border-radius:10px;padding:12px;background:#f8fbff}
    .pak-review-return{border-color:#f3c9c9 !important;background:#fffafa !important}
    .pak-review h4{margin:0;font-size:13.5px;color:#17314e}
    .pak-review-row{align-items:flex-start !important}
    .pak-review-row span{display:grid;gap:2px}
    .pak-review-row small{font-weight:500;color:#475569}
    .pak-review-row.is-disabled{opacity:.6;cursor:not-allowed}
    .pak-lock{margin-top:8px}
    .pak-returned{margin:8px 0}
    .pak-legacy-note{margin:8px 0 12px;font-size:13px;color:#334155}
    .pak-redeclare{margin-top:14px;border:1px solid #dfe6ef;border-radius:10px;padding:12px}
    .pak-redeclare>summary{font-size:13px;font-weight:700;color:#1d4ed8;cursor:pointer}
    .pak-redeclare>p{margin:10px 0}
    @media(max-width:760px){
        .pak-review{grid-template-columns:1fr}
        .pak-hse-row{grid-template-columns:1fr}
        .pak-group>header{flex-direction:column}
    }
</style>
@endonce
