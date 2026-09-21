@extends('technical.guides.layout')

@section('guide')

    <section class="tg-section" id="muc-dich">
        <h2 class="tg-section__title"><span class="num">1</span> Mục đích</h2>
        <p>
            Trang <strong>Kế hoạch tuần của tôi</strong> (<code>/ky-thuat/ke-hoach-tuan</code>) là nơi nhân viên
            kỹ thuật <em>tự lập</em> kế hoạch làm việc cho từng ngày trong tuần: làm gì, vào buổi nào, trong bao lâu,
            mục tiêu cần đạt là gì. Kế hoạch này chính là danh sách mà bạn sẽ chọn lại khi viết báo cáo ngày,
            và là dữ liệu mà Trưởng phòng nhìn thấy trên ma trận kế hoạch cả phòng.
        </p>
    </section>

    <section class="tg-section" id="dieu-kien">
        <h2 class="tg-section__title"><span class="num">2</span> Điều kiện trước khi thao tác</h2>
        <ul>
            <li>Bạn đăng nhập bằng tài khoản kỹ thuật của chính mình — trang chỉ thao tác trên kế hoạch của bạn.</li>
            <li>Biết trước tuần cần lập (thanh chọn tuần cho phép lùi/tiến từng tuần).</li>
            <li>Nếu muốn gắn việc vào một đầu việc có sẵn (bước công trình, task, lịch bảo trì), đầu việc đó phải
                đang được giao cho bạn.</li>
            <li>Không cần quyền gì thêm: mọi nhân viên kỹ thuật đều lập được kế hoạch của mình.</li>
        </ul>
    </section>

    <section class="tg-section" id="quy-trinh">
        <h2 class="tg-section__title"><span class="num">3</span> Quy trình tổng quan</h2>
        @include('technical.guides.partials.flow-diagram', ['steps' => [
            ['icon' => 'bi-calendar-range', 'label' => 'Chọn tuần', 'description' => 'Tuần này / tuần sau'],
            ['icon' => 'bi-plus-circle', 'label' => 'Thêm công việc', 'description' => 'Từng ngày'],
            ['icon' => 'bi-save', 'label' => 'Lưu nháp', 'description' => 'Còn sửa được', 'color' => 'warn'],
            ['icon' => 'bi-check2-circle', 'label' => 'Hoàn tất tuần', 'description' => 'Chốt kế hoạch', 'color' => 'ok'],
            ['icon' => 'bi-grid-3x3', 'label' => 'Trưởng phòng xem', 'description' => 'Trên ma trận', 'color' => 'accent'],
        ]])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Trưởng phòng có thể điều chỉnh sau khi bạn hoàn tất',
            'text' => 'Khi Trưởng phòng sửa hoặc giao thêm việc, trạng thái tuần đổi thành <strong>Đã được Trưởng phòng điều chỉnh</strong> và mọi thay đổi đều được ghi lịch sử kèm lý do.',
        ])
    </section>

    <section class="tg-section" id="cac-buoc">
        <h2 class="tg-section__title"><span class="num">4</span> Hướng dẫn từng bước</h2>

        @include('technical.guides.partials.step', [
            'num' => 1,
            'title' => 'Mở trang và chọn đúng tuần',
            'desc' => 'Menu <strong>Kỹ thuật → Kế hoạch</strong>. Thanh tuần ở đầu trang có ba nút: tuần trước, <strong>Tuần này</strong>, tuần sau, kèm khoảng ngày và trạng thái tuần.',
            'shot' => 'images/guides/technical/staff-plan-01-week.png',
            'alt' => 'Thanh chọn tuần trên trang Kế hoạch tuần của nhân viên, hiển thị khoảng ngày và trạng thái tuần',
            'caption' => 'Số 1: chọn tuần. Số 2: trạng thái tuần. Số 3: cụm nút Sao chép tuần trước / Lưu nháp / Hoàn tất.',
            'result' => 'Lưới bảy ngày của đúng tuần bạn chọn hiện ra bên dưới.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 2,
            'title' => 'Mở khung "Thêm công việc vào kế hoạch"',
            'desc' => 'Bấm nút <strong>Tạo kế hoạch của tôi</strong> ở góc phải đầu trang, hoặc bấm thẳng vào dòng tiêu đề <strong>Thêm công việc vào kế hoạch</strong> để mở khung nhập.',
            'shot' => 'images/guides/technical/staff-plan-02-add-form.png',
            'alt' => 'Khung Thêm công việc vào kế hoạch đang mở với các ô Ngày thực hiện, Buổi, Giờ bắt đầu, Giờ kết thúc và Nội dung công việc',
            'caption' => 'Khung nhập mở sẵn khi bạn vào bằng nút "Tạo kế hoạch của tôi".',
            'result' => 'Form thêm việc mở ra ngay trên trang, không phải chuyển màn hình.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 3,
            'title' => 'Điền thông tin công việc',
            'desc' => 'Chỉ <strong>Nội dung công việc</strong> là bắt buộc; các ô còn lại giúp kế hoạch sát thực tế hơn.',
            'bullets' => [
                '<strong>Ngày thực hiện</strong> — chọn trong bảy ngày của tuần đang xem.',
                '<strong>Buổi</strong> — Sáng / Chiều / Cả ngày, dùng để xếp việc vào đúng ô trên ma trận của Trưởng phòng.',
                '<strong>Giờ bắt đầu / Giờ kết thúc</strong> — nếu điền cả hai, hệ thống dùng để phát hiện trùng khung giờ.',
                '<strong>Nội dung công việc</strong> — bắt buộc, tối đa 255 ký tự.',
                '<strong>Mục tiêu cần đạt</strong> — viết rõ "xong đến đâu thì coi là hoàn thành".',
                '<strong>Thời gian dự kiến (phút)</strong> — dùng để cảnh báo quá tải trong ngày.',
                '<strong>Ưu tiên</strong>, <strong>Ghi chú</strong> — tuỳ chọn.',
            ],
            'result' => 'Việc mới xuất hiện ngay trong ô ngày tương ứng của lưới tuần.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 4,
            'title' => 'Dùng ba nút thao tác nhanh trên thanh tuần',
            'desc' => 'Ba nút này áp dụng cho cả tuần đang xem.',
            'bullets' => [
                '<strong>Sao chép tuần trước</strong> — nhân bản toàn bộ đầu việc của tuần liền trước sang tuần này, rất nhanh khi công việc lặp lại theo chu kỳ.',
                '<strong>Lưu nháp</strong> — giữ tuần ở trạng thái <em>Đang lập</em>, bạn còn sửa thoải mái.',
                '<strong>Hoàn tất kế hoạch tuần</strong> — chốt tuần. Nút này <em>bị khoá</em> khi phần kiểm tra bên dưới còn lỗi.',
            ],
            'shot' => 'images/guides/technical/staff-plan-03-toolbar.png',
            'alt' => 'Cụm ba nút Sao chép tuần trước, Lưu nháp và Hoàn tất kế hoạch tuần trên thanh tuần',
            'caption' => 'Nút "Hoàn tất kế hoạch tuần" chuyển sang màu xanh khi không còn lỗi chặn.',
            'result' => 'Trạng thái tuần đổi tương ứng: Đang lập → Đã hoàn tất.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 5,
            'title' => 'Đọc khối kiểm tra kế hoạch trước khi hoàn tất',
            'desc' => 'Ngay dưới thanh tuần, hệ thống liệt kê <strong>lỗi</strong> (chặn hoàn tất) và <strong>cảnh báo</strong> (chỉ nhắc, vẫn hoàn tất được).',
            'bullets' => [
                'Lỗi: tuần chưa có công việc nào.',
                'Lỗi: một ngày chưa có kế hoạch và cũng chưa được đánh dấu nghỉ / chờ phân công.',
                'Lỗi: tổng thời lượng một ngày vượt trần cho phép.',
                'Cảnh báo: một ngày vượt giờ làm việc chuẩn (quá tải).',
                'Cảnh báo: hai việc trong cùng ngày trùng khung giờ.',
                'Cảnh báo: việc được xếp sau hạn của đầu việc nguồn.',
                'Cảnh báo: cùng một nội dung công việc xuất hiện ở nhiều ngày trong tuần.',
            ],
            'shot' => 'images/guides/technical/staff-plan-04-checks.png',
            'alt' => 'Khối kiểm tra kế hoạch tuần liệt kê danh sách lỗi và cảnh báo',
            'caption' => 'Lỗi phải xử lý hết thì nút Hoàn tất mới bấm được; cảnh báo chỉ để bạn cân nhắc.',
            'result' => 'Hết lỗi → bấm được <strong>Hoàn tất kế hoạch tuần</strong>.',
        ])
    </section>

    <section class="tg-section" id="trang-thai">
        <h2 class="tg-section__title"><span class="num">5</span> Các trạng thái có thể gặp</h2>
        <p>Trạng thái của cả tuần kế hoạch:</p>
        @include('technical.guides.partials.status-badge', ['items' => [
            ['label' => 'Đang lập', 'tone' => 'secondary', 'text' => 'Tuần còn là bản nháp, bạn tự do thêm / sửa / xoá.'],
            ['label' => 'Đã hoàn tất', 'tone' => 'success', 'text' => 'Bạn đã chốt kế hoạch tuần này.'],
            ['label' => 'Đã được Trưởng phòng điều chỉnh', 'tone' => 'warning', 'text' => 'Quản lý đã sửa hoặc giao thêm việc vào tuần của bạn — hãy xem lại phần thay đổi.'],
        ]])
        <p class="mt-3">Trạng thái của từng dòng công việc:</p>
        @include('technical.guides.partials.status-badge', ['items' => [
            ['label' => 'Dự kiến', 'tone' => 'secondary', 'text' => 'Chưa bắt đầu.'],
            ['label' => 'Đang thực hiện', 'tone' => 'info', 'text' => 'Đang làm.'],
            ['label' => 'Hoàn thành', 'tone' => 'success', 'text' => 'Đã xong.'],
            ['label' => 'Chưa hoàn thành', 'tone' => 'danger', 'text' => 'Hết thời hạn nhưng chưa xong.'],
            ['label' => 'Chuyển sang ngày sau', 'tone' => 'warning', 'text' => 'Đã dời sang ngày khác.'],
            ['label' => 'Đã huỷ', 'tone' => 'secondary', 'text' => 'Không còn phải làm.'],
        ]])
    </section>

    <section class="tg-section" id="luu-y">
        <h2 class="tg-section__title"><span class="num">6</span> Lưu ý quan trọng</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Hoàn tất không có nghĩa là khoá cứng',
            'text' => 'Tuần "Đã hoàn tất" vẫn xem lại được và Trưởng phòng vẫn điều chỉnh được. Hoàn tất là tín hiệu "kế hoạch của tôi đã sẵn sàng", không phải một thao tác không thể quay lui.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Điền giờ bắt đầu và giờ kết thúc để được cảnh báo trùng lịch',
            'text' => 'Hệ thống chỉ phát hiện được trùng khung giờ khi cả hai đầu việc đều có giờ. Nếu bỏ trống, bạn sẽ không nhận được cảnh báo.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Kế hoạch là nguồn của báo cáo',
            'text' => 'Khi viết báo cáo ngày, bạn chọn đúng dòng kế hoạch đã lập. Lập kế hoạch càng rõ, báo cáo càng nhanh.',
        ])
    </section>

    <section class="tg-section" id="loi-thuong-gap">
        <h2 class="tg-section__title"><span class="num">7</span> Lỗi thường gặp và cách xử lý</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Nút "Hoàn tất kế hoạch tuần" bị mờ, không bấm được',
            'text' => 'Khối kiểm tra phía trên còn ít nhất một <strong>lỗi</strong>. Thường gặp nhất là một ngày trong tuần chưa có việc và cũng chưa được đánh dấu nghỉ / chờ phân công. Xử lý hết lỗi, nút sẽ tự bật lại.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Báo "Nội dung công việc" không được bỏ trống',
            'text' => 'Ô <strong>Nội dung công việc</strong> là bắt buộc và tối đa 255 ký tự. Mô tả dài hãy đưa vào ô <em>Mục tiêu cần đạt</em> hoặc <em>Ghi chú</em>.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Cảnh báo "vượt trần thời lượng" trong ngày',
            'text' => 'Tổng thời gian dự kiến của một ngày vượt trần cho phép thì đây là <strong>lỗi chặn</strong>, không phải cảnh báo. Hãy chia bớt việc sang ngày khác hoặc điều chỉnh số phút dự kiến cho sát thực tế.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'danger',
            'title' => 'Không sửa được một dòng việc do Trưởng phòng giao',
            'text' => 'Những dòng do quản lý giao xuống được đánh dấu riêng. Nếu cần đổi, hãy báo Trưởng phòng dùng chức năng <em>Điều chỉnh</em> — mọi thay đổi đều bắt buộc kèm lý do và được ghi lịch sử.',
        ])
    </section>
@endsection
