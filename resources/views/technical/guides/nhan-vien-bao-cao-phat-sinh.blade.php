@extends('technical.guides.layout')

@section('guide')

    <section class="tg-section" id="muc-dich">
        <h2 class="tg-section__title"><span class="num">1</span> Mục đích</h2>
        <p>
            Thực tế luôn có việc không nằm trong kế hoạch: khách gọi gấp, sự cố tại công trình, hỗ trợ đội khác.
            Luồng <strong>Báo cáo việc phát sinh</strong> dùng để ghi nhận đúng những việc đó, kèm
            <strong>lý do phát sinh bắt buộc</strong> — nhờ vậy việc ngoài kế hoạch không bị "hợp thức hoá"
            thành việc đã lập từ đầu tuần, và số liệu tổng hợp tuần vẫn tách bạch được hai loại.
        </p>
    </section>

    <section class="tg-section" id="dieu-kien">
        <h2 class="tg-section__title"><span class="num">2</span> Điều kiện trước khi thao tác</h2>
        <ul>
            <li>Việc bạn định báo cáo <strong>không</strong> có trong kế hoạch tuần của bạn. Nếu có rồi, hãy dùng
                luồng báo cáo theo kế hoạch bình thường.</li>
            <li>Bạn chuẩn bị được một lý do phát sinh rõ ràng, <strong>tối thiểu 5 ký tự</strong> — đây là ràng buộc
                thật của hệ thống, không phải khuyến nghị.</li>
            <li>Tài khoản thuộc phòng Kỹ thuật.</li>
        </ul>
    </section>

    <section class="tg-section" id="quy-trinh">
        <h2 class="tg-section__title"><span class="num">3</span> Quy trình tổng quan</h2>
        @include('technical.guides.partials.flow-diagram', ['steps' => [
            ['icon' => 'bi-exclamation-circle', 'label' => 'Việc phát sinh', 'description' => 'Ngoài kế hoạch', 'color' => 'warn'],
            ['icon' => 'bi-plus-circle', 'label' => 'Mở form phát sinh', 'description' => 'Nút riêng'],
            ['icon' => 'bi-chat-left-text', 'label' => 'Nhập lý do', 'description' => 'Bắt buộc', 'color' => 'warn'],
            ['icon' => 'bi-send', 'label' => 'Gửi duyệt', 'description' => 'Chờ quản lý'],
            ['icon' => 'bi-check2-circle', 'label' => 'Đã duyệt', 'description' => 'Vào tổng hợp tuần', 'color' => 'ok'],
        ]])
    </section>

    <section class="tg-section" id="cac-buoc">
        <h2 class="tg-section__title"><span class="num">4</span> Hướng dẫn từng bước</h2>

        @include('technical.guides.partials.step', [
            'num' => 1,
            'title' => 'Bấm đúng nút "Báo cáo việc phát sinh"',
            'desc' => 'Trên trang <strong>Báo cáo</strong> (<code>/ky-thuat/bao-cao-ngay</code>) có hai nút khác nhau ở góc phải: <em>Viết báo cáo</em> (theo kế hoạch) và <em>Báo cáo việc phát sinh</em>. Nút phát sinh mở <code>/ky-thuat/bao-cao-ngay/tao?mode=phat-sinh</code>.',
            'shot' => 'images/guides/technical/staff-unplanned-01-button.png',
            'alt' => 'Hai nút Viết báo cáo và Báo cáo việc phát sinh ở góc phải trang Báo cáo',
            'caption' => 'Số 1: Viết báo cáo theo kế hoạch. Số 2: Báo cáo việc phát sinh.',
            'result' => 'Form mở ở chế độ phát sinh, đã bật sẵn ô lý do phát sinh.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 2,
            'title' => 'Điền nội dung việc đã làm',
            'desc' => 'Ở chế độ phát sinh, bạn tự mô tả công việc thay vì chọn từ kế hoạch.',
            'bullets' => [
                'Ngày báo cáo — mặc định là hôm nay.',
                'Nội dung công việc đã thực hiện.',
                'Kết quả đạt được và (nếu còn dở) lý do chưa hoàn thành.',
                'Tệp minh chứng nếu có.',
            ],
            'shot' => 'images/guides/technical/staff-unplanned-02-form.png',
            'alt' => 'Form báo cáo ngày với ô đánh dấu Công việc phát sinh đã được tích sẵn và ô Lý do phát sinh',
            'caption' => 'Nút "Báo cáo việc phát sinh" mở form đã TÍCH SẴN ô "Công việc phát sinh (ngoài kế hoạch tuần)".',
            'result' => 'Báo cáo được đánh dấu là việc ngoài kế hoạch.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 3,
            'title' => 'Nhập lý do phát sinh — bắt buộc',
            'desc' => 'Viết ngắn nhưng đủ để quản lý hiểu vì sao việc này chen vào lịch: ai yêu cầu, sự cố gì, mức độ gấp ra sao.',
            'bullets' => [
                'Tối thiểu 5 ký tự, tối đa 2000 ký tự.',
                'Bỏ trống sẽ bị chặn với thông báo <em>"Công việc phát sinh bắt buộc nhập lý do phát sinh."</em>',
                'Viết quá ngắn sẽ bị chặn với thông báo <em>"Lý do phát sinh phải có ít nhất 5 ký tự."</em>',
            ],
            'shot' => 'images/guides/technical/staff-unplanned-03-error.png',
            'alt' => 'Thông báo lỗi bắt buộc nhập lý do phát sinh khi gửi form để trống',
            'caption' => 'Hệ thống chặn ngay tại máy chủ, không chỉ nhắc ở trình duyệt.',
            'result' => 'Khi lý do hợp lệ, báo cáo lưu lại và bạn gửi duyệt như báo cáo thường.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 4,
            'title' => 'Gửi duyệt',
            'desc' => 'Từ đây trở đi, báo cáo phát sinh đi đúng cùng một vòng duyệt như báo cáo theo kế hoạch: Nháp → Đã gửi · chờ duyệt → Đã duyệt (hoặc Yêu cầu sửa).',
            'result' => 'Việc phát sinh được tính vào ô <strong>Công việc phát sinh</strong> của bảng Tổng hợp tuần.',
        ])
    </section>

    <section class="tg-section" id="trang-thai">
        <h2 class="tg-section__title"><span class="num">5</span> Các trạng thái có thể gặp</h2>
        @include('technical.guides.partials.status-badge', ['items' => [
            ['label' => 'Nháp', 'tone' => 'secondary', 'text' => 'Đã lưu, chưa gửi. Còn sửa được.'],
            ['label' => 'Đã gửi · chờ duyệt', 'tone' => 'warning', 'text' => 'Đang chờ quản lý xử lý.'],
            ['label' => 'Đã duyệt', 'tone' => 'success', 'text' => 'Đã được công nhận.'],
            ['label' => 'Yêu cầu sửa', 'tone' => 'danger', 'text' => 'Bị trả lại kèm ý kiến — thường vì lý do phát sinh chưa thuyết phục.'],
        ]])
    </section>

    <section class="tg-section" id="luu-y">
        <h2 class="tg-section__title"><span class="num">6</span> Lưu ý quan trọng</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Có chọn đầu việc trong kế hoạch thì không còn là việc phát sinh',
            'text' => 'Nếu bạn chọn một dòng kế hoạch trong form, hệ thống tự bỏ đánh dấu "phát sinh" — vì việc đó vốn đã có trong kế hoạch.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Việc phát sinh vẫn được tính vào tổng hợp tuần',
            'text' => 'Bảng Tổng hợp tuần có riêng một ô <strong>Công việc phát sinh</strong>. Báo cáo trung thực giúp phản ánh đúng khối lượng bạn đã gánh.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Đừng dùng luồng phát sinh để né việc lập kế hoạch',
            'text' => 'Việc biết trước vẫn nên đưa vào kế hoạch tuần. Luồng phát sinh dành cho việc thực sự không lường trước được.',
        ])
    </section>

    <section class="tg-section" id="loi-thuong-gap">
        <h2 class="tg-section__title"><span class="num">7</span> Lỗi thường gặp và cách xử lý</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => '"Công việc phát sinh bắt buộc nhập lý do phát sinh."',
            'text' => 'Bạn để trống ô lý do. Điền lý do rồi gửi lại — nội dung các ô khác không bị mất.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => '"Lý do phát sinh phải có ít nhất 5 ký tự."',
            'text' => 'Lý do kiểu "ok", "x" bị chặn. Hãy viết một câu ngắn nêu rõ nguyên nhân.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Điền lý do nhưng hệ thống không ghi nhận là việc phát sinh',
            'text' => 'Ô <strong>Lý do phát sinh</strong> luôn có mặt trên form, nhưng chỉ có hiệu lực khi ô đánh dấu <strong>Công việc phát sinh (ngoài kế hoạch tuần)</strong> được tích. Bấm nút <strong>Báo cáo việc phát sinh</strong> (đường dẫn có <code>?mode=phat-sinh</code>) để form mở ra đã tích sẵn ô này.',
        ])
    </section>
@endsection
