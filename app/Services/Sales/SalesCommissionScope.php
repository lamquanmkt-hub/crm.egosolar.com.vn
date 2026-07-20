<?php

declare(strict_types=1);

namespace App\Services\Sales;

/**
 * Quy ước phạm vi dữ liệu hoa hồng sales dùng chung giữa controller và exporter.
 */
final class SalesCommissionScope
{
    /**
     * Nhân viên bị loại khỏi mọi báo cáo hoa hồng/KPI theo yêu cầu nghiệp vụ.
     */
    public const EXCLUDED_SALES_NAME = 'Ngọc Trân';
}
