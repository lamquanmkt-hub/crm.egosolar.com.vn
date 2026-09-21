@extends('technical.guides.layout')

@section('guide')

    <section class="tg-section" id="muc-dich">
        <h2 class="tg-section__title"><span class="num">1</span> Mục đích</h2>
        <p>
            Hai màn hình theo dõi của Trưởng phòng: <strong>Tổng quan phòng Kỹ thuật</strong>
            (<code>/ky-thuat/quan-ly/tong-quan</code>) trả lời câu hỏi "tuần này ai chưa lập kế hoạch, việc nào
            quá hạn", còn <strong>Tổng kết tuần</strong> (<code>/ky-thuat/quan-ly/tong-ket-tuan</code>) đối chiếu
            <em>kế hoạch so với thực tế</em> của cả phòng, theo từng nhân viên và từng công trình.
        </p>
    </section>

    <section class="tg-section" id="dieu-kien">
        <h2 class="tg-section__title"><span class="num">2</span> Điều kiện trước khi thao tác</h2>
        <ul>
            <li>Bạn có quyền quản lý module Kỹ thuật.</li>
            <li>Nhân viên đã lập kế hoạch tuần — không có kế hoạch thì không có gì để đối chiếu.</li>
            <li>Nhân viên đã gửi báo cáo ngày — tỷ lệ nộp báo cáo được tính từ đó.</li>
        </ul>
    </section>

    <section class="tg-section" id="quy-trinh">
        <h2 class="tg-section__title"><span class="num">3</span> Quy trình tổng quan</h2>
        @include('technical.guides.partials.flow-diagram', ['steps' => [
            ['icon' => 'bi-calendar-week', 'label' => 'Nhân viên lập KH', 'description' => 'Đầu tuần'],
            ['icon' => 'bi-hammer', 'label' => 'Thực hiện', 'description' => 'Trong tuần', 'color' => 'accent'],
            ['icon' => 'bi-journal-text', 'label' => 'Báo cáo ngày', 'description' => 'Mỗi ngày'],
            ['icon' => 'bi-check2-square', 'label' => 'Duyệt', 'description' => 'Quản lý xác nhận', 'color' => 'ok'],
            ['icon' => 'bi-clipboard-data', 'label' => 'Tổng kết tuần', 'description' => 'Đối chiếu KH – TT'],
        ]])
    </section>

    <section class="tg-section" id="cac-buoc">
        <h2 class="tg-section__title"><span class="num">4</span> Hướng dẫn từng bước</h2>

        @include('technical.guides.partials.step', [
            'num' => 1,
            'title' => 'Mở Tổng quan phòng và đọc tám thẻ',
            'desc' => 'Menu <strong>Quản lý kỹ thuật → Tổng quan</strong>. Thanh tuần cho phép lùi/tiến từng tuần.',
            'bullets' => [
                '<strong>Nhân sự kỹ thuật</strong> — số người trong phạm vi quản lý của bạn.',
                '<strong>Chưa lập kế hoạch tuần</strong> — con số cần về 0 vào đầu tuần.',
                '<strong>Công việc kế hoạch</strong> và <strong>Hoàn thành</strong>.',
                '<strong>Quá hạn</strong> — tính từ hạn công việc so với hiện tại.',
                '<strong>Phát sinh</strong> — việc ngoài kế hoạch đã báo cáo.',
                '<strong>Báo cáo đã nộp</strong> và <strong>Báo cáo chưa nộp</strong>.',
            ],
            'shot' => 'images/guides/technical/manager-overview-01.png',
            'alt' => 'Trang Tổng quan phòng Kỹ thuật với tám thẻ chỉ số của tuần đang xem',
            'caption' => 'Số 1: thanh chọn tuần. Số 2: tám thẻ chỉ số toàn phòng.',
            'result' => 'Bạn nắm được bức tranh tuần chỉ trong một màn hình.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 2,
            'title' => 'Xử lý danh sách "chưa lập kế hoạch tuần"',
            'desc' => 'Ngay dưới cụm thẻ, hệ thống liệt kê đích danh nhân viên chưa lập kế hoạch, kèm liên kết <em>mở kế hoạch</em> đi thẳng vào trang chi tiết của người đó.',
            'result' => 'Bạn nhắc đúng người, hoặc tự giao việc cho họ ngay tại đó.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 3,
            'title' => 'Mở Tổng kết tuần',
            'desc' => 'Menu <strong>Tổng kết tuần</strong>, hoặc nút <em>Tổng kết tuần</em> ở đầu trang ma trận kế hoạch.',
            'bullets' => [
                'Hai chỉ số dẫn đầu: <strong>tỷ lệ hoàn thành</strong> và <strong>tỷ lệ nộp báo cáo</strong>.',
                'Tám thẻ: Công việc kế hoạch, Hoàn thành, Chưa hoàn thành, Chuyển ngày, Quá hạn, Phát sinh, Giờ dự kiến, Giờ thực tế.',
                'Bảng theo nhân viên và bảng theo công trình ở phía dưới.',
            ],
            'shot' => 'images/guides/technical/manager-weekly-02.png',
            'alt' => 'Trang Tổng kết tuần của phòng Kỹ thuật với tỷ lệ hoàn thành, tám thẻ chỉ số và bảng theo nhân viên',
            'caption' => 'Giờ dự kiến so với giờ thực tế cho thấy kế hoạch có sát thực tế hay không.',
            'result' => 'Bạn có số liệu để họp giao ban tuần mà không phải tự cộng tay.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 4,
            'title' => 'Đối chiếu với tab "Tổng hợp tuần" của trang Báo cáo',
            'desc' => 'Trang <code>/ky-thuat/bao-cao-ngay?tab=weekly-summary</code> có tám thẻ nghiêng về <em>báo cáo</em>: Tổng báo cáo đã nộp, Báo cáo chờ duyệt, Báo cáo đã duyệt, Báo cáo yêu cầu sửa, Công việc hoàn thành, Công việc chưa hoàn thành, Công việc phát sinh, Tổng giờ thực tế.',
            'result' => 'Hai màn hình bổ sung cho nhau: một nghiêng về kế hoạch, một nghiêng về báo cáo.',
        ])
    </section>

    <section class="tg-section" id="trang-thai">
        <h2 class="tg-section__title"><span class="num">5</span> Các trạng thái có thể gặp</h2>
        <p>Trạng thái tuần kế hoạch của nhân viên xuất hiện trên các bảng tổng hợp:</p>
        @include('technical.guides.partials.status-badge', ['items' => [
            ['label' => 'Đang lập', 'tone' => 'secondary', 'text' => 'Nhân viên chưa chốt kế hoạch tuần.'],
            ['label' => 'Đã hoàn tất', 'tone' => 'success', 'text' => 'Nhân viên đã chốt.'],
            ['label' => 'Đã được Trưởng phòng điều chỉnh', 'tone' => 'warning', 'text' => 'Quản lý đã can thiệp vào tuần này.'],
        ]])
        <p class="mt-3">Trạng thái báo cáo dùng trong các ô đếm:</p>
        @include('technical.guides.partials.status-badge', ['items' => [
            ['label' => 'Nháp', 'tone' => 'secondary', 'text' => 'Chưa gửi — không được tính vào "đã nộp".'],
            ['label' => 'Đã gửi · chờ duyệt', 'tone' => 'warning', 'text' => 'Tính vào "đã nộp" và vào ô "chờ duyệt".'],
            ['label' => 'Đã duyệt', 'tone' => 'success', 'text' => 'Tính vào "đã nộp" và vào ô "đã duyệt".'],
            ['label' => 'Yêu cầu sửa', 'tone' => 'danger', 'text' => 'Đang chờ nhân viên cập nhật lại.'],
        ]])
    </section>

    <section class="tg-section" id="luu-y">
        <h2 class="tg-section__title"><span class="num">6</span> Lưu ý quan trọng</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => '"Quá hạn" được suy ra, không phải trạng thái lưu',
            'text' => 'Ô <strong>Quá hạn</strong> được tính khi mở trang bằng cách so hạn công việc với thời điểm hiện tại. Không có giá trị "Quá hạn" trong ô chọn trạng thái của bất kỳ form nào.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Giờ dự kiến và giờ thực tế đến từ hai nguồn khác nhau',
            'text' => 'Giờ dự kiến lấy từ ô "thời gian dự kiến" trong kế hoạch; giờ thực tế lấy từ báo cáo ngày. Chênh lệch lớn kéo dài là dấu hiệu kế hoạch lập chưa sát.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Số liệu phụ thuộc vào việc nhân viên có báo cáo hay không',
            'text' => 'Nhân viên làm việc nhưng không gửi báo cáo sẽ làm tỷ lệ hoàn thành trông thấp hơn thực tế. Hãy nhìn kèm ô "Báo cáo chưa nộp".',
        ])
    </section>

    <section class="tg-section" id="loi-thuong-gap">
        <h2 class="tg-section__title"><span class="num">7</span> Lỗi thường gặp và cách xử lý</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Tỷ lệ hoàn thành hiển thị "N/A"',
            'text' => 'Mẫu số bằng 0 — tuần đó chưa có công việc kế hoạch nào (hoặc chưa có việc đến hạn để tính tỷ lệ nộp báo cáo). Hệ thống cố tình hiển thị <strong>N/A</strong> thay vì 0% để tránh hiểu nhầm là "làm mà không xong việc nào".',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Bảng theo nhân viên trống',
            'text' => 'Không có nhân viên nào thuộc phạm vi quản lý có dữ liệu trong tuần đang xem. Kiểm tra lại tuần đang chọn trên thanh tuần.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Số trên Tổng kết tuần khác số trên tab Tổng hợp tuần',
            'text' => 'Hai màn hình đếm hai thứ khác nhau: một bên đếm <em>công việc kế hoạch</em>, một bên đếm <em>báo cáo</em>. Đây không phải lỗi dữ liệu — hãy so đúng ô cùng tên.',
        ])
    </section>
@endsection
