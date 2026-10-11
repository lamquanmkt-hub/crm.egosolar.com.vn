@extends('technical.guides.layout')

@section('guide')

    <section class="tg-section" id="muc-dich">
        <h2 class="tg-section__title"><span class="num">1</span> Mục đích</h2>
        <p>
            Mỗi tháng Trưởng phòng Kỹ thuật cung cấp số liệu để hệ thống tính <strong>% KPI</strong> cho từng kỹ sư. Có hai cách,
            dùng được cùng lúc:
        </p>
        <ul>
            <li><strong>Tự động</strong>: điền mục <strong>“Đánh giá KPI kỹ thuật”</strong> ở bước <em>Nghiệm thu và bàn giao</em> của từng dự án trên <code>/du-an</code>.</li>
            <li><strong>Nhập tay</strong>: trang <strong>Nhập số liệu tháng</strong> (<code>/ky-thuat/kpis/nhap-lieu</code>) — nhập Kế hoạch / Thực hiện cho từng kỹ sư × tiêu chí. Số nhập tay luôn được ưu tiên hơn số tự động.</li>
        </ul>
    </section>

    <section class="tg-section" id="dieu-kien">
        <h2 class="tg-section__title"><span class="num">2</span> Điều kiện trước khi thao tác</h2>
        <ul>
            <li>Tài khoản Trưởng phòng Kỹ thuật, Ban giám đốc hoặc Admin.</li>
            <li>Muốn số liệu tự động: kỹ sư phải được <strong>phân công</strong> vào bước Khảo sát / Thi công / Nghiệm thu của dự án (hoặc là kỹ sư phụ trách chính), và dự án có <strong>ngày cam kết hoàn thành</strong> ở bước Ký hợp đồng.</li>
        </ul>
    </section>

    <section class="tg-section" id="quy-trinh">
        <h2 class="tg-section__title"><span class="num">3</span> KH và TH là gì?</h2>
        <p>
            <strong>KH = Kế hoạch</strong> (chỉ tiêu phải đạt) · <strong>TH = Thực hiện</strong> (số thực tế đạt được).
            Tỷ lệ đạt mỗi tiêu chí = <strong>TH ÷ KH</strong>; TH lớn hơn KH thì tiêu chí được vượt 100%.
            Thường <strong>KH = số hệ thống kỹ sư phụ trách trong tháng</strong>, <strong>TH = số hệ thống đạt chuẩn</strong> của tiêu chí đó.
        </p>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Tiêu chí</th><th>Trọng số</th><th>KH (ví dụ)</th><th>TH (ví dụ)</th><th>Tỷ lệ</th></tr></thead>
                <tbody>
                    <tr><td>Tiến độ lắp đặt</td><td>30%</td><td>4 hệ thống phải bàn giao</td><td>3 bàn giao đúng hạn</td><td>75%</td></tr>
                    <tr><td>Chất lượng thi công</td><td>25%</td><td>4 hệ thống nghiệm thu</td><td>4 đạt ngay lần đầu, không phàn nàn</td><td>100%</td></tr>
                    <tr><td>Khảo sát &amp; vật tư</td><td>15%</td><td>4 hệ thống</td><td>4 đủ hồ sơ khảo sát, lệch vật tư &lt; 3%</td><td>100%</td></tr>
                    <tr><td>An toàn HSE &amp; vệ sinh</td><td>15%</td><td>4 hệ thống</td><td>4 không vi phạm</td><td>100%</td></tr>
                    <tr><td>EVN &amp; cài App</td><td>15%</td><td>4 hệ thống</td><td>4 đã đóng điện thử, cài App</td><td>100%</td></tr>
                </tbody>
            </table>
        </div>
        <p class="mb-0">→ % KPI = 75%×30 + 100%×25 + 100%×15 + 100%×15 + 100%×15 = <strong>92,5%</strong> (cộng thêm điểm thưởng / trừ nếu có).</p>
    </section>

    <section class="tg-section" id="cac-buoc">
        <h2 class="tg-section__title"><span class="num">4</span> Hướng dẫn từng bước</h2>

        @include('technical.guides.partials.step', [
            'num' => 1,
            'title' => 'Đánh giá KPI ở bước Nghiệm thu của dự án (tự động)',
            'desc' => 'Mở dự án trên <code>/du-an</code> → bước <strong>Nghiệm thu và bàn giao</strong> → bảng <strong>“ĐÁNH GIÁ KPI KỸ THUẬT”</strong> → điền rồi bấm <strong>Lưu</strong>, trước khi Admin duyệt nghiệm thu.',
            'bullets' => [
                '<strong>Chất lượng</strong>: chọn “Chủ nhà không phàn nàn” hoặc “Có phàn nàn” (bắt buộc ghi nội dung). Dự án bị bấm <strong>“Trả lại”</strong> ở bước Thi công / Nghiệm thu tự tính là không đạt lần đầu.',
                '<strong>An toàn HSE</strong>: tick đủ 5 mục — không tai nạn, đeo dây an toàn &amp; bảo hộ, không làm hỏng mái, dọn vệ sinh 100%, thu gom rác &amp; cáp thừa. Thiếu một mục là không đạt.',
                '<strong>EVN &amp; App</strong>: tick “Đã đóng điện thử EVN” (bắt buộc khi bước Hồ sơ pháp lý có tick cần đấu nối) và “Đã cài App” (hoặc dự án đã có link Monitoring).',
                '<strong>Khảo sát &amp; vật tư</strong>: hệ thống tự kiểm tra hồ sơ khảo sát, bản vẽ sơ bộ, bảng khối lượng vật tư và tự tính % lệch vật tư (đề xuất bổ sung ÷ ban đầu). Có bảng quyết toán thì nhập % lệch vào ô tương ứng — số nhập được ưu tiên.',
            ],
            'result' => 'Khi dự án được duyệt nghiệm thu, số liệu tự vào KPI tháng đó của các kỹ sư được phân công. Cột Trạng thái của bảng hiện ngay Đạt / Chưa đủ / Chưa đánh giá.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 2,
            'title' => 'Nhập số liệu tháng (nhập tay)',
            'desc' => 'Bảng KPI → nút <strong>Nhập số liệu tháng</strong>. Chọn tháng ở góc phải.',
            'bullets' => [
                'Mỗi kỹ sư × tiêu chí có hai ô <strong>KH</strong> và <strong>TH</strong>. Nhập cả hai, KH phải lớn hơn 0.',
                'Dòng <strong>“Tự động”</strong> bên dưới gợi ý số lấy từ /du-an để đối chiếu.',
                'Để trống cả KH và TH rồi Lưu = xoá số đã nhập, tiêu chí quay về dùng số tự động.',
                'Bấm <strong>Lưu số liệu tháng …</strong> cuối trang.',
            ],
            'result' => 'Quay lại Bảng KPI: dòng “Thiếu” của kỹ sư biến mất và hiện % KPI.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 3,
            'title' => 'Cộng / trừ điểm KPI',
            'desc' => 'Cùng trang Nhập số liệu tháng, mục <strong>Cộng / trừ điểm KPI</strong>: chọn kỹ sư, nhập số điểm (dương = cộng, âm = trừ, tối đa ±100) và <strong>lý do</strong> (bắt buộc), rồi bấm <strong>Ghi nhận</strong>.',
            'bullets' => [
                '1 điểm = 1% KPI.',
                'Vi phạm nghiêm trọng (không đeo dây an toàn, bạo lực, lời lẽ thiếu chuẩn mực với chủ nhà) có thể trừ 10–20 điểm.',
                'Nhập nhầm thì bấm <strong>Xoá</strong> ở dòng điểm đó.',
            ],
            'result' => '% KPI của kỹ sư thay đổi đúng số điểm, lý do hiện dưới cột KPI đạt.',
        ])
    </section>

    <section class="tg-section" id="luu-y">
        <h2 class="tg-section__title"><span class="num">5</span> Lưu ý quan trọng</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Dự án tính vào tháng nào?',
            'text' => 'Chất lượng, Khảo sát &amp; vật tư, HSE, EVN &amp; App tính vào <strong>tháng dự án được duyệt nghiệm thu</strong>. Tiến độ tính theo ngày bàn giao so với ngày cam kết hoàn thành.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Kỹ sư không tự chấm',
            'text' => 'Kỹ sư được giao bước Nghiệm thu chỉ xem được bảng Đánh giá KPI, không sửa được. Chỉ Trưởng phòng Kỹ thuật / Admin đánh giá.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Bảng KPI báo “Chưa đủ dữ liệu”',
            'text' => 'Còn tiêu chí chưa có số. Nhập ở trang Nhập số liệu tháng, hoặc kiểm tra dự án của kỹ sư đã được phân công và duyệt nghiệm thu trong tháng chưa.',
        ])
    </section>
@endsection
