@extends('layouts.app')

@section('content')
@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $employeeId = $employee->id ?? request()->route('employee');

    if (is_object($employee ?? null) && method_exists($employee, 'toArray')) {
        $baseEmployee = (object) $employee->toArray();
    } elseif (is_object($employee ?? null)) {
        $baseEmployee = $employee;
    } else {
        $baseEmployee = (object) [];
    }

    $profile = Schema::hasTable('hr_employee_profiles')
        ? DB::table('hr_employee_profiles')->where('employee_id', (int) $employeeId)->first()
        : null;

    $emp = (object) array_merge((array) $baseEmployee, (array) ($profile ?: []));

    $files = Schema::hasTable('hr_employee_files')
        ? DB::table('hr_employee_files')->where('employee_id', (int) $employeeId)->orderByDesc('id')->get()
        : collect();

    $safeText = function ($value, $default = '—') {
        if ($value === null || $value === '') return $default;

        if (is_array($value)) {
            foreach (['full_name', 'name', 'title', 'department_name', 'position_name', 'label'] as $key) {
                if (!empty($value[$key]) && is_scalar($value[$key])) return (string) $value[$key];
            }

            $parts = [];
            foreach ($value as $v2) {
                if (is_scalar($v2) && $v2 !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $v2)) {
                    $parts[] = (string) $v2;
                }
            }

            return count($parts) ? implode(', ', array_slice($parts, 0, 2)) : $default;
        }

        if (is_object($value)) {
            foreach (['full_name', 'name', 'title', 'department_name', 'position_name', 'label'] as $key) {
                if (!empty($value->{$key}) && is_scalar($value->{$key})) return (string) $value->{$key};
            }

            if (method_exists($value, '__toString')) return (string) $value;

            return $default;
        }

        return (string) $value;
    };

    $v = fn($key, $default = '—') => $safeText(data_get($emp, $key), $default);

    $d = function ($key) use ($emp) {
        $value = data_get($emp, $key);
        if ($value === null || $value === '' || is_array($value) || is_object($value)) return '—';
        try { return date('d/m/Y', strtotime((string) $value)); } catch (Throwable $e) { return '—'; }
    };

    $rawDate = fn($key) => filled(data_get($emp, $key)) && !is_array(data_get($emp, $key)) && !is_object(data_get($emp, $key)) ? data_get($emp, $key) : '';

    $money = function ($key) use ($emp) {
        $value = data_get($emp, $key);
        if ($value === null || $value === '' || is_array($value) || is_object($value) || !is_numeric($value)) return '—';
        return number_format((float) $value, 0, ',', '.') . ' đ';
    };

    $name = $v('full_name', $v('name', 'Nhân viên'));
    $fileTypes = ['CV', 'CCCD/CMND', 'Hợp đồng lao động', 'Bằng cấp', 'Quyết định', 'Ảnh hồ sơ', 'Cam kết', 'File khác'];

    $infoRows = [
        ['Email', $v('email')],
        ['Số điện thoại', $v('phone')],
        ['Phòng ban', $v('department_name', $v('department'))],
        ['Chức vụ', $v('position_name', $v('position'))],
        ['Ngày nhận việc', $d('hire_date')],
        ['Ngày chính thức', $d('official_date')],
        ['Loại hợp đồng', $v('contract_type')],
        ['Hạn hợp đồng', $d('contract_end_date')],
        ['Ngày sinh', $d('birth_date')],
        ['CCCD/CMND', $v('id_card')],
        ['Ngân hàng', $v('bank_name')],
        ['Số tài khoản', $v('bank_account')],
        ['Mã số thuế', $v('tax_code')],
        ['Số BHXH', $v('insurance_number')],
        ['Liên hệ khẩn cấp', $v('emergency_contact_name')],
        ['SĐT khẩn cấp', $v('emergency_contact_phone')],
    ];
@endphp

<style>
    .emp-page{padding:0 4px 34px;font-family:"Be Vietnam Pro",system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
    .emp-head{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;margin-bottom:14px}
    .emp-title{margin:0;font-size:25px;font-weight:950;color:#0f172a;letter-spacing:-.04em}
    .emp-sub{font-size:13px;font-weight:750;color:#64748b;margin-top:4px}
    .emp-actions{display:flex;gap:8px;flex-wrap:wrap}
    .emp-btn,.emp-btn-outline,.emp-btn-danger{height:36px;border-radius:11px;padding:0 13px;border:1px solid transparent;display:inline-flex;align-items:center;justify-content:center;gap:6px;font-size:12px;font-weight:900;text-decoration:none;cursor:pointer;white-space:nowrap}
    .emp-btn{background:#2563eb;color:#fff;box-shadow:0 9px 20px rgba(37,99,235,.16)}
    .emp-btn-outline{background:#fff;color:#0f172a;border-color:#dbe3ef}
    .emp-btn-danger{background:#fff1f2;color:#be123c;border-color:#fecdd3}
    .emp-card{background:#fff;border:1px solid #e2e8f0;border-radius:20px;box-shadow:0 16px 36px rgba(15,23,42,.055);overflow:hidden;margin-bottom:14px}
    .emp-profile{padding:18px;display:grid;grid-template-columns:1fr auto;gap:18px;align-items:center}
    .emp-identity{display:flex;gap:14px;align-items:center}
    .emp-avatar{width:72px;height:72px;border-radius:22px;background:linear-gradient(135deg,#dbeafe,#ccfbf1);display:flex;align-items:center;justify-content:center;color:#0f766e;font-size:25px;font-weight:950;border:1px solid #bfdbfe;flex:0 0 auto}
    .emp-name{font-size:23px;font-weight:950;color:#0f172a;margin-bottom:8px}
    .emp-chip-row{display:flex;gap:7px;flex-wrap:wrap}
    .emp-chip{height:27px;border-radius:999px;padding:0 10px;display:inline-flex;align-items:center;border:1px solid #dbeafe;background:#eff6ff;color:#1d4ed8;font-size:11px;font-weight:950}
    .emp-chip.green{border-color:#bbf7d0;background:#f0fdf4;color:#15803d}
    .emp-chip.yellow{border-color:#fde68a;background:#fffbeb;color:#b45309}
    .emp-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;padding:0 18px 18px}
    .emp-mini{border:1px solid #edf2f7;border-radius:14px;padding:11px 12px;background:#fbfdff}
    .emp-label{font-size:10px;font-weight:950;text-transform:uppercase;color:#64748b;margin-bottom:5px}
    .emp-value{font-size:13px;font-weight:900;color:#0f172a;word-break:break-word}
    .emp-grid{display:grid;grid-template-columns:1.15fr .85fr;gap:14px}
    .emp-section{padding:16px 18px}
    .emp-section-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:12px}
    .emp-section-title{font-size:16px;font-weight:950;color:#0f172a;margin:0}
    .emp-section-note{font-size:12px;font-weight:700;color:#64748b;margin-top:3px}
    .emp-info-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
    .emp-file-list{display:flex;flex-direction:column;gap:8px}
    .emp-file{border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;padding:10px 11px;display:flex;justify-content:space-between;gap:10px;align-items:center}
    .emp-file-name{font-size:13px;font-weight:900;color:#0f172a}
    .emp-file-meta{font-size:11px;font-weight:700;color:#64748b;margin-top:3px}
    .emp-empty{padding:24px 12px;border:1px dashed #cbd5e1;border-radius:14px;background:#f8fafc;color:#64748b;font-size:12px;font-weight:750;text-align:center}
    .emp-alert{margin-bottom:12px;padding:10px 12px;border:1px solid #bbf7d0;background:#f0fdf4;color:#166534;border-radius:14px;font-size:12px;font-weight:850}
    .emp-error{margin-bottom:12px;padding:10px 12px;border:1px solid #fecaca;background:#fff1f2;color:#be123c;border-radius:14px;font-size:12px;font-weight:850}
    .emp-modal-bg{position:fixed;inset:0;background:rgba(15,23,42,.52);z-index:9999;display:none;align-items:center;justify-content:center;padding:18px}
    .emp-modal-bg.show{display:flex}
    .emp-modal{width:min(980px,100%);max-height:92vh;overflow:auto;background:#fff;border-radius:22px;border:1px solid #e2e8f0;box-shadow:0 30px 90px rgba(15,23,42,.28)}
    .emp-modal.small{width:min(640px,100%)}
    .emp-modal-head{position:sticky;top:0;background:linear-gradient(135deg,#eff6ff,#ecfeff);padding:16px 18px;border-bottom:1px solid #dbeafe;display:flex;align-items:center;justify-content:space-between;gap:12px;z-index:2}
    .emp-modal-title{margin:0;font-size:18px;font-weight:950;color:#0f172a}
    .emp-modal-close{width:34px;height:34px;border-radius:10px;border:1px solid #dbe3ef;background:#fff;font-size:18px;font-weight:950;cursor:pointer}
    .emp-modal-body{padding:16px 18px}
    .emp-modal-foot{position:sticky;bottom:0;background:#f8fafc;border-top:1px solid #edf2f7;padding:14px 18px;display:flex;justify-content:flex-end;gap:8px}
    .emp-form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
    .emp-field{display:flex;flex-direction:column;gap:6px}
    .emp-field.full{grid-column:1/-1}
    .emp-form-label{font-size:12px;font-weight:900;color:#475569}
    .emp-input,.emp-select,.emp-textarea{width:100%;border:1px solid #dbe3ef;border-radius:12px;background:#fff;color:#0f172a;font-size:12px;font-weight:750;outline:none}
    .emp-input,.emp-select{height:38px;padding:0 11px}
    .emp-textarea{min-height:76px;padding:10px 11px;resize:vertical}
    .emp-input:focus,.emp-select:focus,.emp-textarea:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12)}
    @media(max-width:1100px){.emp-grid{grid-template-columns:1fr}.emp-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.emp-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:760px){.emp-head,.emp-profile{display:block}.emp-actions{margin-top:10px}.emp-summary,.emp-info-list,.emp-form-grid{grid-template-columns:1fr}.emp-btn,.emp-btn-outline,.emp-btn-danger{width:100%}.emp-file{display:block}.emp-file .emp-actions{margin-top:8px}}
</style>

<div class="emp-page">
    <div class="emp-head">
        <div>
            <h1 class="emp-title">Chi tiết nhân viên</h1>
            <div class="emp-sub">Trang chính chỉ hiển thị thông tin. Nhập liệu nằm trong popup riêng.</div>
        </div>
        <div class="emp-actions">
            <button class="emp-btn" type="button" onclick="openEmpModal('profileModal')">Cập nhật hồ sơ</button>
            <button class="emp-btn-outline" type="button" onclick="openEmpModal('fileModal')">Upload file</button>
            <a class="emp-btn-outline" href="{{ route('hr.employees.index') }}">Quay lại</a>
        </div>
    </div>

    @if(session('success'))
        <div class="emp-alert">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="emp-error">{{ $errors->first() }}</div>
    @endif

    <div class="emp-card">
        <div class="emp-profile">
            <div class="emp-identity">
                <div class="emp-avatar">{{ mb_substr($name, 0, 1) }}</div>
                <div>
                    <div class="emp-name">{{ $name }}</div>
                    <div class="emp-chip-row">
                        <span class="emp-chip">{{ $v('employee_code', 'Chưa có mã NV') }}</span>
                        <span class="emp-chip green">{{ $v('status', 'Đang hoạt động') }}</span>
                        <span class="emp-chip yellow">{{ $v('role', 'Chưa gán vai trò') }}</span>
                    </div>
                </div>
            </div>
            <div class="emp-actions">
                <a class="emp-btn-outline" href="{{ route('hr.employees.edit', $employeeId) }}">Sửa thông tin chính</a>
            </div>
        </div>

        <div class="emp-summary">
            <div class="emp-mini"><div class="emp-label">Email</div><div class="emp-value">{{ $v('email') }}</div></div>
            <div class="emp-mini"><div class="emp-label">Số điện thoại</div><div class="emp-value">{{ $v('phone') }}</div></div>
            <div class="emp-mini"><div class="emp-label">Phòng ban</div><div class="emp-value">{{ $v('department_name', $v('department')) }}</div></div>
            <div class="emp-mini"><div class="emp-label">Chức vụ</div><div class="emp-value">{{ $v('position_name', $v('position')) }}</div></div>
        </div>
    </div>

    <div class="emp-grid">
        <div class="emp-card">
            <div class="emp-section">
                <div class="emp-section-head">
                    <div>
                        <h3 class="emp-section-title">Hồ sơ nhân sự</h3>
                        <div class="emp-section-note">Chỉ hiển thị các thông tin quan trọng.</div>
                    </div>
                    <button class="emp-btn-outline" type="button" onclick="openEmpModal('profileModal')">Sửa hồ sơ</button>
                </div>

                <div class="emp-info-list">
                    @foreach($infoRows as [$label, $value])
                        <div class="emp-mini">
                            <div class="emp-label">{{ $label }}</div>
                            <div class="emp-value">{{ $value }}</div>
                        </div>
                    @endforeach
                    <div class="emp-mini">
                        <div class="emp-label">Địa chỉ</div>
                        <div class="emp-value">{{ $v('address') }}</div>
                    </div>
                    <div class="emp-mini">
                        <div class="emp-label">Ghi chú nhân sự</div>
                        <div class="emp-value">{{ $v('hr_note') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="emp-card">
                <div class="emp-section">
                    <div class="emp-section-head">
                        <div>
                            <h3 class="emp-section-title">Mức lương</h3>
                            <div class="emp-section-note">Thông tin lương đang lưu trên hồ sơ.</div>
                        </div>
                    </div>

                    <div class="emp-info-list" style="grid-template-columns:1fr;">
                        <div class="emp-mini"><div class="emp-label">Lương chính thức</div><div class="emp-value">{{ $money('official_salary') }}</div></div>
                        <div class="emp-mini"><div class="emp-label">Lương thử việc</div><div class="emp-value">{{ $money('probation_salary') }}</div></div>
                        <div class="emp-mini"><div class="emp-label">Lương thực tập</div><div class="emp-value">{{ $money('intern_salary') }}</div></div>
                    </div>
                </div>
            </div>

            <div class="emp-card">
                <div class="emp-section">
                    <div class="emp-section-head">
                        <div>
                            <h3 class="emp-section-title">File hồ sơ</h3>
                            <div class="emp-section-note">{{ $files->count() }} file đã upload.</div>
                        </div>
                        <button class="emp-btn-outline" type="button" onclick="openEmpModal('fileModal')">+ File</button>
                    </div>

                    @if($files->count())
                        <div class="emp-file-list">
                            @foreach($files as $file)
                                <div class="emp-file">
                                    <div>
                                        <div class="emp-file-name">{{ $file->original_name }}</div>
                                        <div class="emp-file-meta">
                                            {{ $file->file_type ?: 'Hồ sơ khác' }}
                                            · {{ number_format(($file->size_bytes ?? 0) / 1024, 1) }} KB
                                            · {{ date('d/m/Y H:i', strtotime($file->created_at)) }}
                                        </div>
                                    </div>
                                    <div class="emp-actions">
                                        <a class="emp-btn-outline" href="{{ route('hr.employees.files.download', $file->id) }}">Tải</a>
                                        <form method="POST" action="{{ route('hr.employees.files.delete', $file->id) }}" onsubmit="return confirm('Xoá file này?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="emp-btn-danger" type="submit">Xoá</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="emp-empty">Chưa có file hồ sơ nào.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div id="profileModal" class="emp-modal-bg" onclick="closeEmpModal(event, 'profileModal')">
    <div class="emp-modal" onclick="event.stopPropagation()">
        <div class="emp-modal-head">
            <h3 class="emp-modal-title">Cập nhật hồ sơ mở rộng</h3>
            <button class="emp-modal-close" type="button" onclick="hideEmpModal('profileModal')">×</button>
        </div>

        <form method="POST" action="{{ route('hr.employees.extras.update', $employeeId) }}">
            @csrf
            @method('PUT')

            <div class="emp-modal-body">
                <div class="emp-form-grid">
                    <div class="emp-field"><label class="emp-form-label">Mã nhân viên</label><input class="emp-input" name="employee_code" value="{{ $v('employee_code', '') }}"></div>
                    <div class="emp-field"><label class="emp-form-label">Ngày nhận việc</label><input class="emp-input" type="date" name="hire_date" value="{{ $rawDate('hire_date') }}"></div>
                    <div class="emp-field"><label class="emp-form-label">Ngày chính thức</label><input class="emp-input" type="date" name="official_date" value="{{ $rawDate('official_date') }}"></div>

                    <div class="emp-field"><label class="emp-form-label">Bắt đầu thử việc</label><input class="emp-input" type="date" name="probation_start_date" value="{{ $rawDate('probation_start_date') }}"></div>
                    <div class="emp-field"><label class="emp-form-label">Kết thúc thử việc</label><input class="emp-input" type="date" name="probation_end_date" value="{{ $rawDate('probation_end_date') }}"></div>
                    <div class="emp-field">
                        <label class="emp-form-label">Loại hợp đồng</label>
                        <select class="emp-select" name="contract_type">
                            @foreach(['Thử việc', 'Chính thức', 'CTV', 'Thực tập', 'Khoán việc', 'Khác'] as $type)
                                <option value="{{ $type }}" @selected($v('contract_type', '') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="emp-field"><label class="emp-form-label">HĐ từ ngày</label><input class="emp-input" type="date" name="contract_start_date" value="{{ $rawDate('contract_start_date') }}"></div>
                    <div class="emp-field"><label class="emp-form-label">HĐ đến ngày</label><input class="emp-input" type="date" name="contract_end_date" value="{{ $rawDate('contract_end_date') }}"></div>
                    <div class="emp-field"><label class="emp-form-label">Ngày sinh</label><input class="emp-input" type="date" name="birth_date" value="{{ $rawDate('birth_date') }}"></div>

                    <div class="emp-field">
                        <label class="emp-form-label">Giới tính</label>
                        <select class="emp-select" name="gender">
                            @foreach(['Nam', 'Nữ', 'Khác'] as $gender)
                                <option value="{{ $gender }}" @selected($v('gender', '') === $gender)>{{ $gender }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="emp-field"><label class="emp-form-label">CCCD/CMND</label><input class="emp-input" name="id_card" value="{{ $v('id_card', '') }}"></div>
                    <div class="emp-field"><label class="emp-form-label">Ngày cấp CCCD</label><input class="emp-input" type="date" name="id_card_date" value="{{ $rawDate('id_card_date') }}"></div>

                    <div class="emp-field"><label class="emp-form-label">Nơi cấp CCCD</label><input class="emp-input" name="id_card_place" value="{{ $v('id_card_place', '') }}"></div>
                    <div class="emp-field"><label class="emp-form-label">Ngân hàng</label><input class="emp-input" name="bank_name" value="{{ $v('bank_name', '') }}"></div>
                    <div class="emp-field"><label class="emp-form-label">Số tài khoản</label><input class="emp-input" name="bank_account" value="{{ $v('bank_account', '') }}"></div>

                    <div class="emp-field"><label class="emp-form-label">Mã số thuế</label><input class="emp-input" name="tax_code" value="{{ $v('tax_code', '') }}"></div>
                    <div class="emp-field"><label class="emp-form-label">Số BHXH</label><input class="emp-input" name="insurance_number" value="{{ $v('insurance_number', '') }}"></div>
                    <div class="emp-field"><label class="emp-form-label">SĐT khẩn cấp</label><input class="emp-input" name="emergency_contact_phone" value="{{ $v('emergency_contact_phone', '') }}"></div>

                    <div class="emp-field"><label class="emp-form-label">Liên hệ khẩn cấp</label><input class="emp-input" name="emergency_contact_name" value="{{ $v('emergency_contact_name', '') }}"></div>
                    <div class="emp-field full"><label class="emp-form-label">Địa chỉ</label><textarea class="emp-textarea" name="address">{{ $v('address', '') }}</textarea></div>
                    <div class="emp-field full"><label class="emp-form-label">Ghi chú nhân sự</label><textarea class="emp-textarea" name="hr_note">{{ $v('hr_note', '') }}</textarea></div>
                </div>
            </div>

            <div class="emp-modal-foot">
                <button class="emp-btn-outline" type="button" onclick="hideEmpModal('profileModal')">Huỷ</button>
                <button class="emp-btn" type="submit">Lưu hồ sơ</button>
            </div>
        </form>
    </div>
</div>

<div id="fileModal" class="emp-modal-bg" onclick="closeEmpModal(event, 'fileModal')">
    <div class="emp-modal small" onclick="event.stopPropagation()">
        <div class="emp-modal-head">
            <h3 class="emp-modal-title">Upload file hồ sơ</h3>
            <button class="emp-modal-close" type="button" onclick="hideEmpModal('fileModal')">×</button>
        </div>

        <form method="POST" action="{{ route('hr.employees.files.store', $employeeId) }}" enctype="multipart/form-data">
            @csrf
            <div class="emp-modal-body">
                <div class="emp-field" style="margin-bottom:10px;">
                    <label class="emp-form-label">Loại file</label>
                    <select class="emp-select" name="file_type">
                        @foreach($fileTypes as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="emp-field" style="margin-bottom:10px;">
                    <label class="emp-form-label">Chọn file</label>
                    <input class="emp-input" type="file" name="file" required>
                </div>

                <div class="emp-field">
                    <label class="emp-form-label">Ghi chú</label>
                    <input class="emp-input" name="note" placeholder="VD: HĐLĐ bản scan, CCCD mặt trước...">
                </div>
            </div>

            <div class="emp-modal-foot">
                <button class="emp-btn-outline" type="button" onclick="hideEmpModal('fileModal')">Huỷ</button>
                <button class="emp-btn" type="submit">+ Upload</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEmpModal(id){
        document.getElementById(id).classList.add('show');
    }

    function hideEmpModal(id){
        document.getElementById(id).classList.remove('show');
    }

    function closeEmpModal(event, id){
        if(event.target.id === id){
            hideEmpModal(id);
        }
    }

    document.addEventListener('keydown', function(e){
        if(e.key === 'Escape'){
            document.querySelectorAll('.emp-modal-bg').forEach(function(modal){
                modal.classList.remove('show');
            });
        }
    });
</script>
@endsection
