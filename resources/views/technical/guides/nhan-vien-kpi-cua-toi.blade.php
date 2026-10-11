@extends('technical.guides.layout')

@section('guide')

    <section class="tg-section" id="muc-dich">
        <h2 class="tg-section__title"><span class="num">1</span> Mục đích</h2>
        <p>
            Trang <strong>KPIs</strong> (<code>/ky-thuat/kpis</code>) cho bạn xem <strong>% KPI tháng của chính mình</strong>,
            tiền KPI và tổng lương dự kiến, cùng danh sách công trình được tính vào từng tiêu chí.
            Bạn chỉ xem được KPI của mình; số liệu do Trưởng phòng Kỹ thuật đánh giá.
        </p>
    </section>

    <section class="tg-section" id="cac-buoc">
        <h2 class="tg-section__title"><span class="num">2</span> Hướng dẫn từng bước</h2>

        @include('technical.guides.partials.step', [
            'num' => 1,
            'title' => 'Xem KPI tháng',
            'desc' => 'Menu <strong>Kỹ thuật → KPIs</strong>, chọn <strong>tháng</strong> rồi bấm <strong>Áp dụng bộ lọc</strong>. Bấm <strong>Xem chi tiết</strong> để thấy từng công trình đạt / không đạt ở tiêu chí nào.',
            'result' => 'Bạn biết % KPI của mình và lý do.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 2,
            'title' => 'Hiểu 5 tiêu chí',
            'desc' => 'Mỗi tiêu chí tính <strong>TH ÷ KH</strong> (Thực hiện ÷ Kế hoạch) rồi nhân trọng số:',
            'bullets' => [
                '<strong>Tiến độ (30%)</strong>: bàn giao đúng ngày cam kết hoàn thành.',
                '<strong>Chất lượng (25%)</strong>: nghiệm thu đạt ngay lần đầu (không bị “Trả lại”), chủ nhà không phàn nàn.',
                '<strong>Khảo sát &amp; vật tư (15%)</strong>: đủ hồ sơ khảo sát, bản vẽ sơ bộ, bảng khối lượng vật tư; lệch vật tư dưới 3%.',
                '<strong>An toàn HSE (15%)</strong>: không tai nạn, đeo dây an toàn, không làm hỏng mái, dọn vệ sinh, thu gom rác &amp; cáp thừa.',
                '<strong>EVN &amp; App (15%)</strong>: đã đóng điện thử lưới EVN (khi cần đấu nối) và cài App giám sát cho chủ nhà.',
            ],
        ])

        @include('technical.guides.partials.step', [
            'num' => 3,
            'title' => 'Tiền KPI được tính thế nào',
            'desc' => 'Lương thoả thuận chia 70% lương cố định + 30% quỹ KPI. Tiền KPI theo % đạt:',
            'bullets' => [
                'Dưới 75%: không nhận tiền KPI.',
                '75% đến dưới 90%: nhận 80% quỹ KPI.',
                '90% đến dưới 100%: nhận 100% quỹ KPI.',
                'Từ 100% trở lên: nhận đúng bằng % đạt (ví dụ 120% nhận 120% quỹ KPI).',
            ],
        ])
    </section>

    <section class="tg-section" id="luu-y">
        <h2 class="tg-section__title"><span class="num">3</span> Câu hỏi thường gặp</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'KPI của tôi báo “Chưa đủ dữ liệu”',
            'text' => 'Tháng đó chưa có số liệu cho một số tiêu chí — không phải bạn bị 0%. Báo Trưởng phòng Kỹ thuật kiểm tra bạn đã được phân công vào dự án trên /du-an chưa, hoặc nhập số liệu tháng.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Muốn KPI cao',
            'text' => 'Hoàn thành đúng ngày cam kết, làm đạt ngay lần đầu, tải đủ hồ sơ khảo sát, giữ an toàn &amp; vệ sinh công trình, đóng điện thử và cài App cho chủ nhà trước khi bàn giao.',
        ])
    </section>
@endsection
