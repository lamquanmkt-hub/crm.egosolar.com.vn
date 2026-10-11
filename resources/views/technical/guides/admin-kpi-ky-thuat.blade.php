@extends('technical.guides.layout')

@section('guide')

    <section class="tg-section" id="muc-dich">
        <h2 class="tg-section__title"><span class="num">1</span> Mục đích</h2>
        <p>
            Trang <strong>Bảng KPI và lương hiệu suất</strong> (<code>/ky-thuat/kpis</code>) tính <strong>% KPI theo tháng</strong>
            cho từng kỹ sư và từ đó ra <strong>tiền KPI</strong> và <strong>tổng thu nhập dự kiến</strong>.
            Bài này dành cho Admin / Ban giám đốc: cài đặt công thức, nhập lương thoả thuận, đọc bảng KPI và hiểu vì sao
            một dòng báo <em>“Chưa đủ dữ liệu”</em>.
        </p>
    </section>

    <section class="tg-section" id="dieu-kien">
        <h2 class="tg-section__title"><span class="num">2</span> Điều kiện trước khi thao tác</h2>
        <ul>
            <li>Tài khoản Admin / Ban giám đốc. Riêng <strong>lương thoả thuận</strong> thì Admin, HR và Kế toán đều nhập được.</li>
            <li>Đã có <strong>phiên bản Cài đặt KPI được duyệt</strong> cho kỳ đang xem (trọng số, tỷ lệ lương cố định / quỹ KPI, bậc thưởng).</li>
            <li>Kỹ sư đã được phân công vào dự án ở <code>/du-an</code> — hoặc Trưởng phòng Kỹ thuật đã nhập số liệu tháng.</li>
        </ul>
    </section>

    <section class="tg-section" id="quy-trinh">
        <h2 class="tg-section__title"><span class="num">3</span> Quy trình tổng quan</h2>
        @include('technical.guides.partials.flow-diagram', ['steps' => [
            ['icon' => 'bi-gear', 'label' => 'Cài đặt KPI', 'description' => 'Admin duyệt phiên bản'],
            ['icon' => 'bi-cash-coin', 'label' => 'Lương thoả thuận', 'description' => 'Admin / HR / Kế toán'],
            ['icon' => 'bi-buildings', 'label' => 'Dự án /du-an', 'description' => 'Tự lấy số liệu', 'color' => 'accent'],
            ['icon' => 'bi-pencil-square', 'label' => 'Nhập số liệu tháng', 'description' => 'Trưởng phòng KT'],
            ['icon' => 'bi-bar-chart-line', 'label' => 'Bảng KPI', 'description' => '% KPI & tiền KPI', 'color' => 'ok'],
        ]])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Số liệu đến từ đâu?',
            'text' => 'Mỗi tiêu chí lấy số theo thứ tự: <strong>số Trưởng phòng nhập tay</strong> ở trang Nhập số liệu tháng (luôn ưu tiên) → <strong>tự động từ quy trình dự án /du-an</strong> (công trình được duyệt nghiệm thu trong tháng). Không có cả hai thì tiêu chí đó báo “Thiếu”.',
        ])
    </section>

    <section class="tg-section" id="cac-buoc">
        <h2 class="tg-section__title"><span class="num">4</span> Hướng dẫn từng bước</h2>

        @include('technical.guides.partials.step', [
            'num' => 1,
            'title' => 'Cài đặt công thức KPI và phê duyệt',
            'desc' => 'Bảng KPI → nút <strong>Cài đặt KPI</strong> (<code>/ky-thuat/kpis/cai-dat</code>). Sửa ở form cấu hình, bấm <strong>Lưu thành bản nháp mới</strong> (không ghi đè phiên bản cũ), rồi ở bản nháp đó bấm <strong>Phê duyệt áp dụng</strong>. Chỉ phiên bản đã duyệt mới có hiệu lực.',
            'bullets' => [
                '<strong>Trọng số 5 tiêu chí</strong> (mặc định): Tiến độ 30% · Chất lượng 25% · Khảo sát &amp; vật tư 15% · An toàn HSE 15% · EVN &amp; App 15%. Tiêu chí 6 (bảo hành) chưa tính điểm.',
                '<strong>Tỷ lệ lương</strong>: lương cố định / quỹ KPI, mặc định <strong>70% / 30%</strong>.',
                '<strong>Bậc tiền KPI</strong>: dưới 75% không nhận · 75% đến dưới 90% nhận 80% · 90% đến dưới 100% nhận 100% · từ 100% trở lên nhận đúng bằng % đạt.',
                'Mục Hạn mức vượt 100%: tick <strong>Cho phép KPI vượt 100%</strong> và để <strong>trống ô “Trần tối đa khi vượt (%)”</strong> = vượt bao nhiêu tính bấy nhiêu (120% nhận 120%).',
            ],
            'result' => 'Bảng KPI tính theo đúng phiên bản vừa duyệt.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 2,
            'title' => 'Nhập lương thoả thuận của từng kỹ sư',
            'desc' => 'Trong trang Cài đặt KPI, mục <strong>“Lương thoả thuận của từng kỹ sư”</strong>: chọn <strong>Áp dụng từ tháng</strong>, nhập lương cho người cần thay đổi rồi bấm <strong>Lưu lương thoả thuận</strong>.',
            'bullets' => [
                'Mức lương giữ nguyên cho các tháng sau cho tới khi nhập mức mới — không phải nhập lại mỗi tháng.',
                'Chưa nhập ở đây thì hệ thống dùng lương đã có trong phiếu lương KPI trước đó, hoặc lương chính thức trong hồ sơ nhân viên.',
                'Lương thoả thuận = lương cố định (70%) + quỹ KPI (30%). Ví dụ 15.000.000 đ → cố định 10.500.000 đ, quỹ KPI 4.500.000 đ.',
            ],
            'result' => 'Cột <strong>Lương cơ bản</strong> và <strong>Mức lương KPI</strong> trên bảng hiện số, dòng không còn báo “Thiếu: Lương thoả thuận”.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 3,
            'title' => 'Đọc bảng KPI của tháng',
            'desc' => 'Chọn <strong>Chế độ kỳ</strong> (tháng / quý), <strong>tháng</strong>, phòng ban, nhân viên rồi bấm <strong>Áp dụng bộ lọc</strong>.',
            'bullets' => [
                '<strong>KPI đạt (%)</strong> = Σ (tỷ lệ đạt từng tiêu chí × trọng số) + điểm cộng/trừ (1 điểm = 1%).',
                'Tỷ lệ đạt mỗi tiêu chí = <strong>TH ÷ KH</strong> (Thực hiện ÷ Kế hoạch); TH vượt KH thì tiêu chí được vượt 100%.',
                '<strong>Tiền KPI</strong> = quỹ KPI × tỷ lệ nhận theo bậc; <strong>Tổng lương dự kiến</strong> = lương cố định + tiền KPI.',
                'Bấm <strong>Xem chi tiết</strong> để thấy từng công trình được tính vào tiêu chí nào, đạt hay không.',
            ],
            'result' => 'Bạn biết mỗi kỹ sư được bao nhiêu % KPI và vì sao.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 4,
            'title' => 'Xử lý dòng “Chưa đủ dữ liệu — Thiếu: …”',
            'desc' => 'Hệ thống <strong>không tính % KPI</strong> khi còn tiêu chí có trọng số chưa có số — để không trả lương sai. Dòng “Thiếu” ghi rõ còn thiếu gì:',
            'bullets' => [
                '<strong>Lương thoả thuận</strong> → nhập ở bước 2.',
                '<strong>Tên tiêu chí</strong> (Tiến độ, Chất lượng…) → Trưởng phòng Kỹ thuật nhập ở <strong>Nhập số liệu tháng</strong>, hoặc chờ công trình của kỹ sư được duyệt nghiệm thu trên /du-an.',
            ],
            'result' => 'Khi đủ số liệu, dòng hiện % KPI, tiền KPI và tổng lương dự kiến.',
        ])
    </section>

    <section class="tg-section" id="trang-thai">
        <h2 class="tg-section__title"><span class="num">5</span> Các trạng thái có thể gặp</h2>
        @include('technical.guides.partials.status-badge', ['items' => [
            ['label' => 'Chưa đủ dữ liệu', 'tone' => 'warning', 'text' => 'Còn thiếu lương thoả thuận hoặc số liệu của ít nhất một tiêu chí. Chưa tính tiền — không phải KPI 0%.'],
            ['label' => 'Chưa tính', 'tone' => 'secondary', 'text' => 'Chưa lập phiếu lương KPI cho kỳ này.'],
            ['label' => 'Đã duyệt', 'tone' => 'success', 'text' => 'Phiếu lương đã chốt: giữ nguyên lương và % KPI đã duyệt, không tính lại khi số liệu thay đổi.'],
        ]])
    </section>

    <section class="tg-section" id="luu-y">
        <h2 class="tg-section__title"><span class="num">6</span> Lưu ý quan trọng</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Đổi công thức không làm thay đổi tháng đã chốt',
            'text' => 'Mỗi tháng dùng phiên bản Cài đặt KPI có hiệu lực của tháng đó. Phiếu lương đã duyệt giữ nguyên số đã chốt.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Cộng / trừ điểm',
            'text' => 'Trưởng phòng Kỹ thuật cộng điểm thưởng hoặc trừ điểm vi phạm ở trang Nhập số liệu tháng, bắt buộc ghi lý do. Vi phạm nghiêm trọng (không đeo dây an toàn, bạo lực, lời lẽ thiếu chuẩn mực với chủ nhà) có thể trừ 10–20 điểm.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Kỹ sư không tự chấm KPI của mình',
            'text' => 'Mục “Đánh giá KPI kỹ thuật” ở bước Nghiệm thu của dự án chỉ Trưởng phòng Kỹ thuật / Admin được điền. Kỹ thuật viên chỉ xem được KPI của chính mình, không vào được trang Cài đặt và Nhập số liệu.',
        ])
    </section>

    <section class="tg-section" id="loi-thuong-gap">
        <h2 class="tg-section__title"><span class="num">7</span> Câu hỏi thường gặp</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Cả bảng đều “Chưa đủ dữ liệu”',
            'text' => 'Tháng đó chưa có công trình nào được duyệt nghiệm thu với kỹ sư được phân công, và Trưởng phòng chưa nhập số liệu tháng. Nhập số liệu tháng là cách nhanh nhất; về lâu dài hãy phân công kỹ sư vào các bước của dự án trên /du-an.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Vượt 100% nhưng tiền KPI vẫn chỉ 100%',
            'text' => 'Phiên bản Cài đặt KPI đang áp dụng chưa tick “Cho phép KPI vượt 100%” hoặc đang đặt “Trần tối đa khi vượt (%)”. Sửa ở form cấu hình, tick cho phép vượt, để trống ô trần, Lưu thành bản nháp mới rồi Phê duyệt áp dụng.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Đã sửa công thức nhưng bảng không đổi',
            'text' => 'Phiên bản mới chưa được bấm “Phê duyệt áp dụng”, hoặc tháng đang xem đã có phiếu lương được duyệt.',
        ])
    </section>
@endsection
