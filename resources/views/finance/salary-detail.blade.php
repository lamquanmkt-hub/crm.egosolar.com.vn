@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row g-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-4" style="background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 100%); color: white;">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <div class="text-uppercase small fw-bold opacity-75 mb-2">Phiếu lương cá nhân</div>
                            <h2 class="mb-1 fw-bold">Chi tiết lương nhân viên</h2>
                            <div class="opacity-75">
                                Xem thông tin lương, ngày công và dữ liệu liên quan theo từng kỳ lương
                            </div>
                        </div>

                        <div class="text-end">
                            <div class="small opacity-75">Kỳ lương</div>
                            <div class="fs-4 fw-bold">{{ $month }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white"
                             style="width:60px;height:60px;background:linear-gradient(135deg,#2563eb,#38bdf8);font-size:22px;">
                            {{ strtoupper(mb_substr($employee->name ?? 'N', 0, 1)) }}
                        </div>
                        <div>
                            <h4 class="mb-1 fw-bold">{{ $employee->name ?? '-' }}</h4>
                            <div class="text-muted">{{ optional($employee->position)->name ?? '-' }}</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small d-block mb-1">Phòng ban</label>
                        <div class="fw-semibold">{{ optional($employee->department)->name ?? '-' }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small d-block mb-1">Chức vụ</label>
                        <div class="fw-semibold">{{ optional($employee->position)->name ?? '-' }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small d-block mb-1">Nhân viên</label>
                        <div class="fw-semibold">#{{ $employee->id }} - {{ $employee->name ?? '-' }}</div>
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('finance.salary', ['month' => $month]) }}" class="btn btn-outline-primary rounded-3">
                            ← Quay lại bảng lương
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body p-4">
                            <div class="text-muted small mb-2">Ngày công thực tế</div>
                            <div class="fs-2 fw-bold text-primary">{{ $workingDays }}</div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body p-4">
                            <div class="text-muted small mb-2">Tổng phút làm việc</div>
                            <div class="fs-2 fw-bold text-dark">{{ number_format($totalMinutes) }}</div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body p-4">
                            <div class="text-muted small mb-2">Trạng thái</div>
                            <div class="fs-4 fw-bold text-success">Đã đồng bộ chấm công</div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <h4 class="mb-1 fw-bold">Tóm tắt phiếu lương</h4>
                                    <div class="text-muted">Bản chi tiết đang lấy dữ liệu từ hệ thống chấm công</div>
                                </div>
                                <button class="btn btn-primary rounded-3" onclick="window.print()">In phiếu lương</button>
                            </div>

                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 260px;">Nhân viên</td>
                                            <td class="fw-semibold">{{ $employee->name ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Phòng ban</td>
                                            <td class="fw-semibold">{{ optional($employee->department)->name ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Chức vụ</td>
                                            <td class="fw-semibold">{{ optional($employee->position)->name ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Kỳ lương</td>
                                            <td class="fw-semibold">{{ $month }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Ngày công thực tế</td>
                                            <td class="fw-semibold">{{ $workingDays }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Tổng phút làm việc</td>
                                            <td class="fw-semibold">{{ number_format($totalMinutes) }} phút</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Lương cơ bản</td>
                                            <td class="fw-semibold text-secondary">Chưa cấu hình</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Phụ cấp / Hoa hồng / Thưởng</td>
                                            <td class="fw-semibold text-secondary">Chưa cấu hình</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Khấu trừ / Tạm ứng</td>
                                            <td class="fw-semibold text-secondary">Chưa cấu hình</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Thực nhận</td>
                                            <td class="fw-bold text-primary">Sẽ tính ở bước tiếp theo</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="alert alert-info border-0 rounded-4 mt-3 mb-0">
                                Hiện tại trang này đã nối được với dữ liệu chấm công. Bước tiếp theo sẽ là:
                                cấu hình lương cơ bản, phụ cấp, khấu trừ và tính thực nhận thật.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection