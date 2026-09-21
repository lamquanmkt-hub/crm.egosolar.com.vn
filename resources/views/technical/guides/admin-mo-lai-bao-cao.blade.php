@extends('technical.guides.layout')

@section('guide')

    @include('technical.guides.partials.note', [
        'tone' => 'warning',
        'title' => 'Đọc trước: phiên bản hiện tại KHÔNG hiện nút "Mở lại" trên màn hình Admin',
        'text' => 'Quyền mở lại báo cáo đã duyệt <strong>chỉ thuộc về Admin / Giám đốc</strong> ở phía máy chủ và bắt buộc kèm lý do. Nhưng màn hình chi tiết báo cáo của Admin đang được đặt ở chế độ <strong>CHỈ XEM</strong> theo chủ trương "Admin không duyệt từng báo cáo", nên cụm nút xử lý (kể cả nút Mở lại) bị ẩn với tài khoản Admin. Bài này mô tả đúng hiện trạng đó và cách xử lý trong thực tế.',
    ])

    <section class="tg-section" id="muc-dich">
        <h2 class="tg-section__title"><span class="num">1</span> Mục đích</h2>
        <p>
            Báo cáo đã duyệt là số liệu chính thức: nó vào bảng tổng hợp tuần và là căn cứ đánh giá. Vì vậy
            hệ thống dành riêng cho <strong>Admin / Ban giám đốc</strong> khả năng <strong>mở lại</strong> một
            báo cáo đã duyệt, kèm <strong>lý do bắt buộc</strong> và có ghi lịch sử. Bài này giúp bạn biết:
            quy tắc thật là gì, giao diện hiện đang cho làm gì, và xử lý thế nào khi một báo cáo đã duyệt bị sai.
        </p>
    </section>

    <section class="tg-section" id="dieu-kien">
        <h2 class="tg-section__title"><span class="num">2</span> Điều kiện trước khi thao tác</h2>
        <ul>
            <li>Tài khoản của bạn là <strong>Admin / Giám đốc</strong>. Trưởng phòng <em>không</em> mở lại được,
                dù chính họ đã duyệt báo cáo đó.</li>
            <li>Báo cáo phải đang ở trạng thái <strong>Đã duyệt</strong>. Ở trạng thái khác, thao tác mở lại
                bị máy chủ từ chối.</li>
            <li>Bạn có lý do rõ ràng: sai số liệu, thiếu minh chứng, duyệt nhầm…</li>
            <li>Ở phiên bản hiện tại, thao tác này <strong>chưa có nút trên giao diện Admin</strong> — xem phần
                "Lỗi thường gặp" để biết cách xử lý.</li>
        </ul>
    </section>

    <section class="tg-section" id="quy-trinh">
        <h2 class="tg-section__title"><span class="num">3</span> Quy trình tổng quan</h2>
        @include('technical.guides.partials.flow-diagram', ['steps' => [
            ['icon' => 'bi-check2-circle', 'label' => 'Đã duyệt', 'description' => 'Số liệu chính thức', 'color' => 'ok'],
            ['icon' => 'bi-search', 'label' => 'Admin rà soát', 'description' => 'Màn hình chỉ xem'],
            ['icon' => 'bi-arrow-counterclockwise', 'label' => 'Mở lại', 'description' => 'Lý do bắt buộc', 'color' => 'warn'],
            ['icon' => 'bi-pencil', 'label' => 'Nhân viên sửa', 'description' => 'Mở khoá sửa'],
            ['icon' => 'bi-send-check', 'label' => 'Gửi lại', 'description' => 'Chờ duyệt'],
            ['icon' => 'bi-check2-square', 'label' => 'Duyệt lại', 'description' => 'Hoàn tất', 'color' => 'ok'],
        ]])
    </section>

    <section class="tg-section" id="cac-buoc">
        <h2 class="tg-section__title"><span class="num">4</span> Hướng dẫn từng bước</h2>

        @include('technical.guides.partials.step', [
            'num' => 1,
            'title' => 'Tìm đúng báo cáo đã duyệt',
            'desc' => 'Trang <strong>Báo cáo ngày/tuần</strong> (<code>/ky-thuat/bao-cao-ngay</code>), lọc trạng thái <em>Đã duyệt</em>, chọn nhân viên và khoảng ngày nếu cần.',
            'result' => 'Danh sách thu gọn còn các báo cáo đã duyệt.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 2,
            'title' => 'Mở chi tiết và rà soát',
            'desc' => 'Với tài khoản Admin, màn hình chi tiết ở chế độ <strong>chỉ xem</strong>: có đủ nội dung, tệp minh chứng, khối <em>Duyệt</em> (gửi lúc, người duyệt, duyệt lúc, ý kiến) và khối <em>Lịch sử</em>, nhưng không có cụm nút xử lý.',
            'shot' => 'images/guides/technical/admin-reopen-01-detail.png',
            'alt' => 'Trang chi tiết một báo cáo đã duyệt mở bằng tài khoản Admin, ở chế độ chỉ xem',
            'caption' => 'Khối "Duyệt" bên phải cho biết ai duyệt, duyệt lúc nào và với ý kiến gì.',
            'result' => 'Bạn xác định được báo cáo có thực sự cần mở lại hay không.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 3,
            'title' => 'Đối chiếu lịch sử xử lý',
            'desc' => 'Khối <strong>Lịch sử</strong> ghi từng hành động: gửi duyệt, duyệt, yêu cầu sửa, mở lại — kèm người thực hiện, thời điểm, trạng thái trước → sau và lý do.',
            'shot' => 'images/guides/technical/admin-reopen-02-history.png',
            'alt' => 'Khối Duyệt và khối Lịch sử của một báo cáo đã duyệt',
            'caption' => 'Mọi thao tác mở lại đều để lại dấu vết ở đây, không thể xoá khỏi giao diện.',
            'result' => 'Bạn có bằng chứng đầy đủ cho quyết định mở lại.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 4,
            'title' => 'Hiểu đúng điều gì xảy ra khi mở lại',
            'desc' => 'Khi thao tác mở lại được thực hiện, hệ thống làm đúng bốn việc sau — không hơn:',
            'bullets' => [
                'Kiểm tra người thực hiện là Admin và báo cáo đang ở trạng thái <strong>Đã duyệt</strong>; sai một trong hai điều kiện thì trả lỗi 403.',
                'Bắt buộc có <strong>lý do</strong>; thiếu lý do thì không ghi gì cả.',
                'Đưa báo cáo về trạng thái <strong>Yêu cầu sửa</strong>, xoá thông tin người duyệt và thời điểm duyệt, lưu lý do vào ô ý kiến.',
                'Ghi một dòng lịch sử "mở lại" kèm trạng thái trước → sau và lý do.',
            ],
            'result' => 'Nhân viên được mở khoá sửa và gửi lại; vòng duyệt bắt đầu lại từ đầu.',
        ])
    </section>

    <section class="tg-section" id="trang-thai">
        <h2 class="tg-section__title"><span class="num">5</span> Các trạng thái có thể gặp</h2>
        @include('technical.guides.partials.status-badge', ['items' => [
            ['label' => 'Nháp', 'tone' => 'secondary', 'text' => 'Nhân viên chưa gửi — không liên quan tới thao tác mở lại.'],
            ['label' => 'Đã gửi · chờ duyệt', 'tone' => 'warning', 'text' => 'Chưa duyệt nên chưa cần mở lại; ở trạng thái này quản lý dùng "Yêu cầu sửa" để trả về.'],
            ['label' => 'Đã duyệt', 'tone' => 'success', 'text' => 'Trạng thái DUY NHẤT mà thao tác mở lại được chấp nhận.'],
            ['label' => 'Yêu cầu sửa', 'tone' => 'danger', 'text' => 'Trạng thái sau khi mở lại — nhân viên được sửa trở lại.'],
        ]])
    </section>

    <section class="tg-section" id="luu-y">
        <h2 class="tg-section__title"><span class="num">6</span> Lưu ý quan trọng</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Chỉ Admin / Giám đốc, không có ngoại lệ',
            'text' => 'Máy chủ kiểm tra trực tiếp quyền Admin cho thao tác này, độc lập với quyền duyệt báo cáo. Trưởng phòng gửi thẳng yêu cầu vẫn nhận 403.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Màn hình Admin là chế độ chỉ xem theo chủ trương hiện hành',
            'text' => 'Ban giám đốc không duyệt từng báo cáo — việc duyệt thuộc về Trưởng phòng. Vì vậy cụm nút xử lý được ẩn khỏi màn hình Admin, kể cả nút Mở lại. Quyền ở máy chủ vẫn nguyên vẹn.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Mở lại làm số tổng hợp tuần thay đổi',
            'text' => 'Báo cáo rời khỏi nhóm "đã duyệt" và chuyển sang nhóm "yêu cầu sửa". Hãy chủ động thông báo nếu số liệu tuần đã được dùng để báo cáo cấp trên.',
        ])
    </section>

    <section class="tg-section" id="loi-thuong-gap">
        <h2 class="tg-section__title"><span class="num">7</span> Lỗi thường gặp và cách xử lý</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Không thấy nút "Mở lại" dù đang là Admin',
            'text' => 'Đây là hiện trạng của phiên bản hiện tại: màn hình chi tiết báo cáo của Admin bị đặt ở chế độ chỉ xem nên toàn bộ cụm nút xử lý bị ẩn. Cách xử lý thực tế: nếu báo cáo <em>chưa duyệt</em>, nhờ Trưởng phòng bấm <strong>Yêu cầu sửa</strong>; nếu báo cáo <em>đã duyệt</em> và thực sự phải sửa, hãy đề nghị quản trị hệ thống mở lại lối vào thao tác này.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Trưởng phòng muốn tự mở lại báo cáo mình đã duyệt',
            'text' => 'Không được phép. Mở lại là đặc quyền của Admin / Giám đốc. Vì vậy hãy đọc kỹ trước khi bấm Duyệt.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'danger',
            'title' => '403 "Chỉ Admin / Giám đốc được mở lại báo cáo đã duyệt."',
            'text' => 'Tài khoản không đủ quyền, hoặc báo cáo không ở trạng thái Đã duyệt. Đây là chặn ở máy chủ; không thể vượt qua bằng cách gọi thẳng đường dẫn.',
        ])
    </section>
@endsection
