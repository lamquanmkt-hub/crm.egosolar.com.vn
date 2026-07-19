@extends('layouts.app')

@section('content')
<style>
    .online-page{
        min-height:100vh;
        background:#f4f7fb;
        padding-bottom:48px;
        font-size:13px;
    }

    .online-shell{
        padding:22px;
    }

    .online-hero{
        border-radius:24px;
        padding:24px;
        color:#fff;
        background:
            radial-gradient(700px 280px at 88% 0%, rgba(34,211,238,.24), transparent 60%),
            linear-gradient(135deg,#020617,#075985 58%,#0f766e);
        box-shadow:0 18px 44px rgba(15,23,42,.18);
        margin-bottom:16px;
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
    }

    .online-hero h3{
        font-size:25px;
        font-weight:950;
        margin:0;
        letter-spacing:-.03em;
    }

    .online-hero p{
        margin:5px 0 0;
        opacity:.84;
        font-size:13px;
    }

    .online-card{
        background:#fff;
        border:1px solid #e5eaf1;
        border-radius:22px;
        box-shadow:0 12px 32px rgba(15,23,42,.065);
        overflow:hidden;
    }

    .online-card-head{
        padding:15px 18px;
        border-bottom:1px solid #e5eaf1;
        font-weight:950;
        color:#0f172a;
    }

    .online-card-body{
        padding:18px;
    }

    .form-label{
        font-size:12px;
        font-weight:850;
        color:#334155;
    }

    .form-control,
    .form-select{
        border-radius:13px;
        border-color:#dbe3ee;
        font-size:13px;
    }

    .btn-pill{
        border-radius:999px;
        font-size:13px;
        font-weight:850;
        padding:8px 16px;
    }

    .online-layout{
        display:grid;
        grid-template-columns:1fr 340px;
        gap:16px;
        align-items:start;
    }

    .tip-box{
        padding:14px;
        border-bottom:1px solid #e5eaf1;
    }

    .tip-box:last-child{
        border-bottom:0;
    }

    .tip-title{
        font-weight:950;
        color:#0f172a;
    }

    .tip-desc{
        color:#64748b;
        font-size:12px;
        margin-top:4px;
        line-height:1.5;
    }

    @media(max-width:1100px){
        .online-layout{
            grid-template-columns:1fr;
        }
    }

    @media(max-width:768px){
        .online-shell{
            padding:14px;
        }

        .online-hero{
            flex-direction:column;
            align-items:flex-start;
        }
    }
</style>

<div class="online-page">
    <div class="online-shell">

        <div class="online-hero">
            <div>
                <h3>Đơn xin làm online</h3>
                <p>Gửi đề xuất làm việc online để trưởng bộ phận hoặc sếp duyệt.</p>
            </div>

            <a href="{{ route('hr.attendance.my') }}" class="btn btn-light btn-pill">
                <i class="bi bi-arrow-left"></i> Quay lại chấm công
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm rounded-4">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger border-0 shadow-sm rounded-4">
                <b>Chưa gửi được đơn.</b>
                <ul class="mb-0 mt-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="online-layout">
            <div class="online-card">
                <div class="online-card-head">
                    <i class="bi bi-laptop"></i> Thông tin đơn làm online
                </div>

                <div class="online-card-body">
                    <form method="POST" action="#">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ngày bắt đầu</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ngày kết thúc</label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Hình thức</label>
                            <select name="online_type" class="form-select">
                                <option value="full_day">Làm online cả ngày</option>
                                <option value="morning">Làm online buổi sáng</option>
                                <option value="afternoon">Làm online buổi chiều</option>
                                <option value="custom">Khung giờ tùy chỉnh</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Lý do xin làm online</label>
                            <textarea name="reason"
                                      rows="5"
                                      class="form-control"
                                      placeholder="Ví dụ: cần xử lý công việc từ xa, đi công tác, việc cá nhân nhưng vẫn đảm bảo tiến độ..."
                                      required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Kế hoạch công việc trong thời gian online</label>
                            <textarea name="work_plan"
                                      rows="5"
                                      class="form-control"
                                      placeholder="Liệt kê các đầu việc sẽ thực hiện, deadline, cách báo cáo kết quả..."></textarea>
                        </div>

                        <div class="alert alert-warning rounded-4 small">
                            Trang này hiện là giao diện sẵn. Nếu hệ thống của bạn đã có controller lưu đơn làm online,
                            chỉ cần đổi `action="#"` thành route lưu đơn thật.
                        </div>

                        <div class="d-flex justify-content-end gap-2 flex-wrap">
                            <a href="{{ route('hr.attendance.my') }}" class="btn btn-outline-secondary btn-pill">
                                Hủy
                            </a>

                            <button type="submit" class="btn btn-success btn-pill px-5">
                                <i class="bi bi-send"></i> Gửi đơn
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="online-card">
                <div class="online-card-head">
                    <i class="bi bi-info-circle"></i> Lưu ý
                </div>

                <div class="tip-box">
                    <div class="tip-title">Ghi rõ lý do</div>
                    <div class="tip-desc">Sếp sẽ dễ duyệt hơn nếu bạn trình bày rõ vì sao cần làm online.</div>
                </div>

                <div class="tip-box">
                    <div class="tip-title">Có kế hoạch công việc</div>
                    <div class="tip-desc">Nên ghi rõ các đầu việc sẽ hoàn thành trong thời gian làm online.</div>
                </div>

                <div class="tip-box">
                    <div class="tip-title">Vẫn cần báo cáo kết quả</div>
                    <div class="tip-desc">Sau khi được duyệt, bạn nên cập nhật tiến độ hoặc báo cáo qua hệ thống công việc.</div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection