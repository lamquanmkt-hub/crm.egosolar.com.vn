@extends('layouts.app')

@section('content')
<style>
    .create-page{
        min-height:100vh;
        background:#f4f7fb;
        padding-bottom:48px;
        font-size:13px;
        font-family:inherit;
    }

    .page-shell{
        padding:22px;
    }

    .page-head{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
        margin-bottom:16px;
    }

    .page-head h4{
        margin:0;
        font-size:22px;
        font-weight:950;
        color:#0f172a;
    }

    .page-head .desc{
        color:#64748b;
        font-size:12px;
        margin-top:2px;
    }

    .btn-pill{
        border-radius:999px;
        font-weight:800;
        font-size:13px;
        padding:8px 16px;
    }

    .create-hero{
        border-radius:24px;
        padding:22px;
        color:#fff;
        background:
            radial-gradient(650px 260px at 90% 0%, rgba(34,211,238,.23), transparent 60%),
            linear-gradient(135deg,#020617,#075985 55%,#0f766e);
        box-shadow:0 18px 44px rgba(15,23,42,.18);
        margin-bottom:16px;
    }

    .create-hero h3{
        font-size:24px;
        font-weight:950;
        margin:0;
        letter-spacing:-.03em;
    }

    .create-hero p{
        margin:5px 0 0;
        opacity:.82;
        font-size:13px;
    }

    .layout{
        display:grid;
        grid-template-columns:1fr 360px;
        gap:16px;
        align-items:start;
    }

    .soft-card{
        background:#fff;
        border:1px solid #e5eaf1;
        border-radius:22px;
        overflow:hidden;
        box-shadow:0 12px 32px rgba(15,23,42,.065);
    }

    .card-head{
        padding:14px 16px;
        border-bottom:1px solid #e5eaf1;
        background:#fff;
        font-size:15px;
        font-weight:950;
        color:#0f172a;
    }

    .form-body{
        padding:18px;
    }

    .form-label{
        color:#334155;
        font-size:12px;
        font-weight:850;
        margin-bottom:5px;
    }

    .form-control,
    .form-select{
        border-radius:12px;
        border-color:#dbe3ee;
        font-size:13px;
    }

    .form-control:focus,
    .form-select:focus{
        border-color:#0ea5e9;
        box-shadow:0 0 0 .2rem rgba(14,165,233,.12);
    }

    .upload-zone{
        border:2px dashed #cbd5e1;
        border-radius:18px;
        padding:20px;
        text-align:center;
        background:#f8fafc;
        transition:.15s ease;
    }

    .upload-zone:hover{
        border-color:#38bdf8;
        background:#f0f9ff;
    }

    .upload-icon{
        width:54px;
        height:54px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        border-radius:18px;
        background:#e0f2fe;
        color:#0369a1;
        font-size:27px;
        margin-bottom:10px;
    }

    .side-tip{
        padding:15px;
        border-bottom:1px solid #e5eaf1;
    }

    .side-tip:last-child{
        border-bottom:0;
    }

    .side-tip .num{
        width:28px;
        height:28px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        border-radius:10px;
        background:#e0f2fe;
        color:#0369a1;
        font-weight:950;
        margin-right:8px;
    }

    .side-tip .title{
        font-weight:950;
        color:#0f172a;
    }

    .side-tip .desc{
        color:#64748b;
        font-size:12px;
        margin-top:5px;
        line-height:1.55;
    }

    .sticky-actions{
        position:sticky;
        bottom:12px;
        z-index:30;
        margin-top:14px;
        padding:12px;
        background:rgba(244,247,251,.88);
        backdrop-filter:blur(10px);
        display:flex;
        justify-content:flex-end;
        gap:8px;
    }

    @media(max-width:1100px){
        .layout{grid-template-columns:1fr}
    }

    @media(max-width:768px){
        .page-shell{padding:14px}
        .page-head{flex-direction:column;align-items:flex-start}
    }
</style>

<div class="create-page">
    <div class="page-shell">

        <div class="page-head">
            <div>
                <h4>Tạo đề xuất mới</h4>
                <div class="desc">Điền nội dung rõ ràng và đính kèm file/ảnh để sếp duyệt nhanh hơn.</div>
            </div>

            <a href="{{ route('de-xuat.index') }}" class="btn btn-outline-secondary btn-pill">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
        </div>

        <div class="create-hero">
            <h3>Phiếu đề xuất nội bộ</h3>
            <p>Hỗ trợ nhiều loại đề xuất: mua sắm, tạm ứng, sửa chữa, nhân sự, quy trình, công việc hoặc đề xuất khác.</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger border-0 shadow-sm rounded-4">
                <b>Chưa gửi được đề xuất.</b>
                <ul class="mb-0 mt-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('de-xuat.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="layout">
                <div class="soft-card">
                    <div class="card-head">
                        <i class="bi bi-pencil-square"></i> Thông tin đề xuất
                    </div>

                    <div class="form-body">
                        <div class="mb-3">
                            <label class="form-label">Tiêu đề đề xuất <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="title"
                                   class="form-control"
                                   value="{{ old('title') }}"
                                   required
                                   placeholder="Ví dụ: Đề xuất mua máy khoan cho đội thi công">
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Loại đề xuất</label>
                                <select name="proposal_type" class="form-select">
                                    @foreach($types as $key => $label)
                                        <option value="{{ $key }}" {{ old('proposal_type') === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Mức ưu tiên</label>
                                <select name="priority" class="form-select">
                                    @foreach($priorities as $key => $label)
                                        <option value="{{ $key }}" {{ old('priority', 'normal') === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Ngày cần xử lý</label>
                                <input type="date"
                                       name="needed_date"
                                       class="form-control"
                                       value="{{ old('needed_date') }}">
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phòng ban</label>
                                <input type="text"
                                       name="department_name"
                                       class="form-control"
                                       value="{{ old('department_name', $departmentName ?? '') }}"
                                       placeholder="Ví dụ: Kỹ thuật, Sales, Marketing">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Số tiền dự kiến</label>
                                <input type="number"
                                       name="amount"
                                       class="form-control"
                                       value="{{ old('amount', 0) }}"
                                       min="0"
                                       step="any">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nội dung đề xuất</label>
                            <textarea name="content"
                                      rows="4"
                                      class="form-control"
                                      placeholder="Bạn cần đề xuất việc gì?">{{ old('content') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Lý do đề xuất</label>
                            <textarea name="reason"
                                      rows="4"
                                      class="form-control"
                                      placeholder="Vì sao đề xuất này cần được duyệt?">{{ old('reason') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Kết quả kỳ vọng</label>
                            <textarea name="expected_result"
                                      rows="3"
                                      class="form-control"
                                      placeholder="Sau khi được duyệt sẽ mang lại kết quả gì?">{{ old('expected_result') }}</textarea>
                        </div>

                        <div class="upload-zone">
                            <div class="upload-icon">
                                <i class="bi bi-cloud-arrow-up"></i>
                            </div>

                            <div class="fw-bold">Đính kèm file / hình ảnh</div>
                            <div class="text-muted small mb-3">
                                Có thể chọn nhiều file. Tối đa 20MB mỗi file.
                            </div>

                            <input type="file"
                                   name="attachments[]"
                                   class="form-control"
                                   multiple>
                        </div>
                    </div>
                </div>

                <div class="soft-card">
                    <div class="card-head">
                        <i class="bi bi-stars"></i> Gợi ý để được duyệt nhanh
                    </div>

                    <div class="side-tip">
                        <div>
                            <span class="num">1</span>
                            <span class="title">Tiêu đề rõ ràng</span>
                        </div>
                        <div class="desc">
                            Nên ghi rõ mục tiêu và đối tượng sử dụng, ví dụ: “Đề xuất mua 2 máy khoan cho đội kỹ thuật”.
                        </div>
                    </div>

                    <div class="side-tip">
                        <div>
                            <span class="num">2</span>
                            <span class="title">Lý do cụ thể</span>
                        </div>
                        <div class="desc">
                            Trình bày vấn đề hiện tại, ảnh hưởng nếu không xử lý và vì sao cần duyệt.
                        </div>
                    </div>

                    <div class="side-tip">
                        <div>
                            <span class="num">3</span>
                            <span class="title">Có kết quả kỳ vọng</span>
                        </div>
                        <div class="desc">
                            Nêu đề xuất giúp tiết kiệm chi phí, tăng hiệu suất, giảm rủi ro hoặc phục vụ công việc thế nào.
                        </div>
                    </div>

                    <div class="side-tip">
                        <div>
                            <span class="num">4</span>
                            <span class="title">Đính kèm bằng chứng</span>
                        </div>
                        <div class="desc">
                            Ảnh hiện trạng, báo giá, hóa đơn, PDF hoặc Excel sẽ giúp người duyệt ra quyết định nhanh hơn.
                        </div>
                    </div>
                </div>
            </div>

            <div class="sticky-actions">
                <a href="{{ route('de-xuat.index') }}" class="btn btn-outline-secondary btn-pill">
                    Hủy
                </a>

                <button type="submit" class="btn btn-success btn-pill px-5">
                    <i class="bi bi-send"></i> Gửi đề xuất
                </button>
            </div>
        </form>

    </div>
</div>
@endsection