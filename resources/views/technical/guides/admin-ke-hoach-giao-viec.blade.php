@extends('technical.guides.layout')

@section('guide')

    <section class="tg-section" id="muc-dich">
        <h2 class="tg-section__title"><span class="num">1</span> Mục đích</h2>
        <p>
            Trang <strong>Kế hoạch &amp; Giao việc</strong> của Admin (<code>/ky-thuat/dashboard/ke-hoach</code>)
            cho Ban giám đốc nhìn toàn bộ kế hoạch phòng Kỹ thuật và — theo nghiệp vụ hiện hành —
            <strong>tạo kế hoạch, giao việc như Trưởng phòng</strong>. Điểm quan trọng: Admin không có luồng ghi
            riêng; các nút thao tác đưa bạn về đúng hai ngăn kéo của trang điều phối
            <code>/ky-thuat/quan-ly/ke-hoach</code>.
        </p>
    </section>

    <section class="tg-section" id="dieu-kien">
        <h2 class="tg-section__title"><span class="num">2</span> Điều kiện trước khi thao tác</h2>
        <ul>
            <li>Tài khoản Admin / Ban giám đốc (hoặc có quyền xem dashboard Kỹ thuật).</li>
            <li>Đã có nhân sự Kỹ thuật trong hệ thống — Admin nhìn thấy toàn đội, không bị thu hẹp phạm vi.</li>
            <li>Chuẩn bị sẵn lý do cho lần ghi: mọi thao tác giao việc / điều chỉnh đều bắt buộc lý do.</li>
        </ul>
    </section>

    <section class="tg-section" id="quy-trinh">
        <h2 class="tg-section__title"><span class="num">3</span> Quy trình tổng quan</h2>
        @include('technical.guides.partials.flow-diagram', ['steps' => [
            ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'description' => 'Nhìn toàn phòng'],
            ['icon' => 'bi-calendar-week', 'label' => 'Kế hoạch & Giao việc', 'description' => 'Trang của Admin'],
            ['icon' => 'bi-box-arrow-up-right', 'label' => 'Sang trang điều phối', 'description' => 'Ngăn kéo thật', 'color' => 'accent'],
            ['icon' => 'bi-chat-left-text', 'label' => 'Nhập lý do', 'description' => 'Bắt buộc', 'color' => 'warn'],
            ['icon' => 'bi-check2-circle', 'label' => 'Lưu', 'description' => 'Ghi lịch sử', 'color' => 'ok'],
        ]])
    </section>

    <section class="tg-section" id="cac-buoc">
        <h2 class="tg-section__title"><span class="num">4</span> Hướng dẫn từng bước</h2>

        @include('technical.guides.partials.step', [
            'num' => 1,
            'title' => 'Mở trang Kế hoạch & Giao việc của Admin',
            'desc' => 'Menu <strong>Kỹ thuật → Kế hoạch &amp; Giao việc</strong>. Trang này liệt kê kế hoạch theo nhân viên với bộ lọc riêng.',
            'shot' => 'images/guides/technical/admin-plans-01.png',
            'alt' => 'Trang Kế hoạch và Giao việc của Admin với danh sách kế hoạch theo nhân viên',
            'caption' => 'Số 1: thanh chọn tuần. Số 2: cụm nút Tạo kế hoạch / Giao việc.',
            'result' => 'Bạn thấy toàn bộ kế hoạch của phòng trong tuần đang chọn.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 2,
            'title' => 'Bấm "Tạo kế hoạch" hoặc "Giao việc"',
            'desc' => 'Trang Admin cố ý <em>không chứa form ghi</em> — hai nút là liên kết GET dẫn sang trang điều phối và mở sẵn đúng ngăn kéo (<code>?open=create</code> hoặc <code>?open=assign</code>).',
            'bullets' => [
                '<strong>Tạo kế hoạch</strong> — ngăn kéo nhiều dòng, có hai kiểu lưu: <em>Lưu nháp</em> và <em>Lưu và giao kế hoạch</em>.',
                '<strong>Giao việc</strong> — ngăn kéo một dòng, giao ngay.',
            ],
            'shot' => 'images/guides/technical/admin-plans-02-cta.png',
            'alt' => 'Cụm nút Cách sử dụng, Giao việc và Tạo kế hoạch ở góc phải trang Kế hoạch và Giao việc của Admin',
            'caption' => 'Nút "Cách sử dụng" đặt ngoài cùng bên trái để không lấn át hai nút nghiệp vụ.',
            'result' => 'Ngăn kéo mở ra trên trang <code>/ky-thuat/quan-ly/ke-hoach</code>.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 3,
            'title' => 'Thao tác giống hệt Trưởng phòng',
            'desc' => 'Từ đây, toàn bộ các bước điền dòng, chọn đầu việc nguồn, nhập lý do và xử lý cảnh báo trùng lịch / quá tải giống hoàn toàn bài hướng dẫn của Trưởng phòng.',
            'bullets' => [
                'Bấm thẳng vào ô ngày trong ma trận để mở ngăn kéo với nhân viên và ngày đã điền sẵn.',
                'Cảnh báo mới sẽ <strong>chặn lưu</strong> cho tới khi bạn tích ô xác nhận.',
                'Form "Giao thêm việc" ở trang chi tiết nhân viên vẫn là warning-only — cảnh báo hiện ra nhưng không chặn.',
            ],
            'result' => 'Dữ liệu ghi qua đúng một lối ghi dùng chung, lịch sử thống nhất với thao tác của Trưởng phòng.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 4,
            'title' => 'Theo dõi kết quả trên Tổng quan Admin',
            'desc' => 'Trang <code>/ky-thuat/dashboard</code> tổng hợp kết quả toàn phòng theo tuần hoặc theo tháng. Đường dẫn cũ <code>/ky-thuat/dashboard/bao-cao</code> nay chuyển hướng sang trang báo cáo dùng chung với tab Tổng hợp tuần.',
            'result' => 'Bạn theo dõi được tác động của việc giao việc lên số liệu chung.',
        ])
    </section>

    <section class="tg-section" id="trang-thai">
        <h2 class="tg-section__title"><span class="num">5</span> Các trạng thái có thể gặp</h2>
        @include('technical.guides.partials.status-badge', ['items' => [
            ['label' => 'Đang lập', 'tone' => 'secondary', 'text' => 'Kế hoạch tuần của nhân viên còn là nháp.'],
            ['label' => 'Đã hoàn tất', 'tone' => 'success', 'text' => 'Nhân viên đã chốt kế hoạch tuần.'],
            ['label' => 'Đã được Trưởng phòng điều chỉnh', 'tone' => 'warning', 'text' => 'Có quản lý (kể cả Admin) đã can thiệp vào tuần này.'],
        ]])
        <p class="mt-3">Trạng thái từng dòng công việc: Dự kiến · Đang thực hiện · Hoàn thành · Chưa hoàn thành · Chuyển sang ngày sau · Đã huỷ.</p>
    </section>

    <section class="tg-section" id="luu-y">
        <h2 class="tg-section__title"><span class="num">6</span> Lưu ý quan trọng</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Không có luồng ghi thứ hai cho Admin',
            'text' => 'Mọi thao tác ghi kế hoạch đều đi qua trang điều phối và cùng một lối ghi ở máy chủ. Nhờ vậy dữ liệu, cảnh báo và lịch sử của Admin và Trưởng phòng hoàn toàn nhất quán.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Admin nhìn thấy toàn đội',
            'text' => 'Phạm vi nhân sự của Admin không bị thu hẹp theo nhóm quản lý, khác với Trưởng phòng.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Giao việc nhiều sẽ đổi trạng thái tuần của nhân viên',
            'text' => 'Ngay khi bạn ghi vào tuần của một nhân viên, tuần đó chuyển sang <em>Đã được Trưởng phòng điều chỉnh</em> — nhân viên sẽ thấy tín hiệu cần xem lại.',
        ])
    </section>

    <section class="tg-section" id="loi-thuong-gap">
        <h2 class="tg-section__title"><span class="num">7</span> Lỗi thường gặp và cách xử lý</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Bấm "Tạo kế hoạch" nhưng bị chuyển sang URL khác',
            'text' => 'Đúng như thiết kế: trang Admin không chứa form ghi, nút là liên kết sang trang điều phối kèm <code>?open=create</code>. Bạn vẫn ở trong module Kỹ thuật.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Mở /ky-thuat/dashboard/bao-cao thì bị chuyển trang',
            'text' => 'Đường dẫn cũ chỉ còn chuyển hướng một chiều sang <code>/ky-thuat/bao-cao-ngay?tab=weekly-summary</code>. Hãy cập nhật dấu trang của bạn.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Ngăn kéo hiện cảnh báo và không lưu',
            'text' => 'Có cảnh báo trùng khung giờ hoặc quá tải mới phát sinh. Chỉnh giờ, hoặc tích ô xác nhận rồi lưu lại.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'danger',
            'title' => '403 khi nhân viên hoặc Trưởng phòng mở URL dashboard của Admin',
            'text' => 'Các route <code>/ky-thuat/dashboard/*</code> yêu cầu quyền xem dashboard Kỹ thuật. Hệ thống trả 403 thẳng, không chuyển hướng — đây là hành vi đúng.',
        ])
    </section>
@endsection
