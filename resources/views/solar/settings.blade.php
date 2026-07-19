@extends('layouts.app')

@section('content')
@php
    $settingsCollection = collect($settings);

    $groupedSections = [
        'Công thức công suất' => [
            'pvout_default',
            'performance_ratio',
            'reserve_ratio',
        ],
        'Chi phí solar & pin' => [
            'cost_small',
            'cost_medium',
            'cost_large',
            'battery_cost_per_kwh',
        ],
        'Pin lưu trữ & phần cứng' => [
            'panel_power_wp',
            'panel_area_m2',
            'battery_backup_hours_day',
            'battery_backup_hours_balanced',
            'battery_backup_hours_night',
        ],
        'Hành vi sử dụng điện' => [
            'usage_day_ratio_high',
            'usage_balanced_ratio',
            'usage_night_ratio_high',
            'system_on_grid_ratio',
            'system_hybrid_ratio',
            'system_battery_ratio',
        ],
        'Giá điện' => [
            'electricity_mode_default',
            'residential_bac_1',
            'residential_bac_2',
            'residential_bac_3',
            'residential_bac_4',
            'residential_bac_5',
            'residential_bac_6',
            'business_price_normal_low_voltage',
            'production_price_normal_low_voltage',
            'public_price_low_voltage',
            'export_price',
        ],
        'Phân tích tài chính' => [
            'analysis_years',
            'electricity_price_growth',
            'year1_loss',
            'yearly_degradation',
        ],
    ];

    $sectionDescriptions = [
        'Công thức công suất' => 'Các key dùng để tính kWp đề xuất từ PVOUT, PR và tỷ lệ dự phòng.',
        'Chi phí solar & pin' => 'Thiết lập chi phí đầu tư cho phần solar và pin lưu trữ.',
        'Pin lưu trữ & phần cứng' => 'Thiết lập cấu hình tấm pin, diện tích và giờ lưu trữ đề xuất.',
        'Hành vi sử dụng điện' => 'Các key này quyết định tỷ lệ tự dùng theo ngày/đêm và loại hệ.',
        'Giá điện' => 'Biểu giá điện dùng để quy đổi hóa đơn sang kWh và tính tiết kiệm.',
        'Phân tích tài chính' => 'Dùng để tính hao hụt, tăng giá điện và hoàn vốn.',
    ];

    $defaults = [
        'pvout_default' => 1600,
        'performance_ratio' => 0.80,
        'reserve_ratio' => 1.10,
        'cost_small' => 14000000,
        'cost_medium' => 12000000,
        'cost_large' => 10000000,
        'battery_cost_per_kwh' => 9000000,
        'panel_power_wp' => 585,
        'panel_area_m2' => 2.6,
        'battery_backup_hours_day' => 2,
        'battery_backup_hours_balanced' => 4,
        'battery_backup_hours_night' => 6,
        'usage_day_ratio_high' => 0.85,
        'usage_balanced_ratio' => 0.70,
        'usage_night_ratio_high' => 0.40,
        'system_on_grid_ratio' => 1.00,
        'system_hybrid_ratio' => 1.20,
        'system_battery_ratio' => 1.40,
        'electricity_mode_default' => 'residential',
        'residential_bac_1' => 1893,
        'residential_bac_2' => 1956,
        'residential_bac_3' => 2271,
        'residential_bac_4' => 2860,
        'residential_bac_5' => 3197,
        'residential_bac_6' => 3302,
        'business_price_normal_low_voltage' => 3007,
        'production_price_normal_low_voltage' => 1896,
        'public_price_low_voltage' => 2124,
        'export_price' => 1500,
        'analysis_years' => 25,
        'electricity_price_growth' => 0.03,
        'year1_loss' => 0.02,
        'yearly_degradation' => 0.005,
    ];
@endphp

<div class="solar-page">
    <div class="solar-shell">
        <div class="solar-header">
            <div>
                <div class="solar-badge">Solar Pro Settings</div>
                <h1>Cài đặt Solar + Pin lưu trữ</h1>
                <p>
                    Tất cả tham số trong trang này được calculator dùng trực tiếp.
                    Đổi ở đây là tool tính theo ngay, bao gồm cả hành vi dùng điện ngày/đêm và hệ pin lưu trữ.
                </p>
            </div>

            <a href="{{ route('solar.calculator') }}" class="solar-link-btn">
                Mở công cụ Solar
            </a>
        </div>

        <div id="solar-toast" class="solar-toast"></div>

        @if(session('success'))
            <div class="solar-toast show success" style="display:block;margin-bottom:12px;">
                {{ session('success') }}
            </div>
        @endif

        <div class="solar-card">
            <form id="solar-settings-form" method="POST" action="{{ route('solar.settings.update') }}">
                @csrf

                @foreach($groupedSections as $sectionTitle => $sectionKeys)
                    @php
                        $sectionItems = $settingsCollection->filter(function ($item) use ($sectionKeys) {
                            return in_array($item->key, $sectionKeys);
                        });
                    @endphp

                    @if($sectionItems->count() > 0)
                        <div class="settings-section">
                            <div class="section-head">
                                <div>
                                    <h3>{{ $sectionTitle }}</h3>
                                    <p>{{ $sectionDescriptions[$sectionTitle] ?? '' }}</p>
                                </div>
                            </div>

                            <div class="solar-grid">
                                @foreach($sectionItems as $item)
                                    <div class="solar-field">
                                        <div class="field-top">
                                            <label for="setting_{{ $item->key }}">{{ $item->label }}</label>
                                            <code>{{ $item->key }}</code>
                                        </div>

                                        @if($item->key === 'electricity_mode_default')
                                            <select id="setting_{{ $item->key }}" name="settings[{{ $item->key }}]">
                                                <option value="residential" {{ old('settings.' . $item->key, $item->value) === 'residential' ? 'selected' : '' }}>Sinh hoạt</option>
                                                <option value="business" {{ old('settings.' . $item->key, $item->value) === 'business' ? 'selected' : '' }}>Kinh doanh</option>
                                                <option value="production" {{ old('settings.' . $item->key, $item->value) === 'production' ? 'selected' : '' }}>Sản xuất</option>
                                                <option value="public" {{ old('settings.' . $item->key, $item->value) === 'public' ? 'selected' : '' }}>Hành chính sự nghiệp</option>
                                            </select>
                                        @else
                                            <input
                                                id="setting_{{ $item->key }}"
                                                type="{{ $item->type === 'number' ? 'number' : 'text' }}"
                                                step="any"
                                                name="settings[{{ $item->key }}]"
                                                value="{{ old('settings.' . $item->key, $item->value) }}"
                                            >
                                        @endif

                                        <small>{{ $item->description }}</small>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach

                <div class="solar-formula-box">
                    <h3>Công thức đang chạy</h3>
                    <p><strong>kWp gốc:</strong> Điện năm / (PVOUT × PR)</p>
                    <p><strong>kWp đề xuất:</strong> kWp gốc × tỷ lệ dự phòng</p>
                    <p><strong>Tỷ lệ tự dùng:</strong> theo thói quen dùng điện × hệ số loại hệ</p>
                    <p><strong>Pin lưu trữ:</strong> đề xuất theo phụ tải ngày/đêm và loại hệ</p>
                    <p><strong>Tổng đầu tư:</strong> Solar + Pin lưu trữ</p>
                    <p><strong>Tiết kiệm:</strong> Điện tự dùng × giá điện + điện dư × giá điện dư</p>
                </div>

                <div class="solar-actions">
                    <button type="button" class="btn-secondary" id="reset-defaults">
                        Khôi phục mặc định
                    </button>
                    <button type="submit" class="btn-primary" id="save-settings-btn">
                        Lưu cài đặt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .solar-page{padding:14px 18px}
    .solar-shell{max-width:1180px;margin:0 auto}
    .solar-header{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:14px;flex-wrap:wrap}
    .solar-badge{display:inline-block;padding:5px 10px;border-radius:999px;background:#eef7ff;color:#0f5fa8;font-weight:800;font-size:11px;margin-bottom:8px}
    .solar-header h1{margin:0 0 6px;font-size:28px;line-height:1.15;color:#0f172a}
    .solar-header p{margin:0;color:#64748b;font-size:13px;line-height:1.6;max-width:760px}
    .solar-link-btn{text-decoration:none;background:#0f172a;color:#fff;padding:10px 14px;border-radius:12px;font-weight:700;font-size:13px}
    .solar-card{background:#fff;border-radius:18px;padding:16px;box-shadow:0 8px 24px rgba(15,23,42,.06);border:1px solid #e2e8f0}
    .settings-section{margin-bottom:14px;padding:14px;border:1px solid #e2e8f0;border-radius:16px;background:#fcfdff}
    .section-head h3{margin:0 0 4px;font-size:16px;color:#0f172a}
    .section-head p{margin:0 0 12px;color:#64748b;font-size:12px;line-height:1.55}
    .solar-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .solar-field{background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:12px}
    .field-top{display:flex;justify-content:space-between;gap:8px;align-items:center;margin-bottom:8px}
    .solar-field label{display:block;font-size:13px;font-weight:800;color:#0f172a}
    .solar-field code{font-size:11px;background:#e2e8f0;padding:3px 7px;border-radius:999px;color:#334155}
    .solar-field input,.solar-field select{
        width:100%;
        min-height:40px;
        border:1px solid #cbd5e1;
        border-radius:12px;
        padding:10px 12px;
        font-size:13px;
        background:#fff;
        outline:none;
    }
    .solar-field small{display:block;margin-top:8px;color:#64748b;line-height:1.55;font-size:12px}
    .solar-formula-box{margin-top:14px;padding:14px;border-radius:14px;background:linear-gradient(135deg,#f0fdf4,#eff6ff);border:1px solid #dbeafe}
    .solar-formula-box h3{margin:0 0 10px;color:#0f172a;font-size:15px}
    .solar-formula-box p{margin:5px 0;color:#334155;font-size:12px;line-height:1.55}
    .solar-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:14px;flex-wrap:wrap}
    .btn-primary,.btn-secondary{border:none;border-radius:12px;padding:10px 14px;font-size:13px;font-weight:800;cursor:pointer}
    .btn-primary{background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff}
    .btn-secondary{background:#e2e8f0;color:#0f172a}
    .solar-toast{display:none;margin-bottom:12px;padding:11px 13px;border-radius:12px;font-weight:700;font-size:13px}
    .solar-toast.show{display:block}
    .solar-toast.success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
    .solar-toast.error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
    @media (max-width: 900px){ .solar-grid{grid-template-columns:1fr} }
    @media (max-width: 768px){ .solar-header h1{font-size:24px} }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('solar-settings-form');
    const toast = document.getElementById('solar-toast');
    const resetBtn = document.getElementById('reset-defaults');
    const saveBtn = document.getElementById('save-settings-btn');
    const defaults = @json($defaults);

    function showToast(message, type = 'success') {
        toast.className = 'solar-toast show ' + type;
        toast.innerText = message;
        setTimeout(() => {
            toast.className = 'solar-toast';
        }, 3000);
    }

    resetBtn.addEventListener('click', function () {
        Object.keys(defaults).forEach(key => {
            const input = document.querySelector(`[name="settings[${key}]"]`);
            if (input) input.value = defaults[key];
        });

        showToast('Đã khôi phục đúng bộ mặc định cho Solar + Pin lưu trữ.');
    });

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        saveBtn.disabled = true;
        saveBtn.innerText = 'Đang lưu...';

        try {
            const formData = new FormData(form);

            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: formData
            });

            const result = await response.json();

            if (!response.ok) {
                throw result;
            }

            showToast(result.message || 'Đã lưu cài đặt Solar thành công.');
        } catch (error) {
            if (error.errors) {
                const firstError = Object.values(error.errors)[0][0];
                showToast(firstError, 'error');
            } else {
                showToast('Có lỗi khi lưu cài đặt.', 'error');
            }
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerText = 'Lưu cài đặt';
        }
    });
});
</script>
@endsection