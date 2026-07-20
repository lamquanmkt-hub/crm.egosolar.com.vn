<?php

declare(strict_types=1);

namespace App\Services\Sales;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Cấu hình KPI sales: chỉ tiêu mặc định, bật/tắt từng chỉ tiêu, các chỉ tiêu
 * mở rộng do người dùng tự định nghĩa và mục tiêu (target) theo kỳ.
 *
 * Cấu hình lưu ở file JSON trên storage (không phải DB) — giữ nguyên khi tách
 * để không đổi nguồn dữ liệu production.
 *
 * Tách nguyên trạng từ SalesCommissionController (P1c refactor) — hành vi chốt
 * bằng SalesCommissionPagesCharacterizationTest.
 */
class SalesKpiSettingsService
{
    /** Chỉ tiêu mặc định: số bài đăng/tháng. */
    private const KPI_POSTS_TARGET = 100;

    /** Chỉ tiêu mặc định: số cuộc gọi/tháng. */
    private const KPI_CALLS_TARGET = 60;

    /** Chỉ tiêu mặc định: số data công ty khai thác/tháng. */
    private const KPI_COMPANY_DATA_TARGET = 20;

    /** Tiền phạt mặc định cho mỗi đơn vị chỉ tiêu còn thiếu (VNĐ). */
    private const KPI_PENALTY_PER_MISSING = 100000;

    /**
     * Trả về cấu hình KPI sales mặc định (chỉ tiêu, bật/tắt module, mức phạt).
     */
    public function defaultSalesKpiSettings(): array
    {
        return [
            'posts_target' => self::KPI_POSTS_TARGET,
            'calls_target' => self::KPI_CALLS_TARGET,
            'company_data_target' => self::KPI_COMPANY_DATA_TARGET,
            'penalty_per_missing' => self::KPI_PENALTY_PER_MISSING,

            'enable_posts' => 1,
            'enable_calls' => 1,
            'enable_company_data' => 1,
            'enable_follow_up' => 1,

            'enable_new_leads' => 0,
            'new_leads_target' => 10,
            'enable_quotes' => 0,
            'quotes_target' => 5,
            'enable_customer_care' => 0,
            'customer_care_target' => 20,
            'enable_meetings' => 0,
            'meetings_target' => 2,
            'enable_zalo_messages' => 0,
            'zalo_messages_target' => 40,
            'enable_debt_follow' => 0,
            'debt_follow_target' => 10,
            'enable_order_follow' => 0,
            'order_follow_target' => 8,
            'enable_technical_coordination' => 0,
            'technical_coordination_target' => 5,
            'enable_overdue_tasks' => 0,
            'overdue_tasks_target' => 0,
            'enable_training' => 0,
            'training_target' => 1,
            'enable_quality_score' => 0,
            'quality_score_target' => 90,
            'enable_revenue_pipeline' => 0,
            'revenue_pipeline_target' => 100000000,

            'enable_warnings' => 1,
            'enable_penalty' => 1,
            'enable_manager_approval' => 0,
            'enable_callio_sync' => 0,
            'enable_lock_after_days' => 0,
            'lock_after_days' => 2,
            'settings_note' => '',
        ];
    }

    /**
     * Đường dẫn file JSON lưu cấu hình KPI sales.
     */
    public function salesKpiSettingsPath(): string
    {
        return storage_path('app/sales_kpi_settings.json');
    }

    /**
     * Đọc cấu hình KPI sales từ file JSON, gộp với giá trị mặc định.
     */
    public function salesKpiSettings(): array
    {
        $settings = $this->defaultSalesKpiSettings();
        $path = $this->salesKpiSettingsPath();

        if (is_file($path)) {
            $json = json_decode((string) file_get_contents($path), true);
            if (is_array($json)) {
                $settings = array_merge($settings, array_intersect_key($json, $settings));
            }
        }

        return $settings;
    }

    /**
     * Kiểm tra một hạng mục KPI có được bật trong cấu hình hay không.
     */
    public function kpiSettingEnabled(array $settings, string $key): bool
    {
        return (int) ($settings[$key] ?? 0) === 1;
    }

    /**
     * Danh sách định nghĩa các chỉ số KPI mở rộng (lead mới, báo giá, Zalo...).
     */
    public function salesKpiExtraMetricDefinitions(): array
    {
        return [
            ['metric_key' => 'new_leads', 'enabled_key' => 'enable_new_leads', 'target_key' => 'new_leads_target', 'title' => 'Lead mới', 'unit' => 'lead/ngày', 'icon' => 'bi-person-plus', 'description' => 'Lead mới tự khai thác hoặc được phân bổ.'],
            ['metric_key' => 'quotes', 'enabled_key' => 'enable_quotes', 'target_key' => 'quotes_target', 'title' => 'Báo giá', 'unit' => 'báo giá/ngày', 'icon' => 'bi-file-earmark-text', 'description' => 'Báo giá đã gửi khách trong ngày.'],
            ['metric_key' => 'customer_care', 'enabled_key' => 'enable_customer_care', 'target_key' => 'customer_care_target', 'title' => 'Chăm sóc khách', 'unit' => 'khách/ngày', 'icon' => 'bi-heart', 'description' => 'Khách được chăm sóc, nhắc lại, hỏi nhu cầu.'],
            ['metric_key' => 'meetings', 'enabled_key' => 'enable_meetings', 'target_key' => 'meetings_target', 'title' => 'Meeting / lịch hẹn', 'unit' => 'lịch/ngày', 'icon' => 'bi-calendar2-check', 'description' => 'Lịch tư vấn, khảo sát, demo hoặc gặp khách.'],
            ['metric_key' => 'zalo_messages', 'enabled_key' => 'enable_zalo_messages', 'target_key' => 'zalo_messages_target', 'title' => 'Tin nhắn Zalo', 'unit' => 'tin/ngày', 'icon' => 'bi-send', 'description' => 'Tin nhắn chăm sóc, tư vấn, follow qua Zalo.'],
            ['metric_key' => 'debt_follow', 'enabled_key' => 'enable_debt_follow', 'target_key' => 'debt_follow_target', 'title' => 'Follow công nợ', 'unit' => 'case/ngày', 'icon' => 'bi-wallet2', 'description' => 'Case công nợ hoặc nhắc thanh toán đã xử lý.'],
            ['metric_key' => 'order_follow', 'enabled_key' => 'enable_order_follow', 'target_key' => 'order_follow_target', 'title' => 'Bám đơn hàng', 'unit' => 'đơn/ngày', 'icon' => 'bi-box-seam', 'description' => 'Đơn hàng đang theo dõi xử lý, giao hàng, xuất kho.'],
            ['metric_key' => 'technical_coordination', 'enabled_key' => 'enable_technical_coordination', 'target_key' => 'technical_coordination_target', 'title' => 'Phối hợp kỹ thuật', 'unit' => 'việc/ngày', 'icon' => 'bi-tools', 'description' => 'Việc phối hợp kỹ thuật, khảo sát, cấu hình.'],
            ['metric_key' => 'overdue_tasks', 'enabled_key' => 'enable_overdue_tasks', 'target_key' => 'overdue_tasks_target', 'title' => 'Task quá hạn tối đa', 'unit' => 'task', 'icon' => 'bi-alarm', 'description' => 'Số task quá hạn tối đa cho phép.'],
            ['metric_key' => 'training', 'enabled_key' => 'enable_training', 'target_key' => 'training_target', 'title' => 'Học sản phẩm', 'unit' => 'mục/ngày', 'icon' => 'bi-mortarboard', 'description' => 'Mục học sản phẩm, chính sách, kịch bản tư vấn.'],
            ['metric_key' => 'quality_score', 'enabled_key' => 'enable_quality_score', 'target_key' => 'quality_score_target', 'title' => 'Điểm chất lượng', 'unit' => 'điểm', 'icon' => 'bi-stars', 'description' => 'Điểm chất lượng tư vấn, ghi chú CRM, chăm sóc.'],
            ['metric_key' => 'revenue_pipeline', 'enabled_key' => 'enable_revenue_pipeline', 'target_key' => 'revenue_pipeline_target', 'title' => 'Pipeline doanh số', 'unit' => 'VNĐ', 'icon' => 'bi-graph-up-arrow', 'description' => 'Giá trị cơ hội/báo giá đang theo đuổi.'],
        ];
    }

    /**
     * Chuẩn hóa mảng chỉ số KPI mở rộng về số nguyên không âm.
     */
    public function normalizeExtraMetrics(array $input): array
    {
        $extra = [];

        foreach ($this->salesKpiExtraMetricDefinitions() as $module) {
            $key = $module['metric_key'];
            $extra[$key] = max(0, (int) ($input[$key] ?? 0));
        }

        return $extra;
    }

    /**
     * Giải mã cột extra_metrics (JSON) của một bản ghi KPI thành mảng chuẩn hóa.
     */
    public function decodeExtraMetrics($entry): array
    {
        $raw = $entry->extra_metrics ?? null;

        if (! $raw) {
            return $this->normalizeExtraMetrics([]);
        }

        if (is_array($raw)) {
            return $this->normalizeExtraMetrics($raw);
        }

        $decoded = json_decode((string) $raw, true);

        return $this->normalizeExtraMetrics(is_array($decoded) ? $decoded : []);
    }

    /**
     * Tổng hợp chỉ tiêu KPI và trạng thái bật/tắt để truyền ra view.
     */
    public function salesKpiTargets(): array
    {
        $settings = $this->salesKpiSettings();

        return [
            'posts' => (int) $settings['posts_target'],
            'calls' => (int) $settings['calls_target'],
            'company_data' => (int) $settings['company_data_target'],
            'penalty_per_missing' => (int) $settings['penalty_per_missing'],
            'enabled' => [
                'posts' => $this->kpiSettingEnabled($settings, 'enable_posts'),
                'calls' => $this->kpiSettingEnabled($settings, 'enable_calls'),
                'company_data' => $this->kpiSettingEnabled($settings, 'enable_company_data'),
                'follow_up' => $this->kpiSettingEnabled($settings, 'enable_follow_up'),
            ],
            'settings' => $settings,
        ];
    }
}
