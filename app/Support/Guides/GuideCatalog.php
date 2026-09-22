<?php

declare(strict_types=1);

namespace App\Support\Guides;

/**
 * Nội dung "Hướng dẫn sử dụng" cho từng trang — mỗi trang có bài riêng, không dùng chung 1 bài cho
 * tất cả. Ảnh là ảnh chụp THẬT từ trình duyệt local (public/images/guides/*.png), không phải ảnh AI.
 */
final class GuideCatalog
{
    /**
     * @return array<string, array{title:string,roles:array<int,string>,intro:string,steps:array<int,array{title:string,desc:string,image:string,note:?string}>}>
     */
    public static function all(): array
    {
        $img = fn (string $f) => 'images/guides/'.$f;

        return [
            'doi-hang-bao-hanh' => [
                'title' => 'HƯỚNG DẪN ĐỔI HÀNG BẢO HÀNH',
                'roles' => ['ky_thuat', 'technical', 'technical_manager', 'admin'],
                'intro' => 'Dành cho Kỹ thuật / Trưởng phòng Kỹ thuật. Nghiệp vụ Kho (giữ hàng, xuất kho, thu hồi) đã chuyển sang trang riêng Kho → Xuất hàng BH/SC, xem bài "Hướng dẫn Kho".',
                'steps' => [
                    ['title' => 'Bước 1 · Tạo đề xuất', 'desc' => 'Ở trang "Đổi hàng bảo hành", bấm nút ① "Tạo đề xuất đổi hàng" để mở popup.', 'image' => $img('exchange-01-create-open.png'), 'note' => null],
                    ['title' => 'Bước 2 · Nhập serial', 'desc' => 'Nhập serial thiết bị lỗi vào ô ② rồi bấm ③ "Kiểm tra Serial".', 'image' => $img('exchange-02-serial-input.png'), 'note' => 'Serial trống hoặc sai sẽ báo lỗi ngay trong popup.'],
                    ['title' => 'Bước 3 · Kiểm tra thông tin thiết bị', 'desc' => 'Hệ thống tự tra cứu sản phẩm, khách hàng, đơn hàng, công trình và tình trạng bảo hành — kiểm tra lại trước khi làm tiếp.', 'image' => $img('exchange-03-serial-result.png'), 'note' => null],
                    ['title' => 'Bước 4 · Nhập lỗi/chẩn đoán', 'desc' => 'Điền mô tả lỗi, chẩn đoán ban đầu và phương án đề xuất đổi.', 'image' => $img('exchange-04-diagnosis-fields.png'), 'note' => null],
                    ['title' => 'Bước 5 · Gửi duyệt', 'desc' => 'Bấm ④ "Gửi đề xuất" để tạo phiếu và chuyển sang Trưởng phòng/Admin.', 'image' => $img('exchange-05-submit.png'), 'note' => null],
                    ['title' => 'Bước 6 · Trưởng phòng/Admin duyệt', 'desc' => 'Trưởng phòng mở phiếu, bấm ① "Duyệt & chuyển Kho" trong popup Duyệt.', 'image' => $img('exchange-06-approve.png'), 'note' => null],
                    ['title' => 'Bước 7 · Chờ Kho', 'desc' => 'Sau khi duyệt, phiếu chuyển trạng thái "Chờ Kho" — Kỹ thuật chỉ theo dõi, không thao tác Kho.', 'image' => $img('exchange-07-waiting-stock.png'), 'note' => null],
                    ['title' => 'Bước 8 · Kho giữ/xuất hàng', 'desc' => 'Kho xử lý ở trang Kho → Xuất hàng BH/SC (xem bài hướng dẫn Kho). Timeline bên Kỹ thuật tự cập nhật theo đúng trạng thái.', 'image' => $img('exchange-08-timeline-stock.png'), 'note' => null],
                    ['title' => 'Bước 9 · Kỹ thuật nhận hàng', 'desc' => 'Sau khi Kho xuất, Kỹ thuật bấm ① "Đã nhận hàng" để xác nhận đã nhận thiết bị thay thế.', 'image' => $img('exchange-09-tech-receive.png'), 'note' => null],
                    ['title' => 'Bước 10 · Thay cho khách', 'desc' => 'Bấm ① "Xác nhận đã thay" sau khi lắp thiết bị mới cho khách.', 'image' => $img('exchange-10-replace.png'), 'note' => null],
                    ['title' => 'Bước 11 · Thu hồi hàng lỗi', 'desc' => 'Thiết bị lỗi được Kho thu hồi ở trang Kho — Kỹ thuật chỉ xem trạng thái "Chờ thu hồi/Đã thu hồi".', 'image' => $img('exchange-11-faulty-status.png'), 'note' => null],
                    ['title' => 'Bước 12 · Hoàn tất', 'desc' => 'Trưởng phòng bấm ① "Hoàn tất phiếu" sau khi đủ điều kiện.', 'image' => $img('exchange-12-complete.png'), 'note' => null],
                ],
            ],
            'sua-chua-tinh-phi' => [
                'title' => 'HƯỚNG DẪN SỬA CHỮA TÍNH PHÍ',
                'roles' => ['ky_thuat', 'technical', 'technical_manager', 'admin'],
                'intro' => 'Dành cho Kỹ thuật / Trưởng phòng Kỹ thuật. Nghiệp vụ Kho (giữ/xuất/hoàn linh kiện) xem bài "Hướng dẫn Kho".',
                'steps' => [
                    ['title' => 'Bước 1 · Tiếp nhận', 'desc' => 'Bấm ① "Tiếp nhận sửa chữa" để mở popup tiếp nhận — nhận cả thiết bị mua nơi khác, chưa có trong CRM.', 'image' => $img('repair-01-intake-open.png'), 'note' => null],
                    ['title' => 'Bước 2 · Tạo khách hàng nếu chưa có', 'desc' => 'Nhập tên/SĐT khách — nếu chưa có trong CRM, hệ thống tự tạo khách mới khi lưu.', 'image' => $img('repair-02-customer.png'), 'note' => null],
                    ['title' => 'Bước 3 · Nhập thiết bị', 'desc' => 'Nhập loại sản phẩm, model, serial (có thể để trống nếu chưa có).', 'image' => $img('repair-03-device.png'), 'note' => null],
                    ['title' => 'Bước 4 · Chẩn đoán', 'desc' => 'Từ timeline, bấm ① nút thao tác bước hiện tại để mở popup Chẩn đoán và nhập kết luận.', 'image' => $img('repair-04-diagnosis.png'), 'note' => null],
                    ['title' => 'Bước 5 · Lập báo giá', 'desc' => 'Thêm dòng linh kiện + chi phí công, bấm ① "Lưu & gửi khách xác nhận".', 'image' => $img('repair-05-quote.png'), 'note' => null],
                    ['title' => 'Bước 6 · Khách xác nhận', 'desc' => 'Ghi nhận quyết định của khách (đồng ý/từ chối) qua popup "Khách xác nhận báo giá".', 'image' => $img('repair-06-decision.png'), 'note' => null],
                    ['title' => 'Bước 7 · Kho xuất linh kiện', 'desc' => 'Kho giữ và xuất linh kiện ở trang Kho — Kỹ thuật theo dõi trạng thái linh kiện ngay trên phiếu.', 'image' => $img('repair-07-parts-status.png'), 'note' => null],
                    ['title' => 'Bước 8 · Sửa chữa', 'desc' => 'Bấm ① "Bắt đầu sửa chữa" rồi cập nhật tiến độ.', 'image' => $img('repair-08-start.png'), 'note' => null],
                    ['title' => 'Bước 9 · Phát sinh báo giá V2 nếu có', 'desc' => 'Nếu phát sinh chi phí ngoài dự kiến, bấm ① "Báo giá phát sinh" — hệ thống lưu bản V2 riêng, giữ nguyên V1.', 'image' => $img('repair-09-change-quote.png'), 'note' => 'V1 không bị sửa; khách phải xác nhận lại V2 trước khi tiếp tục.'],
                    ['title' => 'Bước 10 · QA', 'desc' => 'Chuyển sang kiểm tra sau sửa và ghi nhận kết quả đạt/không đạt.', 'image' => $img('repair-10-qa.png'), 'note' => null],
                    ['title' => 'Bước 11 · Hoàn linh kiện dư', 'desc' => 'Nếu dùng không hết linh kiện đã xuất, Kho nhận hoàn phần dư ở trang Kho trước khi hoàn tất phiếu.', 'image' => $img('repair-11-parts-return-status.png'), 'note' => null],
                    ['title' => 'Bước 12 · Bàn giao', 'desc' => 'Bấm ① "Xác nhận bàn giao" sau khi trả thiết bị cho khách.', 'image' => $img('repair-12-handover.png'), 'note' => null],
                    ['title' => 'Bước 13 · Hoàn tất', 'desc' => 'Trưởng phòng bấm ① "Hoàn tất phiếu" — cần linh kiện đã quyết toán xong (dùng/hoàn kho).', 'image' => $img('repair-13-complete.png'), 'note' => null],
                ],
            ],
            'kho-bh-sc' => [
                'title' => 'HƯỚNG DẪN KHO XỬ LÝ BẢO HÀNH & SỬA CHỮA',
                'roles' => ['warehouse', 'kho', 'admin'],
                'intro' => 'Dành cho Kho. Không cần sang menu Kỹ thuật — toàn bộ thao tác Kho thực hiện tại trang này.',
                'steps' => [
                    ['title' => 'A1 · Mở trang Kho', 'desc' => 'Vào menu Kho → ① "Xuất hàng BH/SC".', 'image' => $img('kho-01-menu.png'), 'note' => null],
                    ['title' => 'A2 · Chọn phiếu ở tab "Chờ Kho xử lý"', 'desc' => 'Danh sách phiếu đổi hàng/sửa chữa đang chờ Kho, lọc theo loại/kho/kỹ thuật/khách hàng nếu cần.', 'image' => $img('kho-02-waiting-tab.png'), 'note' => null],
                    ['title' => 'A3 · Kiểm tra sản phẩm', 'desc' => 'Bấm ① "Chi tiết" để xem đúng sản phẩm/serial lỗi cần đổi trước khi giữ hàng.', 'image' => $img('kho-03-detail.png'), 'note' => null],
                    ['title' => 'A4 · Chọn serial & Reserve', 'desc' => 'Bấm ① "Giữ hàng", chọn kho xuất và nhập serial thay thế cùng sản phẩm.', 'image' => $img('kho-04-reserve.png'), 'note' => null],
                    ['title' => 'A5 · Xuất hàng', 'desc' => 'Ở tab "Đã giữ hàng", bấm ① "Xuất hàng" để xác nhận đã xuất kho serial thay thế.', 'image' => $img('kho-05-issue.png'), 'note' => null],
                    ['title' => 'A6 · Giao kỹ thuật', 'desc' => 'Sau khi xuất, Kỹ thuật tự xác nhận "Đã nhận hàng" trên phiếu của họ — Kho không cần thao tác thêm.', 'image' => $img('kho-06-issued-tab.png'), 'note' => null],
                    ['title' => 'A7 · Nhận hàng lỗi', 'desc' => 'Ở tab "Thu hồi / Hoàn kho", bấm ① "Thu hồi hàng lỗi" khi nhận lại thiết bị lỗi từ Kỹ thuật.', 'image' => $img('kho-07-faulty.png'), 'note' => null],
                    ['title' => 'A8 · Hoàn tất phần Kho', 'desc' => 'Sau khi thu hồi, phần việc của Kho cho phiếu đổi hàng đã xong — phiếu chờ Trưởng phòng hoàn tất.', 'image' => $img('kho-08-done.png'), 'note' => null],
                    ['title' => 'B1 · Xem yêu cầu linh kiện', 'desc' => 'Ở tab "Chờ Kho xử lý", các phiếu sửa chữa hiện số lượng linh kiện yêu cầu ngay trong popup Chi tiết.', 'image' => $img('kho-09-parts-detail.png'), 'note' => null],
                    ['title' => 'B2-3 · Kiểm tồn & Giữ số lượng', 'desc' => 'Bấm ① "Giữ linh kiện" — hệ thống tự kiểm tồn khả dụng, báo lỗi nếu không đủ.', 'image' => $img('kho-10-parts-reserve.png'), 'note' => null],
                    ['title' => 'B4 · Xuất', 'desc' => 'Ở tab "Đã giữ hàng", bấm ① "Xuất linh kiện" để giao cho Kỹ thuật.', 'image' => $img('kho-11-parts-issue.png'), 'note' => null],
                    ['title' => 'B5 · Theo dõi Kỹ thuật sử dụng', 'desc' => 'Số lượng "Dùng" cập nhật khi Kỹ thuật báo cáo tiến độ sửa chữa — xem trong popup Chi tiết.', 'image' => $img('kho-12-parts-used.png'), 'note' => null],
                    ['title' => 'B6-7 · Nhận hàng dư & Hoàn kho', 'desc' => 'Ở tab "Thu hồi / Hoàn kho", bấm ① "Hoàn linh kiện dư" để nhập lại phần chưa dùng vào tồn kho.', 'image' => $img('kho-13-parts-return.png'), 'note' => null],
                ],
            ],
        ];
    }

    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }
}
