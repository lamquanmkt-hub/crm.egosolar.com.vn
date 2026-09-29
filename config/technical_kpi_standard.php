<?php

/*
|--------------------------------------------------------------------------
| CHUẨN KPI KỸ SƯ THI CÔNG SOLAR ÁP MÁI HỘ GIA ĐÌNH (tài liệu Ban lãnh đạo)
|--------------------------------------------------------------------------
|
| Nguồn: KPI_Ky_Su_Solar_Ho_Gia_Dinh.xlsx, sheet "KPI Solar Residential".
| SHA-256 file gốc tại thời điểm chép: ab2d0d70a8228091a4beca36d17981ee9279653da29962ba59bc7041816b62f1
|
| Nội dung dưới đây chép NGUYÊN VĂN từ file (ô B, C, F, G). KHÔNG sửa trọng số
| hay diễn giải lại khi chưa có văn bản xác nhận mới của Ban lãnh đạo.
|
| Lưu ý đã ghi nhận trong file gốc:
|   - Dòng 6: ô trọng số C8 để TRỐNG; tên "THỜI GIAN XỬ LÝ BẢO HÀNH SP" nhưng
|     cột F8/G8 lại mô tả vật tư tiêu hao. Vì vậy tiêu chí 6 ở trạng thái
|     "Chờ xác nhận nghiệp vụ": không có trọng số, không tự động tính điểm.
|   - Tổng trọng số C9 = SUM(C3:C8) = 100% (chỉ từ 5 tiêu chí đầu).
|
*/

return [
    'source' => [
        'file' => 'KPI_Ky_Su_Solar_Ho_Gia_Dinh.xlsx',
        'sheet' => 'KPI Solar Residential',
        'title' => 'BẢNG TIÊU CHÍ ĐÁNH GIÁ KPI KỸ SƯ THI CÔNG SOLAR ÁP MÁI HỘ GIA ĐÌNH',
        'sha256' => 'ab2d0d70a8228091a4beca36d17981ee9279653da29962ba59bc7041816b62f1',
    ],

    /*
     * weight: số thập phân đúng như ô C; null = ô trống trong file.
     * pending_confirmation: true = chưa được lãnh đạo xác nhận, không tính.
     * source_code: điểm liên kết dữ liệu CRM (xem KpiStandardEvaluator).
     */
    'criteria' => [
        [
            'no' => 1,
            'code' => 'timeline',
            'name' => 'Tiến độ hoàn thành lắp đặt hệ thống',
            'weight' => 0.30,
            'target' => '100% đúng hạn theo hợp đồng (Thường từ 2 - 5 ngày/hệ thống)',
            'measure' => '(Số hệ thống hoàn thành đúng tiến độ / Tổng số hệ thống bàn giao) x 100%',
            'pending_confirmation' => false,
        ],
        [
            'no' => 2,
            'code' => 'quality',
            'name' => 'Chất lượng thi công & Thẩm mỹ',
            'weight' => 0.25,
            'target' => 'Nghiệm thu đạt ngay lần đầu, không bị chủ nhà phàn nàn',
            'measure' => 'Bảo đảm: Khung giàn phẳng, dây điện đi ống gọn gàng, inverter lắp nơi thoáng mát, bắn keo chống dột 100%',
            'pending_confirmation' => false,
        ],
        [
            'no' => 3,
            'code' => 'survey',
            'name' => 'Khảo sát kỹ thuật & Khối lượng',
            'weight' => 0.15,
            'target' => 'Sai số vật tư thực tế so với khảo sát bóc tách < 3%',
            'measure' => 'Đo đạc chính xác hướng mái, góc nghiêng, bóng râm và chiều dài đường dây trước khi mang vật tư đến',
            'threshold_percent' => 3.0,
            'pending_confirmation' => false,
        ],
        [
            'no' => 4,
            'code' => 'hse',
            'name' => 'An toàn lao động (HSE) & Vệ sinh',
            'weight' => 0.15,
            'target' => '0 tai nạn; 100% dọn dẹp sạch sẽ mái và hiên nhà sau thi công',
            'measure' => 'Đo đai an toàn khi lên mái; không làm xước/vỡ ngói, tôn của chủ nhà; thu dọn toàn bộ rác cáp, bao bì',
            'pending_confirmation' => false,
        ],
        [
            'no' => 5,
            'code' => 'evn_app',
            'name' => 'Hỗ trợ thủ tục EVN & Cài đặt App',
            'weight' => 0.15,
            'target' => '100% hệ thống được đấu nối thử và cài App theo dõi cho chủ nhà',
            'measure' => 'Đấu nối đúng kỹ thuật để EVN kiểm tra dễ dàng; hướng dẫn chủ nhà sử dụng App giám sát sản lượng thành thạo',
            'pending_confirmation' => false,
        ],
        [
            'no' => 6,
            'code' => 'warranty',
            'name' => 'THỜI GIAN XỬ LÝ BẢO HÀNH SP',
            'weight' => null,
            'target' => 'Khối lượng định mức (kèm biên độ hao hụt cho phép  là 3%).',
            'measure' => 'Vật tư thực tế tiêu hao = Số lượng xuất kho - Số lượng dư thừa thu hồi về kho nguyên vẹn.',
            'pending_confirmation' => true,
            'pending_reason' => 'File chuẩn chưa có trọng số (ô C8 trống) và tên tiêu chí (thời gian xử lý bảo hành) không khớp mục tiêu/công thức (vật tư tiêu hao). Cần Ban lãnh đạo xác nhận tên, trọng số và công thức trước khi tính điểm.',
        ],
    ],

    /* Ô B12:B15 — quy đổi điểm KPI ra tiền thưởng hiệu suất. min là ngưỡng dưới (bao gồm). */
    'bonus_tiers' => [
        ['min' => 1.00, 'label' => 'Vượt chỉ tiêu', 'range' => 'KPI ≥ 100%', 'payout' => '110% – 120% tiền lương KPI', 'tone' => 'excellent'],
        ['min' => 0.90, 'label' => 'Đạt yêu cầu', 'range' => '90% ≤ KPI < 100%', 'payout' => '100% tiền lương KPI', 'tone' => 'good'],
        ['min' => 0.75, 'label' => 'Cần cải thiện', 'range' => '75% ≤ KPI < 90%', 'payout' => '80% tiền lương KPI', 'tone' => 'improve'],
        ['min' => 0.00, 'label' => 'Không đạt', 'range' => 'KPI < 75%', 'payout' => 'Không nhận tiền lương KPI tháng đó, bị phê bình/đào tạo lại', 'tone' => 'fail'],
    ],

    /* Ô B16 — hiển thị làm ghi chú, KHÔNG tự động trừ điểm. */
    'serious_violation_note' => 'Đối với lỗi vi phạm nghiêm trọng như không đeo dây an toàn khi lên mái, tổng điểm KPI tháng đó có thể bị trừ thẳng từ 10 - 20 điểm',

    /*
     * Ô G11:I17 — "Cách quy đổi sang Điểm KPI thành phần vật tư tiêu hao".
     * Bảng nằm cạnh mô tả vật tư tiêu hao của dòng 6 → chỉ hiển thị tham khảo,
     * không gán cho tiêu chí nào cho tới khi được xác nhận.
     */
    'material_scale' => [
        ['range' => '0% (Không hao hụt / Tiết kiệm)', 'rate' => 1.20, 'label' => 'Xuất sắc (Cộng điểm thưởng)'],
        ['range' => 'Từ > 0% đến ≤ 2%', 'rate' => 1.00, 'label' => 'Đạt yêu cầu (Mục tiêu chuẩn)'],
        ['range' => 'Từ > 2% đến ≤ 4%', 'rate' => 0.85, 'label' => 'Khá (Chớm vượt định mức)'],
        ['range' => 'Từ > 4% đến ≤ 6%', 'rate' => 0.70, 'label' => 'Trung bình (Lãng phí vật tư)'],
        ['range' => 'Tên > 6% hoặc làm mất đồ', 'rate' => 0.00, 'label' => 'Không đạt'],
    ],
];
