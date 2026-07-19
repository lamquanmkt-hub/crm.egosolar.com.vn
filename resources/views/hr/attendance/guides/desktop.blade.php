@extends('layouts.app')

@section('content')
<style>
    .guide-page{
        min-height:100vh;
        background:#f4f7fb;
        padding-bottom:48px;
        font-size:13px;
    }

    .guide-shell{
        padding:22px;
        max-width:1100px;
        margin:0 auto;
    }

    .guide-hero{
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

    .guide-hero h3{
        font-size:25px;
        font-weight:950;
        margin:0;
        letter-spacing:-.03em;
    }

    .guide-hero p{
        margin:5px 0 0;
        opacity:.84;
        font-size:13px;
    }

    .btn-pill{
        border-radius:999px;
        font-size:13px;
        font-weight:850;
        padding:8px 16px;
    }

    .guide-card{
        background:#fff;
        border:1px solid #e5eaf1;
        border-radius:22px;
        box-shadow:0 12px 32px rgba(15,23,42,.065);
        overflow:hidden;
    }

    .guide-step{
        display:grid;
        grid-template-columns:52px 1fr;
        gap:14px;
        padding:18px;
        border-bottom:1px solid #e5eaf1;
    }

    .guide-step:last-child{
        border-bottom:0;
    }

    .guide-num{
        width:46px;
        height:46px;
        border-radius:16px;
        background:#e0f2fe;
        color:#0369a1;
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:18px;
        font-weight:950;
    }

    .guide-title{
        font-size:16px;
        font-weight:950;
        color:#0f172a;
        margin-bottom:5px;
    }

    .guide-desc{
        color:#475569;
        line-height:1.65;
    }

    .guide-img{
        margin-top:12px;
        border:1px dashed #cbd5e1;
        background:#f8fafc;
        border-radius:18px;
        padding:20px;
        text-align:center;
        color:#64748b;
    }

    .guide-note{
        margin-top:16px;
        border-radius:18px;
        background:#eff6ff;
        border:1px solid #bfdbfe;
        color:#1e40af;
        padding:14px 16px;
        font-weight:750;
    }

    @media(max-width:768px){
        .guide-shell{
            padding:14px;
        }

        .guide-hero{
            flex-direction:column;
            align-items:flex-start;
        }

        .guide-step{
            grid-template-columns:42px 1fr;
            padding:14px;
        }

        .guide-num{
            width:38px;
            height:38px;
            border-radius:14px;
        }
    }
</style>

<div class="guide-page">
    <div class="guide-shell">

        <div class="guide-hero">
            <div>
                <h3>Hướng dẫn chấm công trên máy tính</h3>
                <p>Áp dụng cho Chrome, Edge, Cốc Cốc trên laptop hoặc máy bàn.</p>
            </div>

            <a href="{{ route('hr.attendance.my') }}" class="btn btn-light btn-pill">
                <i class="bi bi-arrow-left"></i> Quay lại chấm công
            </a>
        </div>

        <div class="guide-card">
            <div class="guide-step">
                <div class="guide-num">1</div>
                <div>
                    <div class="guide-title">Mở CRM trên trình duyệt</div>
                    <div class="guide-desc">
                        Đăng nhập CRM, vào mục <b>Nhân sự → Chấm công của tôi</b>.
                    </div>
                    <div class="guide-img">
                        <i class="bi bi-display fs-1 d-block mb-2"></i>
                        Ảnh minh họa: trang chấm công trên máy tính.
                    </div>
                </div>
            </div>

            <div class="guide-step">
                <div class="guide-num">2</div>
                <div>
                    <div class="guide-title">Bấm Check-in hoặc Check-out</div>
                    <div class="guide-desc">
                        Khi trình duyệt hỏi quyền vị trí, chọn <b>Cho phép</b>.
                        Máy tính thường lấy vị trí theo Wi-Fi hoặc mạng đang sử dụng.
                    </div>
                    <div class="guide-img">
                        <i class="bi bi-geo-alt fs-1 d-block mb-2"></i>
                        Ảnh minh họa: popup xin quyền vị trí trên Chrome/Edge.
                    </div>
                </div>
            </div>

            <div class="guide-step">
                <div class="guide-num">3</div>
                <div>
                    <div class="guide-title">Nếu vị trí bị chặn</div>
                    <div class="guide-desc">
                        Bấm biểu tượng <b>ổ khóa</b> bên trái thanh địa chỉ →
                        chọn <b>Site settings / Cài đặt trang web</b> →
                        mục <b>Location / Vị trí</b> →
                        đổi thành <b>Allow / Cho phép</b>.
                    </div>
                    <div class="guide-img">
                        <i class="bi bi-shield-check fs-1 d-block mb-2"></i>
                        Ảnh minh họa: cài đặt quyền vị trí trên máy tính.
                    </div>
                </div>
            </div>

            <div class="guide-step">
                <div class="guide-num">4</div>
                <div>
                    <div class="guide-title">Tải lại trang sau khi bật quyền</div>
                    <div class="guide-desc">
                        Sau khi đổi quyền vị trí, nhấn <b>F5</b> hoặc bấm nút tải lại trang,
                        sau đó bấm chấm công lại.
                    </div>
                    <div class="guide-img">
                        <i class="bi bi-arrow-clockwise fs-1 d-block mb-2"></i>
                        Ảnh minh họa: tải lại trang sau khi bật quyền vị trí.
                    </div>
                </div>
            </div>

            <div class="guide-step">
                <div class="guide-num">5</div>
                <div>
                    <div class="guide-title">Nếu máy tính không lấy được vị trí</div>
                    <div class="guide-desc">
                        Hãy thử dùng điện thoại để chấm công, hoặc kiểm tra Wi-Fi/mạng.
                        Một số máy bàn không có GPS nên vị trí có thể không chính xác bằng điện thoại.
                    </div>

                    <div class="guide-note">
                        Gợi ý: Chấm công bằng điện thoại thường ổn định hơn vì có GPS thật.
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection