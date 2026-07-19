@extends('layouts.app')

@section('content')
<style>
.swr-wrap{max-width:1180px;margin:0 auto;padding:6px}
.swr-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 8px 24px rgba(15,23,42,.06);padding:18px;margin-bottom:14px}
.swr-muted{color:#64748b}
.swr-btn{border:0;border-radius:12px;padding:10px 14px;background:#0f766e;color:#fff;font-weight:900;text-decoration:none;display:inline-flex}
.swr-btn.dark{background:#0f172a}.swr-btn.light{background:#e2e8f0;color:#0f172a}
.swr-badge{display:inline-flex;border-radius:999px;padding:5px 10px;font-weight:900;background:#e0f2fe;color:#0369a1}
.swr-badge.hot{background:#fee2e2;color:#991b1b}.swr-badge.high{background:#ffedd5;color:#9a3412}
.swr-grid{display:grid;grid-template-columns:1.25fr .75fr;gap:14px}
.swr-kv{display:grid;grid-template-columns:180px 1fr;gap:8px;border-top:1px solid #e5e7eb;padding:10px 0}
.swr-input{width:100%;border:1px solid #d1d5db;border-radius:12px;padding:10px 12px}
@media(max-width:900px){.swr-grid{grid-template-columns:1fr}.swr-kv{grid-template-columns:1fr}}
</style>

<div class="swr-wrap">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:10px;flex-wrap:wrap">
        <div>
            <h1 style="font-size:28px;font-weight:900;margin:0">{{ $report->customer_name }}</h1>
            <div class="swr-muted">
                {{ $report->customer_phone ?: 'Chưa có SĐT' }}
                {{ $report->customer_company ? ' • '.$report->customer_company : '' }}
                {{ $report->source_name ? ' • '.$report->source_name : '' }}
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="swr-btn light" href="{{ route('sales.work-reports.edit', $report->id) }}">Sửa</a>
            <a class="swr-btn dark" href="{{ route('sales.work-reports.index') }}">Quay lại</a>
        </div>
    </div>

    @if(session('success'))
        <div class="swr-card" style="background:#dcfce7;color:#166534;font-weight:800">{{ session('success') }}</div>
    @endif

    <div class="swr-grid">
        <div>
            <div class="swr-card">
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px">
                    <span class="swr-badge">{{ $statuses[$report->status] ?? $report->status }}</span>
                    <span class="swr-badge {{ $report->priority }}">{{ $priorities[$report->priority] ?? $report->priority }}</span>
                    <span class="swr-muted">Sales: <strong>{{ $report->sales_name ?: '—' }}</strong></span>
                </div>

                <div class="swr-kv"><strong>Nhận data</strong><span>{{ $report->data_received_at ? \Carbon\Carbon::parse($report->data_received_at)->format('d/m/Y H:i') : '—' }}</span></div>
                <div class="swr-kv"><strong>Gọi/nhắn lần đầu</strong><span>{{ $report->first_call_at ? \Carbon\Carbon::parse($report->first_call_at)->format('d/m/Y H:i') : '—' }}</span></div>
                <div class="swr-kv"><strong>Tương tác gần nhất</strong><span>{{ $report->last_contact_at ? \Carbon\Carbon::parse($report->last_contact_at)->format('d/m/Y H:i') : '—' }}</span></div>
                <div class="swr-kv"><strong>Kênh</strong><span>{{ $channels[$report->contact_channel] ?? $report->contact_channel }}</span></div>
                <div class="swr-kv"><strong>Kết quả</strong><span>{{ $outcomes[$report->outcome] ?? '—' }}</span></div>
                <div class="swr-kv"><strong>Follow-up</strong><span>{{ $report->next_followup_at ? \Carbon\Carbon::parse($report->next_followup_at)->format('d/m/Y H:i') : '—' }}</span></div>
                <div class="swr-kv"><strong>Việc tiếp theo</strong><span>{{ $report->next_action ?: '—' }}</span></div>

                <h2 style="font-size:20px;font-weight:900;margin-top:18px">Nhu cầu và tư vấn</h2>
                <p><strong>Nhu cầu:</strong><br>{!! nl2br(e($report->customer_need)) !!}</p>
                <p><strong>Công suất dự kiến:</strong> {{ $report->system_size_kw ? $report->system_size_kw . ' kW' : '—' }}</p>
                <p><strong>Ngân sách:</strong> {{ $report->budget_range ?: '—' }}</p>
                <p><strong>Timeline:</strong> {{ $report->project_timeline ?: '—' }}</p>
                <p><strong>Đã tư vấn:</strong><br>{!! nl2br(e($report->consultation_summary)) !!}</p>
                <p><strong>Sản phẩm / giải pháp đã báo:</strong><br>{!! nl2br(e($report->quoted_products)) !!}</p>
                <p><strong>Phản hồi khách:</strong><br>{!! nl2br(e($report->customer_feedback)) !!}</p>
                <p><strong>Doanh thu kỳ vọng:</strong> {{ $report->revenue_expectation ? number_format($report->revenue_expectation, 0, ',', '.') . 'đ' : '—' }}</p>
                <p><strong>Lý do thất bại / data lỗi:</strong><br>{!! nl2br(e($report->lost_reason)) !!}</p>

                @php $proofs = is_array($report->proof_links) ? $report->proof_links : json_decode($report->proof_links ?? '[]', true); @endphp
                @if(!empty($proofs))
                    <p><strong>Link minh chứng:</strong></p>
                    <ul>
                        @foreach($proofs as $link)
                            <li><a href="{{ $link }}" target="_blank" rel="noopener">{{ $link }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if($canManage)
                <div class="swr-card">
                    <h2 style="font-size:20px;font-weight:900;margin-top:0">Ghi chú quản lý</h2>
                    <form method="POST" action="{{ route('sales.work-reports.approve', $report->id) }}">
                        @csrf
                        <textarea class="swr-input" name="manager_note" rows="4">{{ old('manager_note', $report->manager_note) }}</textarea>
                        <div style="text-align:right;margin-top:10px">
                            <button class="swr-btn" type="submit">Lưu ghi chú / duyệt</button>
                        </div>
                    </form>
                    @if($report->approved_at)
                        <div class="swr-muted" style="margin-top:8px">Đã duyệt lúc {{ \Carbon\Carbon::parse($report->approved_at)->format('d/m/Y H:i') }}</div>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <div class="swr-card">
                <h2 style="font-size:20px;font-weight:900;margin-top:0">Thông tin khách</h2>
                <div class="swr-kv"><strong>Email</strong><span>{{ $report->customer_email ?: '—' }}</span></div>
                <div class="swr-kv"><strong>Công ty</strong><span>{{ $report->customer_company ?: '—' }}</span></div>
                <div class="swr-kv"><strong>Loại khách</strong><span>{{ $customerTypes[$report->customer_type] ?? $report->customer_type }}</span></div>
                <div class="swr-kv"><strong>Khu vực</strong><span>{{ $report->region_text ?: '—' }}</span></div>
                <div class="swr-kv"><strong>Địa chỉ</strong><span>{{ $report->customer_address ?: '—' }}</span></div>
                <div class="swr-kv"><strong>Facebook</strong><span>{{ $report->facebook_name ?: '—' }}</span></div>
                <div class="swr-kv"><strong>Zalo</strong><span>{{ $report->zalo_id ?: '—' }}</span></div>
            </div>

            <div class="swr-card">
                <h2 style="font-size:20px;font-weight:900;margin-top:0">Lịch sử cùng khách này</h2>
                @forelse($history as $item)
                    <div style="border-left:3px solid #0f766e;padding-left:12px;margin-bottom:14px">
                        <strong>{{ $statuses[$item->status] ?? $item->status }}</strong>
                        <div class="swr-muted">{{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->format('d/m/Y H:i') : '' }} • {{ $item->sales_name }}</div>
                        <div>{{ \Illuminate\Support\Str::limit($item->consultation_summary ?: $item->customer_need, 140) }}</div>
                        <a href="{{ route('sales.work-reports.show', $item->id) }}">Xem</a>
                    </div>
                @empty
                    <div class="swr-muted">Chưa có lịch sử khác.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>


{{-- EGO_SALES_MANAGER_DROPDOWN_START --}}
@php
    $egoSalesManagerOptions = collect();

    try {
        $egoSalesManagerOptions = \App\Models\User::query()
            ->get()
            ->filter(function ($u) {
                $roles = [];

                foreach (['role', 'type', 'position', 'department'] as $field) {
                    if (!empty($u->{$field})) {
                        $roles[] = mb_strtolower((string) $u->{$field});
                    }
                }

                if (method_exists($u, 'getRoleNames')) {
                    foreach ($u->getRoleNames() as $roleName) {
                        $roles[] = mb_strtolower((string) $roleName);
                    }
                }

                $roleText = implode('|', array_unique(array_filter($roles)));

                return str_contains($roleText, 'sales_manager')
                    || str_contains($roleText, 'sales manager')
                    || str_contains($roleText, 'trưởng phòng sales')
                    || str_contains($roleText, 'truong_phong_sales')
                    || str_contains($roleText, 'manager_sales');
            })
            ->map(function ($u) {
                return [
                    'id' => (string) $u->id,
                    'name' => (string) ($u->name ?? $u->email ?? ('User #' . $u->id)),
                ];
            })
            ->values();
    } catch (\Throwable $e) {
        $egoSalesManagerOptions = collect();
    }
@endphp

<script>
(function () {
    var managers = @json($egoSalesManagerOptions);

    function cleanText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function selectLooksLikeSalesOwner(select) {
        var name = cleanText(select.getAttribute('name'));
        var id = cleanText(select.getAttribute('id'));
        var text = cleanText(select.closest('form, .modal, .card, section, div') ? select.closest('form, .modal, .card, section, div').textContent : '');

        return name.indexOf('sales') !== -1
            || name.indexOf('assigned') !== -1
            || name.indexOf('owner') !== -1
            || id.indexOf('sales') !== -1
            || text.indexOf('sales phụ trách') !== -1
            || text.indexOf('sales phu trach') !== -1;
    }

    function hasOption(select, value) {
        return Array.prototype.slice.call(select.options).some(function (opt) {
            return String(opt.value) === String(value);
        });
    }

    function addManagersToSelect(select) {
        if (!select || !selectLooksLikeSalesOwner(select)) return;

        managers.forEach(function (manager) {
            if (!manager || !manager.id || hasOption(select, manager.id)) return;

            var option = document.createElement('option');
            option.value = manager.id;
            option.textContent = manager.name + ' - Sales Manager';
            option.setAttribute('data-ego-sales-manager', '1');

            select.appendChild(option);
        });
    }

    function run() {
        if (!Array.isArray(managers) || managers.length === 0) return;

        document.querySelectorAll('select').forEach(addManagersToSelect);
    }

    document.addEventListener('DOMContentLoaded', run);

    setTimeout(run, 300);
    setTimeout(run, 900);
    setTimeout(run, 1800);

    document.addEventListener('click', function () {
        setTimeout(run, 150);
        setTimeout(run, 500);
    }, true);
})();
</script>
{{-- EGO_SALES_MANAGER_DROPDOWN_END --}}
@endsection
