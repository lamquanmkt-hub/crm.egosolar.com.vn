@extends('technical.guides.layout')

@section('guide')

    <section class="tg-section" id="muc-dich">
        <h2 class="tg-section__title"><span class="num">1</span> Mục đích</h2>
        <p>
            Trang <strong>KPIs</strong> (<code>/ky-thuat/kpis</code>) là bảng KPI chính thức của phòng Kỹ thuật,
            chấm <strong>theo tháng</strong> cho từng nhân sự. Bài này giúp bạn đọc đúng năm tiêu chí, hiểu trọng số
            và — quan trọng nhất — biết số nào <em>chấm tay</em> và số nào <em>lấy từ dữ liệu công trình</em>,
            để không diễn giải sai khi dùng bảng này ra quyết định.
        </p>
    </section>

    <section class="tg-section" id="dieu-kien">
        <h2 class="tg-section__title"><span class="num">2</span> Điều kiện trước khi thao tác</h2>
        <ul>
            <li>Tài khoản Admin / Ban giám đốc (mục KPIs nằm trong lối vào chính của vai trò này).</li>
            <li>Nhân sự Kỹ thuật đã được khai báo trong hệ thống — bảng hiển thị <em>toàn bộ</em> nhân sự Kỹ thuật,
                kể cả người chưa có hồ sơ KPI trong tháng.</li>
            <li>Cấu hình KPI có <strong>tổng trọng số đúng 100%</strong>; nếu lệch, hệ thống từ chối chấm và yêu cầu
                sửa cấu hình trước.</li>
        </ul>
    </section>

    <section class="tg-section" id="quy-trinh">
        <h2 class="tg-section__title"><span class="num">3</span> Quy trình tổng quan</h2>
        @include('technical.guides.partials.flow-diagram', ['steps' => [
            ['icon' => 'bi-calendar-week', 'label' => 'Kế hoạch / Giao việc', 'description' => 'Đầu tuần'],
            ['icon' => 'bi-hammer', 'label' => 'Thực hiện', 'description' => 'Tại công trình', 'color' => 'accent'],
            ['icon' => 'bi-journal-text', 'label' => 'Báo cáo', 'description' => 'Mỗi ngày'],
            ['icon' => 'bi-check2-square', 'label' => 'Duyệt', 'description' => 'Xác nhận', 'color' => 'ok'],
            ['icon' => 'bi-clipboard-data', 'label' => 'Tổng hợp', 'description' => 'Theo tuần'],
            ['icon' => 'bi-bar-chart-line', 'label' => 'Chấm KPI', 'description' => 'Theo tháng', 'color' => 'ok'],
        ]])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Đọc kỹ: KPI không được tính tự động từ báo cáo ngày',
            'text' => 'Sơ đồ trên là <em>trình tự nghiệp vụ</em>, không phải đường đi của dữ liệu. Bảng KPI tại <code>/ky-thuat/kpis</code> chấm theo tháng dựa trên hồ sơ KPI được nhập / lưu riêng và dữ liệu công trình liên kết. Số báo cáo ngày và tổng hợp tuần là <strong>căn cứ tham khảo để chấm</strong>, không tự động đổ thành điểm KPI.',
        ])
    </section>

    <section class="tg-section" id="cac-buoc">
        <h2 class="tg-section__title"><span class="num">4</span> Hướng dẫn từng bước</h2>

        @include('technical.guides.partials.step', [
            'num' => 1,
            'title' => 'Mở trang KPIs và chọn tháng',
            'desc' => 'Menu <strong>Kỹ thuật → KPIs</strong>. Bộ lọc gồm <strong>tháng</strong> (định dạng năm-tháng), <strong>nhân sự</strong> và <strong>trạng thái</strong> hồ sơ.',
            'shot' => 'images/guides/technical/kpi-overview-01.png',
            'alt' => 'Trang KPIs của phòng Kỹ thuật với cụm thống kê và bộ lọc theo tháng, nhân sự, trạng thái',
            'caption' => 'Số 1: bộ lọc tháng. Số 2: dòng tóm tắt số kỹ sư, số hồ sơ KPI và số người chưa chấm.',
            'result' => 'Bảng KPI hiển thị đúng tháng bạn chọn.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 2,
            'title' => 'Hiểu năm tiêu chí mặc định và trọng số',
            'desc' => 'Bộ tiêu chí mặc định của hệ thống gồm năm mục, tổng trọng số 100%:',
            'bullets' => [
                '<strong>Tiến độ hoàn thành lắp đặt hệ thống — 30%</strong>. Công thức: Thực hiện / Kế hoạch, trong đó TH là số công trình hoàn thành đúng hạn, KH là tổng công trình đến hạn trong kỳ. Nguồn: tiến độ / nghiệm thu công trình.',
                '<strong>Chất lượng thi công &amp; thẩm mỹ — 25%</strong>. Công thức: TH / KH, TH là số công trình nghiệm thu đạt ngay lần đầu, KH là tổng công trình nghiệm thu. Nguồn: biên bản nghiệm thu / phản hồi khách hàng.',
                '<strong>Khảo sát kỹ thuật &amp; khối lượng — 15%</strong>. Tính theo bậc hao hụt vật tư: 0% = 120%; trên 0 đến 2% = 100%; trên 2 đến 4% = 85%; trên 4 đến 6% = 70%; trên 6% = 0%. Nguồn: vật tư xuất kho trừ vật tư hoàn trả nguyên vẹn.',
                '<strong>An toàn lao động (HSE) &amp; vệ sinh — 15%</strong>. Công thức: TH / KH, TH là số công trình đạt checklist HSE; vi phạm nghiêm trọng có thể trừ thêm 10–20 điểm KPI. Nguồn: checklist HSE / biên bản sự cố.',
                '<strong>Hỗ trợ thủ tục EVN &amp; cài đặt App — 15%</strong>. Công thức: TH / KH, TH là số công trình hoàn tất hạng mục EVN/App cần thực hiện, KH là số công trình có yêu cầu. Nguồn: nghiệm thu / bàn giao / cấu hình App.',
            ],
            'shot' => 'images/guides/technical/kpi-table-02.png',
            'alt' => 'Bảng KPI theo nhân sự với các thành phần điểm của từng tiêu chí và tổng phần trăm KPI',
            'caption' => 'Mỗi ô thành phần hiển thị điểm và trọng số của đúng tiêu chí tương ứng.',
            'result' => 'Bạn đọc được vì sao một người đạt tổng KPI như vậy.',
        ])

        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Năm tiêu chí trên là bộ MẶC ĐỊNH',
            'text' => 'Nếu công ty đã khai báo bộ tiêu chí riêng trong cấu hình KPI kỹ thuật, bảng sẽ dùng bộ đó thay cho bộ mặc định — kèm tên, trọng số, kiểu tính và nguồn dữ liệu riêng. Hãy đọc cột ghi chú của từng tiêu chí trên chính trang KPIs để biết bộ đang áp dụng.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 3,
            'title' => 'Phân biệt "chưa chấm" với "điểm bằng 0"',
            'desc' => 'Bảng liệt kê cả người chưa có hồ sơ KPI trong tháng. Những dòng đó hiển thị <strong>Chưa chấm</strong> và dấu gạch ở các ô thành phần — không phải điểm 0.',
            'bullets' => [
                'Dòng tóm tắt đầu trang ghi rõ: số kỹ sư · số hồ sơ KPI · số người <em>chưa chấm</em>.',
                'Bộ lọc trạng thái có lựa chọn <strong>Chưa chấm</strong> để soát nhanh người còn thiếu.',
            ],
            'result' => 'Bạn không nhầm "chưa chấm" thành "làm việc kém".',
        ])

        @include('technical.guides.partials.step', [
            'num' => 4,
            'title' => 'Đối chiếu với dữ liệu vận hành trước khi kết luận',
            'desc' => 'Trước khi dùng KPI để đánh giá, hãy mở kèm hai màn hình vận hành: <strong>Tổng kết tuần</strong> của phòng và tab <strong>Tổng hợp tuần</strong> ở trang Báo cáo.',
            'bullets' => [
                'Tổng kết tuần cho tỷ lệ hoàn thành và tỷ lệ nộp báo cáo theo tuần.',
                'Tab Tổng hợp tuần cho số báo cáo đã nộp / chờ duyệt / đã duyệt / yêu cầu sửa và tổng giờ thực tế.',
                'Hai nguồn này là <em>tham khảo</em>; con số chính thức để đánh giá vẫn là bảng KPI theo tháng.',
            ],
            'result' => 'Kết luận đánh giá dựa trên cả KPI chính thức lẫn dữ liệu vận hành.',
        ])
    </section>

    <section class="tg-section" id="trang-thai">
        <h2 class="tg-section__title"><span class="num">5</span> Các trạng thái có thể gặp</h2>
        <p>Trạng thái của một hồ sơ KPI trong tháng:</p>
        @include('technical.guides.partials.status-badge', ['items' => [
            ['label' => 'Chưa chấm', 'tone' => 'secondary', 'text' => 'Nhân sự chưa có hồ sơ KPI trong tháng đang xem. Các ô thành phần hiển thị dấu gạch, không phải 0.'],
            ['label' => 'Nháp', 'tone' => 'warning', 'text' => 'Hồ sơ KPI đã được tạo nhưng chưa chốt.'],
            ['label' => 'Đã duyệt', 'tone' => 'success', 'text' => 'Hồ sơ KPI đã được chốt cho tháng đó.'],
        ]])
    </section>

    <section class="tg-section" id="luu-y">
        <h2 class="tg-section__title"><span class="num">6</span> Lưu ý quan trọng</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'KPI ở đây theo THÁNG, không theo tuần',
            'text' => 'Bộ lọc chính là tháng. Các màn hình theo tuần (Tổng kết tuần, Tổng hợp tuần) là dữ liệu vận hành, không phải kỳ KPI.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Tổng trọng số phải đúng 100%',
            'text' => 'Nếu cấu hình bị lệch, hệ thống không âm thầm quay về bộ mặc định mà báo lỗi kèm tổng trọng số hiện tại và yêu cầu chỉnh về 100% trước khi chấm. Đây là chủ ý để tránh chấm trên một bộ trọng số sai.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Sửa KPI của tháng cũ dùng đúng bộ trọng số của tháng đó',
            'text' => 'Khi mở lại một hồ sơ KPI đã lưu, hệ thống dựng lại bộ tiêu chí từ chính hồ sơ đó, nên lịch sử không bị lệch theo cấu hình mới của các tháng sau.',
        ])
    </section>

    <section class="tg-section" id="loi-thuong-gap">
        <h2 class="tg-section__title"><span class="num">7</span> Lỗi thường gặp và cách xử lý</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Báo "Tổng trọng số KPI hiện tại là …%"',
            'text' => 'Cấu hình KPI kỹ thuật đang lệch khỏi 100%. Vào Cấu hình KPI, chỉnh trọng số các tiêu chí về đúng tổng 100% rồi chấm lại.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Báo "Chưa có tiêu chí KPI đang hoạt động."',
            'text' => 'Toàn bộ tiêu chí trong cấu hình đã bị tắt. Bật lại ít nhất một tiêu chí, hoặc xoá hết cấu hình riêng để hệ thống quay về bộ năm tiêu chí mặc định.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Bảng trống trong tháng mới',
            'text' => 'Tháng mới chưa ai được chấm. Bảng vẫn phải liệt kê đủ nhân sự Kỹ thuật ở trạng thái <em>Chưa chấm</em>; nếu hoàn toàn không có dòng nào, nhân sự Kỹ thuật chưa được khai báo trong hệ thống HR.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'danger',
            'title' => 'Đừng dùng KPI tháng để kết luận về một tuần cụ thể',
            'text' => 'Kỳ KPI là tháng. Muốn nhận xét một tuần, hãy dùng Tổng kết tuần và tab Tổng hợp tuần — và nói rõ đó là số vận hành tham khảo.',
        ])
    </section>
@endsection
