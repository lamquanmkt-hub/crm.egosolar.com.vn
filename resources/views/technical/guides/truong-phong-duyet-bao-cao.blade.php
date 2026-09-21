@extends('technical.guides.layout')

@section('guide')

    <section class="tg-section" id="muc-dich">
        <h2 class="tg-section__title"><span class="num">1</span> Mục đích</h2>
        <p>
            Duyệt báo cáo là bước xác nhận khối lượng công việc thực tế của nhân viên. Trang
            <strong>Báo cáo</strong> (<code>/ky-thuat/bao-cao-ngay</code>) là màn hình dùng chung cho cả ba vai trò;
            khi bạn có quyền quản lý, nó tự mở rộng phạm vi sang <em>toàn phòng</em> và bổ sung ô lọc theo nhân viên.
        </p>
    </section>

    <section class="tg-section" id="dieu-kien">
        <h2 class="tg-section__title"><span class="num">2</span> Điều kiện trước khi thao tác</h2>
        <ul>
            <li>Bạn có quyền quản lý module Kỹ thuật (Trưởng phòng, Admin / Ban giám đốc, hoặc được trao riêng
                quyền duyệt báo cáo kỹ thuật).</li>
            <li>Báo cáo phải đang ở trạng thái <strong>Đã gửi · chờ duyệt</strong> — báo cáo còn là Nháp thì chưa ai
                ngoài người viết nhìn thấy nội dung để xử lý.</li>
            <li>Khi trả lại báo cáo, bạn phải nhập <strong>ý kiến</strong> — đây là trường bắt buộc.</li>
            <li>Bạn <strong>không</strong> duyệt được báo cáo do chính mình viết.</li>
        </ul>
    </section>

    <section class="tg-section" id="quy-trinh">
        <h2 class="tg-section__title"><span class="num">3</span> Quy trình tổng quan</h2>
        @include('technical.guides.partials.flow-diagram', [
            'steps' => [
                ['icon' => 'bi-send', 'label' => 'Nhân viên gửi', 'description' => 'Chờ duyệt'],
                ['icon' => 'bi-eye', 'label' => 'Quản lý xem', 'description' => 'Đọc kết quả'],
                ['icon' => 'bi-check2-circle', 'label' => 'Duyệt', 'description' => 'Hoàn tất', 'color' => 'ok'],
            ],
            'branch' => [
                'label' => 'Nếu chưa đạt',
                'steps' => [
                    ['icon' => 'bi-arrow-counterclockwise', 'label' => 'Yêu cầu sửa', 'description' => 'Ý kiến bắt buộc'],
                    ['icon' => 'bi-pencil', 'label' => 'Nhân viên cập nhật', 'description' => 'Mở khoá sửa'],
                    ['icon' => 'bi-send-check', 'label' => 'Gửi lại', 'description' => 'Quay lại chờ duyệt'],
                ],
            ],
        ])
    </section>

    <section class="tg-section" id="cac-buoc">
        <h2 class="tg-section__title"><span class="num">4</span> Hướng dẫn từng bước</h2>

        @include('technical.guides.partials.step', [
            'num' => 1,
            'title' => 'Mở danh sách báo cáo toàn phòng',
            'desc' => 'Menu <strong>Báo cáo</strong>. Tiêu đề trang đổi thành <em>Báo cáo Kỹ thuật</em> (thay vì "Báo cáo của tôi") — dấu hiệu cho biết bạn đang ở chế độ quản lý.',
            'shot' => 'images/guides/technical/manager-review-01-list.png',
            'alt' => 'Trang Báo cáo Kỹ thuật ở chế độ quản lý với bộ lọc theo nhân viên và trạng thái',
            'caption' => 'Số 1: tab Báo cáo ngày / Tổng hợp tuần. Số 2: bộ lọc nhân viên, trạng thái, khoảng ngày.',
            'result' => 'Danh sách hiển thị báo cáo của toàn phòng.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 2,
            'title' => 'Lọc về đúng việc cần xử lý',
            'desc' => 'Đặt bộ lọc trạng thái về <strong>Đã gửi · chờ duyệt</strong> để chỉ còn những bản đang đợi bạn. Có thể lọc thêm theo nhân viên và khoảng ngày.',
            'result' => 'Danh sách rút gọn còn đúng hàng đợi duyệt.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 3,
            'title' => 'Mở một báo cáo và đọc nội dung',
            'desc' => 'Bấm vào báo cáo để xem chi tiết: đầu việc tương ứng, kết quả đạt được, lý do chưa hoàn thành, tệp minh chứng và — nếu là việc ngoài kế hoạch — <strong>lý do phát sinh</strong>.',
            'shot' => 'images/guides/technical/manager-review-02-detail.png',
            'alt' => 'Trang chi tiết một báo cáo ngày với nội dung kết quả và cụm nút xử lý của quản lý',
            'caption' => 'Tệp minh chứng luôn tải xuống qua đường dẫn có kiểm tra quyền.',
            'result' => 'Bạn có đủ thông tin để quyết định duyệt hay trả lại.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 4,
            'title' => 'Duyệt báo cáo',
            'desc' => 'Bấm <strong>Duyệt</strong>. Báo cáo chuyển sang trạng thái <em>Đã duyệt</em>, hệ thống ghi lại người duyệt và thời điểm duyệt.',
            'shot' => 'images/guides/technical/manager-review-03-actions.png',
            'alt' => 'Cụm nút Duyệt và Yêu cầu sửa trên trang chi tiết báo cáo ở chế độ quản lý',
            'caption' => 'Số 1: nút Duyệt. Số 2: nút Yêu cầu sửa (bắt buộc kèm ý kiến).',
            'result' => 'Thông báo "Đã duyệt báo cáo." và báo cáo rời khỏi hàng đợi.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 5,
            'title' => 'Hoặc trả lại kèm ý kiến',
            'desc' => 'Bấm <strong>Yêu cầu sửa</strong> và nhập ý kiến cụ thể: thiếu minh chứng, kết quả mô tả chung chung, sai đầu việc…',
            'bullets' => [
                'Ý kiến là <strong>bắt buộc</strong> — bỏ trống sẽ bị chặn.',
                'Báo cáo chuyển sang <strong>Yêu cầu sửa</strong> và được mở khoá cho nhân viên sửa.',
                'Thông tin người duyệt trước đó (nếu có) được xoá để vòng duyệt bắt đầu lại sạch sẽ.',
                'Toàn bộ thao tác được ghi vào lịch sử báo cáo kèm ý kiến của bạn.',
            ],
            'result' => 'Thông báo "Đã trả lại báo cáo kèm ý kiến." và nhân viên thấy ý kiến ngay trên báo cáo.',
        ])
    </section>

    <section class="tg-section" id="trang-thai">
        <h2 class="tg-section__title"><span class="num">5</span> Các trạng thái có thể gặp</h2>
        @include('technical.guides.partials.status-badge', ['items' => [
            ['label' => 'Nháp', 'tone' => 'secondary', 'text' => 'Nhân viên chưa gửi — chưa đến lượt bạn xử lý.'],
            ['label' => 'Đã gửi · chờ duyệt', 'tone' => 'warning', 'text' => 'Đang đợi bạn duyệt hoặc trả lại.'],
            ['label' => 'Đã duyệt', 'tone' => 'success', 'text' => 'Đã xác nhận. Chỉ Admin / Giám đốc mới mở lại được.'],
            ['label' => 'Yêu cầu sửa', 'tone' => 'danger', 'text' => 'Bạn đã trả lại kèm ý kiến; nhân viên đang sửa.'],
        ]])
    </section>

    <section class="tg-section" id="luu-y">
        <h2 class="tg-section__title"><span class="num">6</span> Lưu ý quan trọng</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Không tự duyệt báo cáo của mình',
            'text' => 'Dù tài khoản của bạn có quyền duyệt, hệ thống vẫn chặn duyệt báo cáo do chính bạn viết. Nhờ người quản lý khác hoặc Ban giám đốc xử lý.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Duyệt xong thì bạn không tự mở lại được',
            'text' => 'Mở lại báo cáo đã duyệt là đặc quyền của Admin / Giám đốc và bắt buộc kèm lý do. Hãy đọc kỹ trước khi bấm Duyệt.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Ý kiến trả lại nên nêu rõ phải sửa gì',
            'text' => 'Ý kiến hiển thị nguyên văn cho nhân viên và được lưu vĩnh viễn trong lịch sử. Viết cụ thể sẽ rút ngắn số vòng qua lại.',
        ])
    </section>

    <section class="tg-section" id="loi-thuong-gap">
        <h2 class="tg-section__title"><span class="num">7</span> Lỗi thường gặp và cách xử lý</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Không thấy nút Duyệt / Yêu cầu sửa',
            'text' => 'Báo cáo không ở trạng thái chờ duyệt (còn Nháp, hoặc đã duyệt rồi), hoặc đó là báo cáo do chính bạn viết.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Bấm "Yêu cầu sửa" nhưng bị chặn',
            'text' => 'Ô ý kiến đang trống. Nhập ý kiến rồi bấm lại — hệ thống bắt buộc trường này ở phía máy chủ.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Không thấy báo cáo của một nhân viên',
            'text' => 'Kiểm tra bộ lọc nhân viên / trạng thái / khoảng ngày. Nếu vẫn không có, nhân viên đó chưa gửi báo cáo (báo cáo còn ở trạng thái Nháp).',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'danger',
            'title' => '403 "Bạn không có quyền xử lý báo cáo này."',
            'text' => 'Bạn đang thao tác trên báo cáo ngoài phạm vi quản lý, hoặc trên báo cáo của chính mình. Máy chủ kiểm tra lại quyền ở từng thao tác.',
        ])
    </section>
@endsection
