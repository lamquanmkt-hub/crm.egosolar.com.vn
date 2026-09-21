@extends('technical.guides.layout')

@section('guide')

    <section class="tg-section" id="muc-dich">
        <h2 class="tg-section__title"><span class="num">1</span> Mục đích</h2>
        <p>
            Trang <strong>Kế hoạch nhân viên</strong> (<code>/ky-thuat/quan-ly/ke-hoach</code>) là ma trận
            <em>nhân viên × ngày trong tuần</em> của cả phòng. Từ đây bạn làm được ba việc:
            <strong>tạo kế hoạch nhiều dòng</strong> cho một nhân viên, <strong>giao nhanh một việc</strong>,
            và <strong>điều chỉnh / chuyển ngày</strong> các dòng đã có. Mọi thao tác ghi đều
            <strong>bắt buộc nhập lý do</strong> và được ghi lịch sử.
        </p>
    </section>

    <section class="tg-section" id="dieu-kien">
        <h2 class="tg-section__title"><span class="num">2</span> Điều kiện trước khi thao tác</h2>
        <ul>
            <li>Bạn có quyền quản lý module Kỹ thuật (Trưởng phòng Kỹ thuật, hoặc Admin / Ban giám đốc).</li>
            <li>Nhân viên bạn định giao việc phải nằm trong phạm vi quản lý của bạn — ngoài phạm vi, hệ thống trả 403.</li>
            <li>Bạn chuẩn bị sẵn <strong>lý do</strong> cho lần ghi này: đây là trường bắt buộc ở mọi form giao việc,
                điều chỉnh và chuyển ngày.</li>
            <li>Chọn đúng tuần trên thanh tuần trước khi thao tác.</li>
        </ul>
    </section>

    <section class="tg-section" id="quy-trinh">
        <h2 class="tg-section__title"><span class="num">3</span> Quy trình tổng quan</h2>
        @include('technical.guides.partials.flow-diagram', ['steps' => [
            ['icon' => 'bi-grid-3x3', 'label' => 'Xem ma trận', 'description' => 'Ai đang làm gì'],
            ['icon' => 'bi-plus-lg', 'label' => 'Tạo / Giao', 'description' => 'Mở form phù hợp'],
            ['icon' => 'bi-chat-left-text', 'label' => 'Nhập lý do', 'description' => 'Bắt buộc', 'color' => 'warn'],
            ['icon' => 'bi-exclamation-triangle', 'label' => 'Xử lý cảnh báo', 'description' => 'Trùng lịch / quá tải', 'color' => 'warn'],
            ['icon' => 'bi-check2-circle', 'label' => 'Lưu', 'description' => 'Ghi lịch sử', 'color' => 'ok'],
        ]])
    </section>

    <section class="tg-section" id="cac-buoc">
        <h2 class="tg-section__title"><span class="num">4</span> Hướng dẫn từng bước</h2>

        @include('technical.guides.partials.step', [
            'num' => 1,
            'title' => 'Đọc ma trận kế hoạch tuần',
            'desc' => 'Mỗi hàng là một nhân viên, mỗi cột là một ngày. Ô ngày cho biết nhân viên đó có bao nhiêu việc, hoặc ghi "Chưa lập kế hoạch". Cạnh tên nhân viên có <strong>menu ba chấm</strong> với các thao tác nhanh.',
            'shot' => 'images/guides/technical/manager-board-01-matrix.png',
            'alt' => 'Ma trận kế hoạch tuần của phòng Kỹ thuật với các hàng nhân viên và bảy cột ngày',
            'caption' => 'Số 1: thanh chọn tuần. Số 2: hai nút Tạo kế hoạch / Giao việc. Số 3: một ô ngày bấm được.',
            'result' => 'Bạn thấy ngay ai đang trống lịch và ai đang quá tải trong tuần.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 2,
            'title' => 'Phân biệt hai form: "Tạo kế hoạch" và "Giao việc"',
            'desc' => 'Đây là hai form riêng biệt, mở ra dạng ngăn kéo (drawer) ngay trên trang. Chọn đúng form tiết kiệm rất nhiều thời gian.',
            'bullets' => [
                '<strong>Tạo kế hoạch</strong> (nút chính, màu primary) — nhập <em>nhiều dòng</em> công việc cho một nhân viên trong một lần lưu. Có hai kiểu lưu: <em>Lưu nháp</em> và <em>Lưu và giao kế hoạch</em>.',
                '<strong>Giao việc</strong> (nút phụ, viền) — nhập <em>một dòng</em> duy nhất, giao ngay. Dùng khi chỉ cần bổ sung một đầu việc.',
                'Cả hai đi qua đúng <em>một</em> lối ghi ở máy chủ, nên dữ liệu và lịch sử hoàn toàn thống nhất.',
            ],
            'shot' => 'images/guides/technical/manager-plan-create-02-empty.png',
            'alt' => 'Ngăn kéo Tạo kế hoạch mở ra với form trống gồm ô chọn nhân viên, tuần và các dòng công việc',
            'caption' => 'Form "Tạo kế hoạch" khi vừa mở: chọn nhân viên, tuần, rồi thêm từng dòng việc.',
            'result' => 'Form mở ngay trên trang, không rời khỏi ma trận.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 3,
            'title' => 'Bấm thẳng vào một ô ngày để mở form đã điền sẵn',
            'desc' => 'Thay vì mở form rồi chọn lại nhân viên và ngày, bấm vào chính ô giao nhau giữa nhân viên và ngày bạn muốn. Ngăn kéo mở ra với <strong>nhân viên và ngày đã được chọn sẵn</strong>.',
            'shot' => 'images/guides/technical/manager-assign-05-cell.png',
            'alt' => 'Ngăn kéo Giao việc mở từ một ô ngày, đã điền sẵn tên nhân viên và ngày thực hiện',
            'caption' => 'Bấm ô ngày là cách nhanh nhất để giao việc đúng người, đúng ngày.',
            'result' => 'Bạn chỉ còn phải nhập nội dung công việc và lý do.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 4,
            'title' => 'Điền các dòng công việc',
            'desc' => 'Mỗi dòng là một đầu việc độc lập. Ở form "Tạo kế hoạch" bạn thêm / sao chép / xoá dòng tuỳ ý; ở form "Giao việc" chỉ có đúng một dòng nên các nút thao tác dòng được ẩn đi.',
            'bullets' => [
                '<strong>Ngày</strong> và <strong>Buổi</strong> (Sáng / Chiều / Cả ngày).',
                '<strong>Giờ bắt đầu / kết thúc</strong> — điền để hệ thống phát hiện trùng khung giờ.',
                '<strong>Nội dung công việc</strong> — bắt buộc.',
                '<strong>Mục tiêu cần đạt</strong>, <strong>thời lượng dự kiến</strong>, <strong>ưu tiên</strong>, <strong>ghi chú</strong>.',
                '<strong>Đầu việc nguồn</strong> — chọn từ danh sách đầu việc đang giao cho nhân viên đó, để không mất liên kết với Công trình / Task / Bảo trì.',
            ],
            'shot' => 'images/guides/technical/manager-plan-create-03-filled.png',
            'alt' => 'Form Tạo kế hoạch đã điền ba dòng công việc cho một nhân viên trong tuần',
            'caption' => 'Toàn bộ các dòng được lưu trong một giao dịch: hoặc lưu hết, hoặc không lưu dòng nào.',
            'result' => 'Ba dòng nằm trọn trong một lần lưu, cùng một lý do, cùng một dấu vết lịch sử.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 5,
            'title' => 'Chọn đúng kiểu lưu',
            'desc' => 'Ở form "Tạo kế hoạch" có hai nút lưu với ý nghĩa nghiệp vụ khác nhau.',
            'bullets' => [
                '<strong>Lưu nháp</strong> — các dòng được tạo nhưng <em>không</em> đánh dấu là việc do quản lý giao. Dùng khi bạn mới phác thảo kế hoạch cho nhân viên.',
                '<strong>Lưu và giao kế hoạch</strong> — các dòng được đánh dấu là việc quản lý giao xuống, nhân viên thấy ngay trong kế hoạch tuần của họ.',
                'Form "Giao việc" một dòng luôn ở chế độ giao.',
            ],
            'result' => 'Thông báo thành công hiện kèm liên kết "Xem chi tiết kế hoạch của …".',
        ])

        @include('technical.guides.partials.step', [
            'num' => 6,
            'title' => 'Xử lý cảnh báo trùng lịch / quá tải',
            'desc' => 'Nếu việc bạn vừa nhập tạo ra cảnh báo <em>mới</em> (trùng khung giờ với việc khác, hoặc ngày đó vượt giờ làm việc chuẩn), ngăn kéo sẽ hiện danh sách cảnh báo và <strong>chặn lưu</strong> cho tới khi bạn tích ô xác nhận.',
            'bullets' => [
                'Danh sách cảnh báo ghi rõ ngày và hai công việc bị trùng giờ, hoặc tổng thời lượng vượt chuẩn.',
                'Tích ô xác nhận rồi bấm lưu lại — hệ thống hiểu bạn đã cân nhắc và vẫn muốn giao.',
                'Cảnh báo lặp lại y hệt cảnh báo đã có vẫn được đếm là cảnh báo mới, nên vẫn phải xác nhận.',
            ],
            'shot' => 'images/guides/technical/manager-warning-06-confirm.png',
            'alt' => 'Ngăn kéo hiển thị danh sách cảnh báo trùng khung giờ kèm ô xác nhận bắt buộc trước khi lưu',
            'caption' => 'Cổng chặn này chỉ có ở hai ngăn kéo mới trên trang ma trận.',
            'result' => 'Sau khi xác nhận, các dòng được lưu và lịch sử ghi lại đúng lý do bạn nhập.',
        ])

        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Khác biệt quan trọng: form "Giao thêm việc" ở trang chi tiết',
            'text' => 'Ở trang chi tiết kế hoạch của một nhân viên còn một form <strong>Giao thêm việc</strong> (một dòng) không có ô xác nhận cảnh báo. Form đó vẫn hiện cảnh báo nhưng <em>không chặn lưu</em> — đây là hành vi cố ý, khác với hai ngăn kéo trên trang ma trận. Khi cần cổng chặn, hãy dùng ngăn kéo ở trang ma trận.',
        ])

        @include('technical.guides.partials.step', [
            'num' => 7,
            'title' => 'Điều chỉnh hoặc chuyển ngày một dòng đã có',
            'desc' => 'Từ menu ba chấm cạnh tên nhân viên hoặc từ trang chi tiết kế hoạch, bạn sửa nội dung một dòng hoặc chuyển nó sang ngày khác.',
            'bullets' => [
                'Cả hai thao tác đều <strong>bắt buộc nhập lý do điều chỉnh</strong>.',
                'Chỉ thao tác được trên dòng của nhân viên thuộc phạm vi quản lý của bạn.',
                'Sau khi bạn điều chỉnh, tuần của nhân viên chuyển sang trạng thái <em>Đã được Trưởng phòng điều chỉnh</em>.',
            ],
            'result' => 'Thay đổi hiển thị ngay trên ma trận và được lưu vào lịch sử kế hoạch.',
        ])
    </section>

    <section class="tg-section" id="trang-thai">
        <h2 class="tg-section__title"><span class="num">5</span> Các trạng thái có thể gặp</h2>
        <p>Trạng thái tuần kế hoạch của từng nhân viên trên ma trận:</p>
        @include('technical.guides.partials.status-badge', ['items' => [
            ['label' => 'Đang lập', 'tone' => 'secondary', 'text' => 'Nhân viên đang soạn, chưa chốt.'],
            ['label' => 'Đã hoàn tất', 'tone' => 'success', 'text' => 'Nhân viên đã chốt kế hoạch tuần.'],
            ['label' => 'Đã được Trưởng phòng điều chỉnh', 'tone' => 'warning', 'text' => 'Bạn (hoặc quản lý khác) đã can thiệp vào tuần này.'],
        ]])
        <p class="mt-3">Trạng thái từng dòng công việc:</p>
        @include('technical.guides.partials.status-badge', ['items' => [
            ['label' => 'Dự kiến', 'tone' => 'secondary', 'text' => 'Chưa bắt đầu.'],
            ['label' => 'Đang thực hiện', 'tone' => 'info', 'text' => 'Nhân viên đang làm.'],
            ['label' => 'Hoàn thành', 'tone' => 'success', 'text' => 'Đã xong.'],
            ['label' => 'Chưa hoàn thành', 'tone' => 'danger', 'text' => 'Quá thời hạn mà chưa xong.'],
            ['label' => 'Chuyển sang ngày sau', 'tone' => 'warning', 'text' => 'Đã được dời ngày.'],
            ['label' => 'Đã huỷ', 'tone' => 'secondary', 'text' => 'Không còn phải làm.'],
        ]])
    </section>

    <section class="tg-section" id="luu-y">
        <h2 class="tg-section__title"><span class="num">6</span> Lưu ý quan trọng</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Lý do là bắt buộc ở mọi thao tác ghi',
            'text' => 'Giao việc, tạo kế hoạch, điều chỉnh, chuyển ngày, yêu cầu nhân viên cập nhật — tất cả đều yêu cầu lý do. Đây là cách hệ thống giữ dấu vết cho tranh luận về sau.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Nhiều dòng = một giao dịch',
            'text' => 'Khi lưu form "Tạo kế hoạch", tất cả các dòng được ghi trong một giao dịch duy nhất. Một dòng sai sẽ làm cả lần lưu không được ghi — bạn không bao giờ rơi vào trạng thái nửa vời.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'info',
            'title' => 'Admin / Ban giám đốc dùng chung đúng luồng này',
            'text' => 'Admin có trang riêng <code>/ky-thuat/dashboard/ke-hoach</code>, nhưng khi bấm Tạo kế hoạch / Giao việc thì vẫn về đúng hai ngăn kéo của trang ma trận — không có luồng ghi thứ hai.',
        ])
    </section>

    <section class="tg-section" id="loi-thuong-gap">
        <h2 class="tg-section__title"><span class="num">7</span> Lỗi thường gặp và cách xử lý</h2>
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => '"Bắt buộc nhập lý do điều chỉnh"',
            'text' => 'Bạn bấm lưu mà chưa điền ô lý do. Không có cách nào bỏ qua — hãy ghi một câu ngắn nêu căn cứ giao / điều chỉnh.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => 'Bấm lưu nhưng ngăn kéo hiện danh sách cảnh báo và không lưu',
            'text' => 'Việc bạn vừa nhập tạo ra cảnh báo mới (trùng khung giờ hoặc quá tải ngày). Đọc danh sách, chỉnh lại giờ nếu muốn tránh trùng, hoặc <strong>tích ô xác nhận</strong> rồi lưu lại.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'warning',
            'title' => '"Vui lòng chọn nhân viên nhận kế hoạch."',
            'text' => 'Form được mở mà chưa chọn nhân viên. Cách nhanh nhất là đóng ngăn kéo và bấm thẳng vào ô ngày của nhân viên cần giao — nhân viên và ngày sẽ được điền sẵn.',
        ])
        @include('technical.guides.partials.note', [
            'tone' => 'danger',
            'title' => '403 "Nhân sự này không thuộc phạm vi quản lý của bạn."',
            'text' => 'Bạn đang thao tác lên nhân viên ngoài phạm vi quản lý. Máy chủ kiểm tra lại quyền ở mọi thao tác ghi, nên việc đổi tham số trên URL không có tác dụng.',
        ])
    </section>
@endsection
