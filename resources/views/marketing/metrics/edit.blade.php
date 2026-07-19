@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 mt-3">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">Sửa chỉ số Marketing</h4>
            <small class="text-muted">Marketing / Chỉ số</small>
        </div>
        <a href="{{ route('marketing.budget') }}" class="btn btn-outline-secondary">Quay lại</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Có lỗi dữ liệu:</div>
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        // Nếu DB lưu dạng json string, decode về array
        $m = $m ?? ($row ?? ($metric ?? null));
        $gender = $m->gender_breakdown;
        if (is_string($gender)) $gender = json_decode($gender, true);
        $gender = is_array($gender) ? $gender : [];

        $age = $m->age_breakdown;
        if (is_string($age)) $age = json_decode($age, true);
        $age = is_array($age) ? $age : [];

        $region = $m->region_breakdown;
        if (is_string($region)) $region = json_decode($region, true);
        $region = is_array($region) ? $region : [];
    @endphp

    <div class="card border-0 shadow-sm">
        <div class="card-body">

            <form method="POST" action="{{ route('marketing.metrics.update', $m->id) }}" class="row g-2">
                @csrf
                @method('PUT')

                <div class="col-md-3">
                    <label class="form-label small text-muted">Từ ngày</label>
                    <input type="date" name="date_from" class="form-control" required
                           value="{{ old('date_from', $m->date_from ? \Illuminate\Support\Carbon::parse($m->date_from)->format('Y-m-d') : '') }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label small text-muted">Đến ngày</label>
                    <input type="date" name="date_to" class="form-control" required
                           value="{{ old('date_to', $m->date_to ? \Illuminate\Support\Carbon::parse($m->date_to)->format('Y-m-d') : '') }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label small text-muted">Kênh</label>
                    <select name="platform" class="form-select" required>
                        @foreach(['Facebook','Google','TikTok','Zalo','Khác'] as $p)
                            <option value="{{ $p }}" {{ old('platform', $m->platform) == $p ? 'selected' : '' }}>
                                {{ $p }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small text-muted">Chiến dịch</label>
                    <select name="campaign_id" class="form-select" required>
                        <option value="">-- Chọn chiến dịch --</option>
                        @foreach($campaigns as $c)
                            <option value="{{ $c->id }}" {{ (string)old('campaign_id', $m->campaign_id) === (string)$c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small text-muted">Reach (tiếp cận)</label>
                    <input type="number" name="reach" class="form-control" min="0" required
                           value="{{ old('reach', $m->reach ?? 0) }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label small text-muted">Số lead</label>
                    <input type="number" name="leads" class="form-control" min="0" required
                           value="{{ old('leads', $m->leads ?? 0) }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label small text-muted">Chi tiêu (nếu có)</label>
                    <input type="number" name="spend" class="form-control" min="0"
                           value="{{ old('spend', $m->spend ?? 0) }}">
                </div>

                {{-- BREAKDOWN: GIỚI TÍNH --}}
                <div class="col-12"><div class="fw-semibold small text-muted mt-2">Giới tính (số lượng)</div></div>
                <div class="col-md-4">
                    <label class="form-label small">Nam</label>
                    <input type="number" name="gender[male]" class="form-control" min="0"
                           value="{{ old('gender.male', $gender['male'] ?? 0) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Nữ</label>
                    <input type="number" name="gender[female]" class="form-control" min="0"
                           value="{{ old('gender.female', $gender['female'] ?? 0) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Không rõ</label>
                    <input type="number" name="gender[unknown]" class="form-control" min="0"
                           value="{{ old('gender.unknown', $gender['unknown'] ?? 0) }}">
                </div>

                {{-- BREAKDOWN: ĐỘ TUỔI --}}
                <div class="col-12"><div class="fw-semibold small text-muted mt-2">Độ tuổi (số lượng)</div></div>
                @foreach(['18-24','25-34','35-44','45-54','55+'] as $ar)
                    <div class="col-md-4">
                        <label class="form-label small">{{ $ar }}</label>
                        <input type="number" name="age[{{ $ar }}]" class="form-control" min="0"
                               value="{{ old('age.'.$ar, $age[$ar] ?? 0) }}">
                    </div>
                @endforeach

                {{-- BREAKDOWN: KHU VỰC --}}
                <div class="col-12"><div class="fw-semibold small text-muted mt-2">Khu vực (số lượng)</div></div>
                <div class="col-md-4">
                    <label class="form-label small">HCM</label>
                    <input type="number" name="region[HCM]" class="form-control" min="0"
                           value="{{ old('region.HCM', $region['HCM'] ?? 0) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Hà Nội</label>
                    <input type="number" name="region[Hà Nội]" class="form-control" min="0"
                           value="{{ old('region.Hà Nội', $region['Hà Nội'] ?? 0) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Khác</label>
                    <input type="number" name="region[Khác]" class="form-control" min="0"
                           value="{{ old('region.Khác', $region['Khác'] ?? 0) }}">
                </div>

                <div class="col-12">
                    <label class="form-label small text-muted">Ghi chú</label>
                    <input type="text" name="note" class="form-control" placeholder="Tuỳ chọn"
                           value="{{ old('note', $m->note) }}">
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                    <a href="{{ route('marketing.budget') }}" class="btn btn-light">Hủy</a>
                    <button class="btn btn-primary">Lưu chỉ số</button>
                </div>

            </form>

        </div>
    </div>
</div>
@endsection
