@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">Sửa ngân sách</h4>
            <small class="text-muted">Marketing / Ngân sách</small>
        </div>

        <div class="d-flex gap-2">
            @hasanyrole('marketing_manager|admin')
            <form method="POST" action="{{ route('marketing.budget.clone_next', $row->id) }}" class="d-inline">
                @csrf
                <button class="btn btn-outline-primary">+ Tạo dòng tháng sau</button>
            </form>
            @endhasanyrole

            <a href="{{ route('marketing.budget') }}" class="btn btn-outline-secondary">Quay lại</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif

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

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('marketing.budget.update', $row->id) }}" class="row g-2">
                @csrf
                @method('PUT')

                <div class="col-md-2">
                    <label class="form-label small text-muted">Tháng</label>
                    <input type="month" name="month" class="form-control" required
                           value="{{ old('month', optional($row->month)->format('Y-m')) }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label small text-muted">Kênh</label>
                    <input type="text" name="platform" class="form-control" required
                           value="{{ old('platform', $row->platform) }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label small text-muted">Chiến dịch</label>
                    <select name="campaign_id" class="form-select" required>
                        <option value="">-- Chọn chiến dịch --</option>
                        @foreach($campaigns as $c)
                            <option value="{{ $c->id }}"
                                {{ (string)old('campaign_id', $row->campaign_id) === (string)$c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small text-muted">Ngân sách (đ)</label>
                    <input type="number" name="budget" class="form-control" min="0" required
                           value="{{ old('budget', $row->budget) }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label small text-muted">Đã chi (đ)</label>
                    <input type="number" name="actual_spent" class="form-control" min="0"
                           value="{{ old('actual_spent', $row->actual_spent) }}">
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-success w-100">Lưu</button>
                </div>

                <div class="col-12">
                    <label class="form-label small text-muted">Ghi chú</label>
                    <input type="text" name="note" class="form-control"
                           value="{{ old('note', $row->note) }}">
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
