@extends('technical.guides.layout')

@section('guide')

    <section class="tg-section" id="muc-dich">
        <h2 class="tg-section__title"><span class="num">1</span> Mục đích</h2>
        <p>
            Trang <strong>Tổng quan Kỹ thuật</strong> (<code>/ky-thuat</code>) là màn hình mở đầu ngày làm việc
            của nhân viên kỹ thuật. Nó gom số liệu trực tiếp từ ba nguồn thật — bước quy trình Công trình,
            Task nội bộ và lịch Bảo trì / Bảo hành — để bạn biết ngay hôm nay phải làm gì, việc nào đang trễ
            và việc nào chưa có báo cáo.
        </p>
    </section>

    <section class="tg-section" id="dieu-kien">
        <h2 class="tg-section__title"><span class="num">2</span> Điều kiện trước khi thao tác</h2>
        <ul>
            <li>Tài khoản của bạn thuộc phòng Kỹ thuật (có quyền vào module Kỹ thuật).</li>
            <li>Bạn đã được giao ít nhất một đầu việc: một bước quy trình công trình, một task, hoặc một lịch bảo trì.</li>
            <li>Nếu bạn là Trưởng phòng hoặc Admin, mở <code>/ky-thuat</code> sẽ tự chuyển sang trang tổng quan
                của vai trò đó — đây là hành vi đúng, không phải lỗi.</li>
        </ul>
    </section>

    <section class="tg-section" id="quy-trinh">
        <h2 class="tg-section__title"><span class="num">3</span> Quy trình tổng quan</h2>
        @include('technical.guides.partials.flow-diagram', ['steps' => [
            ['icon' => 'bi-speedometer2', 'label' => 'Mở Tổng quan', 'description' => 'Đầu giờ sáng'],
            ['icon' => 'bi-calendar-week', 'label' => 'Xem kế hoạch', 'description' => 'Việc của tuần'],
            ['icon' => 'bi-hammer', 'label' => 'Thực hiện', 'description' => 'Ngoài công trình', 'color' => 'accent'],
            ['icon' => 'bi-journal-text', 'label' => 'Viết báo cáo', 'description' => 'Cuối buổi / cuối ngày'],
            ['icon' => 'bi-send-check', 'label' => 'Gửi duyệt', 'description' => 'Quản lý xem', 'color' => 'ok'],
        ]])
    </section>

    <section class="tg-section" id="cac-buoc">
        <h2 class="tg-section__title"><span class="num">4</span> Hướng dẫn từng bước</h2>

        @include('technical.guides.partials.step', [
            'num' => 1,
            'title' => 'Mở trang Tổng quan Kỹ thuật',
            'desc' => 'Bấm <strong>Tổng quan</strong> ở nhóm KỸ THUẬT trong menu bên trái, hoặc mở thẳng <code>/ky-thuat</code>.',
            'shot' => 'images/guides/technical/staff-overview-01-stats.png',
            'alt' => 'Trang Tổng quan Kỹ thuật của nhân viên với bốn thẻ chỉ số và cụm nút đầu trang',
            'caption' => 'Số 1 là cụm bốn chỉ số, số 2 là hai nút thao tác nhanh ở góc phải.',
            'result' => 'Bạn thấy bốn thẻ chỉ số và danh sách đầu việc của riêng mình.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 2,
            'title' => 'Đọc bốn thẻ chỉ số',
            'desc' => 'Ba trong bốn thẻ là liên kết lọc: bấm vào thẻ sẽ lọc luôn danh sách bên dưới.',
            'bullets' => [
                '<strong>Việc hôm nay</strong> — đầu việc đến hạn trong ngày hôm nay.',
                '<strong>Việc đang thực hiện</strong> — đầu việc đã bắt đầu nhưng chưa xong (thẻ này chỉ hiển thị số, không lọc).',
                '<strong>Việc quá hạn</strong> — đã qua hạn mà chưa hoàn thành. Đây là trạng thái <em>suy ra từ hạn</em>, không phải một cột lưu trong cơ sở dữ liệu.',
                '<strong>Báo cáo chưa nộp</strong> — đầu việc chưa có báo cáo ngày tương ứng.',
            ],
            'result' => 'Danh sách phía dưới được lọc theo đúng thẻ bạn vừa bấm.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 3,
            'title' => 'Chuyển sang việc cần làm tiếp theo',
            'desc' => 'Cụm nút góc phải đầu trang đưa bạn tới hai màn hình dùng nhiều nhất.',
            'bullets' => [
                '<strong>Mở kế hoạch</strong> → <code>/ky-thuat/ke-hoach-tuan</code>, nơi bạn tự lập kế hoạch tuần.',
                '<strong>Viết báo cáo</strong> → <code>/ky-thuat/bao-cao-ngay/tao</code>, form báo cáo ngày.',
                '<strong>Cách sử dụng</strong> → chính trang hướng dẫn bạn đang đọc.',
            ],
            'shot' => 'images/guides/technical/staff-overview-02-list.png',
            'alt' => 'Danh sách đầu việc của nhân viên kỹ thuật với nhãn nguồn Công trình, Task và Bảo trì',
            'caption' => 'Mỗi dòng ghi rõ nguồn của đầu việc và hạn hoàn thành.',
            'result' => 'Bạn sang được màn hình kế hoạch hoặc màn hình báo cáo mà không cần tìm trong menu.',
        ])
    </section>

    <section class="tg-section" id="trang-thai">
        <h2 class="tg-section__title"><span class="num">5</span> Các trạng thái có thể gặp</h2>
        <p>Trạng thái của từng dòng kế hoạch trong hệ thống hiện có đúng sáu giá trị:</p>
        @include('technical.guides.partials.status-badge', ['items' => [
            ['label' => 'Dự kiến', 'tone' => 'secondary', 'text' => 'Việc đã nằm trong kế hoạch nhưng chưa bắt đầu.'],
            ['label' => 'Đang thực hiện', 'tone' => 'info', 'text' => 'Việc đã bắt đầu, chưa xong.'],
            ['label' => 'Hoàn thành', 'tone' => 'success', 'text' => 'Đã làm xong theo mục tiêu đặt ra.'],
            ['label' => 'Chưa hoàn thành', 'tone' => 'danger', 'text' => 'Hết ngày mà chưa xong — cần ghi lý do khi báo cáo.'],
            ['label' => 'Chuyển sang ngày sau', 'tone' => 'warning', 'text' => 'Đã được dời sang một ngày khác trong tuần.'],
            ['label' => 'Đã huỷ', 'tone' => 'secondary', 'text' => 'Việc không còn phải làm nữa.'],
        ]])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => '"Quá hạn" không phải một trạng thái lưu trong hệ thống',
            'text' => 'Thẻ <strong>Việc quá hạn</strong> được tính ngay lúc mở trang bằng cách so hạn hoàn thành với ngày hôm nay. Bạn sẽ không tìm thấy giá trị "Quá hạn" trong ô chọn trạng thái của bất kỳ form nào.',
        ])
    </section>

    <section class="tg-section" id="luu-y">
        <h2 class="tg-section__title"><span class="num">6</span> Lưu ý quan trọng</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Bạn chỉ thấy dữ liệu của chính mình',
            'text' => 'Máy chủ tự giới hạn phạm vi theo tài khoản đang đăng nhập. Thêm tham số người dùng khác vào URL cũng không làm hiện dữ liệu của người khác.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Bốn chỉ số là ảnh chụp tại thời điểm mở trang',
            'text' => 'Sau khi bạn cập nhật kế hoạch hoặc gửi báo cáo, hãy tải lại trang để số liệu đổi theo.',
        ])
    </section>

    <section class="tg-section" id="loi-thuong-gap">
        <h2 class="tg-section__title"><span class="num">7</span> Lỗi thường gặp và cách xử lý</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Mở /ky-thuat nhưng bị đưa sang màn hình khác',
            'text' => 'Trang tổng quan tự điều hướng theo vai trò: Admin sang Tổng quan Kỹ thuật của Admin, Trưởng phòng sang Tổng quan phòng. Nếu bạn là nhân viên mà vẫn bị chuyển, tài khoản của bạn đang mang quyền quản lý — hãy báo Admin kiểm tra lại vai trò.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Danh sách trống dù bạn biết mình có việc',
            'text' => 'Kiểm tra xem bạn có đang bật một bộ lọc (bấm nhầm vào thẻ chỉ số) hay không — bấm lại vào <strong>Tổng quan</strong> trên menu để bỏ lọc. Nếu vẫn trống, đầu việc chưa được phân công cho tài khoản của bạn ở module nguồn.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'danger',
            'title' => 'Nhận thông báo 403 "Bạn không thuộc phạm vi module Kỹ thuật"',
            'text' => 'Tài khoản chưa được xếp vào phòng Kỹ thuật. Đây là chặn ở phía máy chủ, không thể tự khắc phục bằng cách đổi URL — hãy liên hệ Admin.',
        ])
    </section>
@endsection
