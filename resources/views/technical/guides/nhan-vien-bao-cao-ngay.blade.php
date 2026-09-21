@extends('technical.guides.layout')

@section('guide')

    <section class="tg-section" id="muc-dich">
        <h2 class="tg-section__title"><span class="num">1</span> Mục đích</h2>
        <p>
            Báo cáo ngày là bằng chứng công việc của bạn. Mỗi <strong>đầu việc</strong> có <strong>một báo cáo riêng</strong>
            — hệ thống không gộp cả ngày vào một bản và không ghi đè báo cáo cũ. Báo cáo sau khi gửi sẽ được
            Trưởng phòng (hoặc Ban giám đốc) duyệt hoặc trả lại kèm ý kiến.
        </p>
    </section>

    <section class="tg-section" id="dieu-kien">
        <h2 class="tg-section__title"><span class="num">2</span> Điều kiện trước khi thao tác</h2>
        <ul>
            <li>Bạn đã lập kế hoạch tuần và có ít nhất một dòng công việc trong ngày cần báo cáo
                (xem bài <em>Lập kế hoạch tuần của tôi</em>).</li>
            <li>Nếu việc bạn làm <em>không</em> có trong kế hoạch, dùng luồng riêng ở bài
                <em>Báo cáo công việc phát sinh</em> — bắt buộc nhập lý do phát sinh.</li>
            <li>Ảnh / tệp minh chứng (nếu có) đã sẵn trên máy.</li>
        </ul>
    </section>

    <section class="tg-section" id="quy-trinh">
        <h2 class="tg-section__title"><span class="num">3</span> Quy trình tổng quan</h2>
        @include('technical.guides.partials.flow-diagram', [
            'steps' => [
                ['icon' => 'bi-pencil-square', 'label' => 'Nháp', 'description' => 'Bạn còn sửa được'],
                ['icon' => 'bi-send', 'label' => 'Gửi duyệt', 'description' => 'Chờ quản lý xem', 'color' => 'warn'],
                ['icon' => 'bi-check2-circle', 'label' => 'Đã duyệt', 'description' => 'Hoàn tất', 'color' => 'ok'],
            ],
            'branch' => [
                'label' => 'Nếu quản lý trả lại',
                'steps' => [
                    ['icon' => 'bi-arrow-counterclockwise', 'label' => 'Yêu cầu sửa', 'description' => 'Kèm ý kiến'],
                    ['icon' => 'bi-pencil', 'label' => 'Cập nhật', 'description' => 'Sửa lại nội dung'],
                    ['icon' => 'bi-send-check', 'label' => 'Gửi lại', 'description' => 'Quay về chờ duyệt'],
                ],
            ],
        ])
    </section>

    <section class="tg-section" id="cac-buoc">
        <h2 class="tg-section__title"><span class="num">4</span> Hướng dẫn từng bước</h2>

        @include('technical.guides.partials.step', [
            'num' => 1,
            'title' => 'Mở danh sách báo cáo của tôi',
            'desc' => 'Menu <strong>Kỹ thuật → Báo cáo</strong> (<code>/ky-thuat/bao-cao-ngay</code>). Trang có bộ lọc theo trạng thái và khoảng ngày.',
            'shot' => 'images/guides/technical/staff-report-01-list.png',
            'alt' => 'Trang Báo cáo của tôi với cụm nút Viết báo cáo, Báo cáo việc phát sinh và bộ lọc theo trạng thái',
            'caption' => 'Số 1: nút Viết báo cáo. Số 2: nút Báo cáo việc phát sinh. Số 3: bộ lọc trạng thái và khoảng ngày.',
            'result' => 'Bạn thấy toàn bộ báo cáo của mình kèm trạng thái từng bản.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 2,
            'title' => 'Bấm "Viết báo cáo" và chọn đúng dòng kế hoạch',
            'desc' => 'Form báo cáo có ô chọn <strong>đầu việc</strong> nạp sẵn các dòng kế hoạch của bạn trong ngày. Chọn đúng dòng chính là cách hệ thống nối báo cáo với kế hoạch.',
            'shot' => 'images/guides/technical/staff-report-02-form.png',
            'alt' => 'Form viết báo cáo ngày với ô chọn đầu việc theo kế hoạch và các ô nội dung công việc đã làm',
            'caption' => 'Chọn dòng kế hoạch ở ô đầu tiên; các ô bên dưới là nội dung bạn thực hiện được.',
            'result' => 'Báo cáo được gắn với đúng dòng kế hoạch, không cần gõ lại tên công việc.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 3,
            'title' => 'Điền kết quả thực hiện',
            'desc' => 'Ghi đúng những gì đã làm được trong ngày.',
            'bullets' => [
                '<strong>Kết quả đạt được</strong> — mô tả phần việc đã hoàn thành.',
                '<strong>Lý do chưa hoàn thành</strong> — điền khi việc chưa xong, để quản lý hiểu nguyên nhân.',
                '<strong>Tệp minh chứng</strong> — ảnh hiện trường, biên bản… tải lên trực tiếp ở form.',
            ],
            'result' => 'Nội dung báo cáo đã đầy đủ để gửi đi.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 4,
            'title' => 'Chọn "Hoàn tất báo cáo" hoặc "Lưu nháp"',
            'desc' => 'Cuối form có đúng hai nút, với ý nghĩa khác nhau.',
            'bullets' => [
                '<strong>Lưu nháp</strong> → báo cáo ở trạng thái <strong>Nháp</strong>, bạn còn sửa và xoá tệp thoải mái.',
                '<strong>Hoàn tất báo cáo</strong> → báo cáo chuyển sang <strong>Đã gửi · chờ duyệt</strong> và <em>khoá sửa</em> cho tới khi quản lý xử lý.',
                'Ngay trên form có ghi rõ: "Hoàn tất báo cáo" đã là trạng thái đã nộp — hệ thống dùng ngay để so sánh kế hoạch với kết quả, không cần chờ Ban giám đốc duyệt.',
            ],
            'shot' => 'images/guides/technical/staff-report-03-submit.png',
            'alt' => 'Cuối form báo cáo ngày với hai nút Hoàn tất báo cáo và Lưu nháp',
            'caption' => 'Sau khi hoàn tất, bạn không còn sửa được cho tới khi quản lý trả lại.',
            'result' => 'Báo cáo hiện trong danh sách với trạng thái tương ứng.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 5,
            'title' => 'Khi bị yêu cầu sửa: cập nhật và gửi lại',
            'desc' => 'Báo cáo bị trả lại chuyển sang <strong>Yêu cầu sửa</strong> và <em>mở khoá sửa</em> trở lại. Ý kiến của quản lý hiển thị ngay trên trang chi tiết báo cáo.',
            'bullets' => [
                'Mở báo cáo → đọc ý kiến của quản lý.',
                'Bấm sửa, cập nhật đúng phần được góp ý.',
                'Gửi lại → báo cáo quay về <strong>Đã gửi · chờ duyệt</strong>.',
            ],
            'shot' => 'images/guides/technical/staff-report-04-status.png',
            'alt' => 'Danh sách báo cáo hiển thị các trạng thái Nháp, Đã gửi chờ duyệt và Đã duyệt',
            'caption' => 'Cột trạng thái cho biết bản nào còn sửa được, bản nào đang chờ quản lý.',
            'result' => 'Vòng duyệt lặp lại cho tới khi báo cáo được duyệt.',
        ])
    </section>

    <section class="tg-section" id="trang-thai">
        <h2 class="tg-section__title"><span class="num">5</span> Các trạng thái có thể gặp</h2>
        @include('technical.guides.partials.status-badge', ['items' => [
            ['label' => 'Nháp', 'tone' => 'secondary', 'text' => 'Chưa gửi. Bạn sửa và xoá tệp minh chứng được.'],
            ['label' => 'Đã gửi · chờ duyệt', 'tone' => 'warning', 'text' => 'Đã gửi cho quản lý. Bạn không sửa được nữa.'],
            ['label' => 'Đã duyệt', 'tone' => 'success', 'text' => 'Quản lý đã duyệt. Chỉ Admin / Giám đốc mới mở lại được.'],
            ['label' => 'Yêu cầu sửa', 'tone' => 'danger', 'text' => 'Bị trả lại kèm ý kiến bắt buộc của quản lý. Bạn sửa rồi gửi lại.'],
        ]])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Vì sao có nơi ghi "Hoàn tất (đã nộp)"?',
            'text' => 'Ở một số bảng tổng hợp, hệ thống dùng nhãn theo góc nhìn nghiệp vụ: <strong>Nháp</strong>, <strong>Hoàn tất (đã nộp)</strong> (gộp cả "chờ duyệt" lẫn "đã duyệt") và <strong>Cần cập nhật lại</strong>. Đây vẫn là bốn trạng thái ở trên, chỉ khác cách gọi.',
        ])
    </section>

    <section class="tg-section" id="luu-y">
        <h2 class="tg-section__title"><span class="num">6</span> Lưu ý quan trọng</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Mỗi đầu việc một báo cáo riêng',
            'text' => 'Đừng gộp nhiều đầu việc vào một báo cáo. Viết riêng từng bản giúp số liệu tổng hợp tuần và đối chiếu kế hoạch – thực tế chính xác.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Báo cáo không ghi đè lẫn nhau',
            'text' => 'Viết báo cáo mới cho cùng một đầu việc sẽ tạo thêm một bản, không xoá bản cũ. Muốn sửa nội dung cũ, hãy mở đúng bản đó (khi nó còn ở trạng thái Nháp hoặc Yêu cầu sửa).',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Bạn không tự duyệt báo cáo của mình',
            'text' => 'Kể cả khi tài khoản của bạn có quyền duyệt, hệ thống vẫn chặn việc tự duyệt báo cáo do chính mình viết.',
        ])
    </section>

    <section class="tg-section" id="loi-thuong-gap">
        <h2 class="tg-section__title"><span class="num">7</span> Lỗi thường gặp và cách xử lý</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Ô chọn đầu việc trống, không có dòng nào để chọn',
            'text' => 'Ngày bạn chọn chưa có dòng kế hoạch nào. Hoặc quay lại lập kế hoạch cho ngày đó, hoặc dùng luồng <strong>Báo cáo việc phát sinh</strong> (kèm lý do phát sinh bắt buộc).',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Không sửa được báo cáo đã gửi',
            'text' => 'Đúng như thiết kế: chỉ báo cáo ở trạng thái <strong>Nháp</strong> hoặc <strong>Yêu cầu sửa</strong> mới sửa và xoá tệp được. Nếu cần sửa gấp, nhờ quản lý bấm <em>Yêu cầu sửa</em> để trả lại cho bạn.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Tải tệp minh chứng bị từ chối',
            'text' => 'Kiểm tra dung lượng và định dạng tệp theo thông báo lỗi hiện trên form. Tệp minh chứng luôn tải xuống qua đường dẫn có kiểm tra quyền, không có liên kết công khai.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'danger',
            'title' => 'Báo cáo "biến mất" khỏi danh sách',
            'text' => 'Rất có thể bộ lọc trạng thái hoặc khoảng ngày đang bật. Bấm nút bỏ lọc bên cạnh nút Lọc để xem lại toàn bộ.',
        ])
    </section>
@endsection
