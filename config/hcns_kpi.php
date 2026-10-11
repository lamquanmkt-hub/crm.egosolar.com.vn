<?php

/*
 * Nguồn KPI HCNS (docs/projects/kpi-automation/hcns-kpi).
 *
 * Danh mục nghiệp vụ và các PHƯƠNG ÁN quy tắc đo nguồn. Giá trị 'draft_default' chỉ dùng cho bản xem
 * trước khi chưa có phiên bản quy tắc được người có quyền duyệt (H-D01…H-D03 còn mở) — không phải
 * chính sách đã chốt, không sinh điểm chính thức hay tiền lương.
 */
return [
    // HC09 — loại thủ tục hành chính/pháp lý được giao (chỉ việc gắn loại này mới vào KPI HC09).
    'procedure_types' => [
        'business_license' => 'Giấy phép / đăng ký kinh doanh',
        'legal_record' => 'Hồ sơ pháp lý doanh nghiệp',
        'permit' => 'Giấy phép chuyên ngành / PCCC / xây dựng',
        'contract_admin' => 'Hợp đồng thuê, dịch vụ văn phòng',
        'seal_authorization' => 'Con dấu, ủy quyền, chữ ký số',
        'state_report' => 'Báo cáo, thủ tục với cơ quan nhà nước',
        'other_admin' => 'Thủ tục hành chính khác',
    ],

    // HC07 — phân loại công văn/giấy tờ do người xử lý chọn; người kiểm xác nhận đúng/sai.
    'document_classes' => [
        'incoming_external' => 'Công văn đến (bên ngoài)',
        'outgoing_external' => 'Công văn đi (bên ngoài)',
        'internal' => 'Văn bản nội bộ',
        'contract' => 'Hợp đồng / phụ lục',
        'legal' => 'Giấy tờ pháp lý',
        'hr_record' => 'Hồ sơ nhân sự',
        'finance_record' => 'Chứng từ tài chính',
        'other' => 'Khác',
    ],

    // Ba mục người kiểm đánh giá cho một hồ sơ HC07.
    'document_checks' => [
        'classification' => 'Phân loại đúng',
        'routing' => 'Luân chuyển đúng nơi nhận',
        'completeness' => 'Đủ giấy tờ / tệp theo yêu cầu',
    ],

    // HC06 — ba nhóm nguồn giữ riêng; cách gộp thành một tỷ lệ là quyết định H-D04 (chưa chốt).
    'asset_groups' => [
        'allocation' => 'Cấp phát VPP theo yêu cầu',
        'inventory' => 'Kiểm kê tài sản theo kỳ',
        'incident' => 'Xử lý sự cố văn phòng',
    ],

    // Người kiểm xác nhận phiếu cấp phát VPP theo dõi KPI.
    'allocation_checks' => [
        'timestamps' => 'Mốc nhận yêu cầu và giao đúng thực tế',
        'quantity' => 'Đúng loại, đủ số lượng',
        'receipt' => 'Người nhận đã ký/xác nhận nhận hàng',
    ],

    // Người kiểm xác nhận đợt kiểm kê tài sản.
    'inventory_checks' => [
        'results' => 'Kết quả từng tài sản đúng thực tế',
        'evidence' => 'Có biên bản/ảnh kiểm kê xác nhận',
    ],

    'inventory_results' => [
        'ok' => 'Đủ, đúng vị trí/người giữ',
        'moved' => 'Đổi vị trí/người giữ',
        'damaged' => 'Hỏng/cần sửa',
        'missing' => 'Không tìm thấy',
    ],

    // HC01 — người kiểm xác nhận một lượt tuyển.
    'hire_checks' => [
        'link' => 'Đúng yêu cầu tuyển · ứng viên · tài khoản nhân viên',
        'received' => 'Mốc HCNS tiếp nhận yêu cầu đúng thực tế',
        'start_date' => 'Ngày nhận việc thực tế có chứng cứ',
    ],

    // HC03 — người kiểm xác nhận kết quả thử việc của một lượt tuyển.
    'probation_checks' => [
        'dates' => 'Ngày bắt đầu/kết thúc thử việc đúng',
        'outcome' => 'Kết quả thử việc / ngày nghỉ thực đúng',
        'evidence' => 'Có quyết định/biên bản/đơn nghỉ làm chứng cứ',
    ],

    // HC02 — người kiểm xác nhận một khoản chi phân bổ cho đợt tuyển.
    'cost_checks' => [
        'document' => 'Chứng từ đã thực chi, đúng nội dung tuyển dụng',
        'allocation' => 'Số phân bổ cho đợt tuyển đúng, không trùng khoản khác',
    ],

    'hire_statuses' => [
        'pending' => 'Chờ nhận việc',
        'started' => 'Đã nhận việc thực tế',
        'no_show' => 'Không nhận việc',
        'cancelled' => 'Đã hủy lượt',
    ],

    'probation_outcomes' => [
        'in_probation' => 'Đang thử việc',
        'passed' => 'Đạt thử việc',
        'left' => 'Nghỉ trong thử việc',
    ],

    'probation_left_types' => [
        'resigned' => 'Tự nghỉ',
        'terminated' => 'Công ty chấm dứt (không đạt)',
        'other' => 'Lý do khác',
    ],

    // HC04 — các khoản của một dòng bảng lương HR có thể kiểm riêng (đơn vị “khoản”); fields = cột hr_payroll_lines.
    'payroll_components' => [
        'paid_days' => ['label' => 'Ngày công tính lương', 'fields' => ['paid_days', 'standard_days']],
        'base_pay' => ['label' => 'Lương chính theo công', 'fields' => ['base_salary', 'base_pay']],
        'kpi_pay' => ['label' => 'Lương KPI / hiệu suất', 'fields' => ['kpi_salary', 'kpi_rate', 'kpi_pay']],
        'allowances' => ['label' => 'Thưởng QĐ, phụ cấp (đi lại, ăn, điện thoại)', 'fields' => ['decision_bonus_pay', 'travel_pay', 'meal_pay', 'phone_pay']],
        'trip_ot' => ['label' => 'Công tác tỉnh, tăng ca', 'fields' => ['trip_allowance', 'ot_amount']],
        'other_income' => ['label' => 'Khoản thu nhập khác', 'fields' => ['other_income']],
        'insurance' => ['label' => 'Bảo hiểm người lao động', 'fields' => ['insurance_base', 'insurance_employee']],
        'pit' => ['label' => 'Thuế TNCN', 'fields' => ['taxable_after_deduction', 'pit']],
        'deductions' => ['label' => 'Tạm ứng, phạt, trừ khác', 'fields' => ['advance', 'late_penalty', 'other_deduction']],
        'net_salary' => ['label' => 'Tổng thu nhập, thực lĩnh', 'fields' => ['gross_income', 'net_salary']],
    ],

    'payroll_item_results' => [
        'ok' => 'Đúng — không có lỗi',
        'error' => 'Có lỗi',
        'undetermined' => 'Chưa xác định',
    ],

    'payroll_error_types' => [
        'attendance' => 'Sai ngày công / chấm công',
        'salary_profile' => 'Sai lương chính / hồ sơ lương',
        'kpi' => 'Sai KPI / hệ số KPI',
        'allowance' => 'Sai thưởng / phụ cấp / khoản khác',
        'insurance' => 'Sai bảo hiểm',
        'tax' => 'Sai thuế TNCN',
        'deduction' => 'Sai tạm ứng / khấu trừ',
        'data_entry' => 'Nhập liệu / công thức',
        'other' => 'Lỗi khác',
    ],

    'payroll_error_resolutions' => [
        'open' => 'Chưa xử lý',
        'fixed' => 'Đã sửa trên bảng lương',
        'next_period' => 'Điều chỉnh ở kỳ sau',
        'no_change' => 'Không sửa (có giải trình)',
    ],

    // Người kiểm độc lập xác nhận một đợt đối soát HC04.
    'payroll_audit_checks' => [
        'scope' => 'Kỳ, phạm vi và các phần đã kiểm đúng, đủ',
        'results' => 'Kết quả đúng/có lỗi từng phần đúng thực tế',
        'errors' => 'Lỗi ghi đúng khoản/loại, có chứng cứ/nguồn và cách xử lý',
    ],

    'rules' => [
        'admin_deadline' => [
            'period_by' => [
                'options' => ['due_month' => 'Theo tháng của hạn hoàn thành'],
                'draft_default' => 'due_month',
            ],
            'measure_point' => [
                'options' => [
                    'accepted_submission' => 'Lần gửi được người kiểm chấp nhận',
                    'first_submission' => 'Lần gửi đầu tiên',
                ],
                'draft_default' => 'accepted_submission',
            ],
            'deadline_basis' => [
                'options' => [
                    'current_if_changed_before_original' => 'Hạn mới nếu đổi trước hạn gốc, ngược lại giữ hạn gốc',
                    'current' => 'Hạn hiện tại',
                    'original' => 'Hạn gốc lúc giao',
                ],
                'draft_default' => 'current_if_changed_before_original',
            ],
            'date_only_due' => [
                'options' => [
                    'end_of_day' => 'Hạn chỉ có ngày tính đến 23:59:59 ngày đó',
                    'as_entered' => 'Giữ nguyên 00:00 ngày đó',
                ],
                'draft_default' => 'end_of_day',
            ],
            'cancelled' => [
                'options' => [
                    'exclude_if_before_due' => 'Hủy trước hạn: loại; hủy sau hạn khi chưa nộp: tính trễ',
                    'exclude' => 'Loại mọi việc đã hủy',
                    'count_as_late' => 'Việc đã hủy tính không đúng hạn',
                ],
                'draft_default' => 'exclude_if_before_due',
            ],
        ],
        'assets' => [
            'aggregation' => [
                'options' => [
                    'unset' => 'Chưa chốt cách gộp — chỉ hiện số đo từng nhóm, chưa tính điểm',
                    'pooled' => 'Gộp chung mọi hồ sơ của ba nhóm',
                    'average_groups' => 'Trung bình tỷ lệ của các nhóm có phát sinh',
                ],
                'draft_default' => 'unset',
            ],
            'period_by' => [
                'options' => ['due_month' => 'Theo tháng của hạn (kiểm kê: theo kỳ kiểm kê)'],
                'draft_default' => 'due_month',
            ],
            'incident_measure' => [
                'options' => [
                    'final_resolution' => 'Sự cố: lần xử lý xong được nghiệm thu',
                    'first_resolution' => 'Sự cố: lần báo xử lý xong đầu tiên',
                ],
                'draft_default' => 'final_resolution',
            ],
            'incident_wait' => [
                'options' => [
                    'include' => 'Sự cố: giờ liên tục, không trừ thời gian chờ',
                    'exclude_approval_wait' => 'Sự cố: trừ thời gian chờ Giám đốc duyệt chi',
                ],
                'draft_default' => 'include',
            ],
        ],
        // HC01–HC03: ngưỡng, cohort và cách quy đổi chưa chốt (H-D02, H-D08) → mặc định chỉ số đo/xem trước.
        'recruit_time' => [
            'adoption' => [
                'options' => [
                    'preview_only' => 'Chưa chốt ngưỡng/cohort/quy đổi — chỉ số đo, xem trước',
                    'source' => 'Lấy điểm chính thức từ nguồn',
                ],
                'draft_default' => 'preview_only',
            ],
            'period_by' => [
                'options' => ['start_month' => 'Bình quân số ngày của các lượt nhận việc thực tế trong tháng'],
                'draft_default' => 'start_month',
            ],
        ],
        'recruit_cost' => [
            'adoption' => [
                'options' => [
                    'preview_only' => 'Chưa chốt ngưỡng/cohort/quy đổi — chỉ số đo, xem trước',
                    'source' => 'Lấy điểm chính thức từ nguồn',
                ],
                'draft_default' => 'preview_only',
            ],
            'period_by' => [
                'options' => ['calendar_month' => 'Chi phí thực chi trong tháng ÷ số lượt nhận việc đã xác nhận trong tháng'],
                'draft_default' => 'calendar_month',
            ],
        ],
        'probation_quit' => [
            'adoption' => [
                'options' => [
                    'preview_only' => 'Chưa chốt ngưỡng/cohort/quy đổi — chỉ số đo, xem trước',
                    'source' => 'Lấy điểm chính thức từ nguồn',
                ],
                'draft_default' => 'preview_only',
            ],
            'cohort' => [
                'options' => ['probation_end_month' => 'Người kết thúc thử việc trong tháng (theo ngày nghỉ thực nếu nghỉ sớm)'],
                'draft_default' => 'probation_end_month',
            ],
            'left_types' => [
                'options' => [
                    'all' => 'Mọi trường hợp nghỉ trong thử việc',
                    'resigned_only' => 'Chỉ trường hợp tự nghỉ',
                ],
                'draft_default' => 'all',
            ],
        ],
        // HC04: đơn vị kiểm, kỳ, độ phủ và lỗi đã sửa chưa chốt (H-D05) → mặc định chỉ số đo/xem trước.
        'payroll_accuracy' => [
            'adoption' => [
                'options' => [
                    'preview_only' => 'Chưa chốt đơn vị/kỳ/quy đổi — chỉ số đo, xem trước',
                    'source' => 'Lấy điểm chính thức từ nguồn',
                ],
                'draft_default' => 'preview_only',
            ],
            'period_by' => [
                'options' => [
                    'next_month' => 'Kỳ KPI = tháng sau kỳ lương (lương tháng 9 lập trong tháng 10 → KPI tháng 10)',
                    'payroll_month' => 'Kỳ KPI = đúng tháng của kỳ lương',
                ],
                'draft_default' => 'next_month',
            ],
            'unit' => [
                'options' => [
                    'line' => 'Đơn vị = một dòng lương (một nhân viên trong kỳ); dòng có lỗi tính 1 phần sai',
                    'component' => 'Đơn vị = một khoản lương đã kiểm; nhiều lỗi cùng khoản tính 1 phần sai',
                ],
                'draft_default' => 'line',
            ],
            'coverage' => [
                'options' => [
                    'full' => 'Phải kết luận mọi dòng trong phạm vi đợt; còn dòng chưa kiểm/chưa xác định → chưa đủ',
                    'sample' => 'Chỉ tính phần đã kiểm (kiểm mẫu); dòng chưa kiểm không vào mẫu số',
                ],
                'draft_default' => 'full',
            ],
            'fixed_errors' => [
                'options' => [
                    'count_all' => 'Mọi lỗi đã xác nhận đều tính sai, kể cả đã sửa trước khi chốt lương',
                    'exclude_fixed_before_approval' => 'Lỗi đã sửa trên bảng lương trước khi duyệt lương không tính',
                ],
                'draft_default' => 'count_all',
            ],
        ],
        'documents' => [
            'period_by' => [
                'options' => ['first_submission_month' => 'Theo tháng gửi kiểm tra lần đầu'],
                'draft_default' => 'first_submission_month',
            ],
            'pass_rule' => [
                'options' => ['all_checks' => 'Đạt khi cả 3 mục kiểm tra đạt'],
                'draft_default' => 'all_checks',
            ],
        ],
    ],
];
