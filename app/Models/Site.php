<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Công trình điện mặt trời (site) — thông tin dự án, hệ thống, tài chính và báo giá.
 */
class Site extends Model
{
    protected $table = 'sites';

    protected $fillable = [
        // Thông tin chung / Core
        'company_id',
        'created_by',
        'name',
        'status',
        'stage',
        'address',
        'contact_name',
        'contact_phone',
        'note',

        // Thông tin hệ thống / System info
        'system_kwp',
        'system_kw_ac',
        'system_type',
        'phase',
        'installed_at',
        'warranty_to',
        'technician_name',
        'monitoring_link',
        'monitoring_account',

        // Triển khai & bảo hành / Deployment & warranty
        'deployment_started_at',
        'completed_at',
        'warranty_reminder_1_at',
        'warranty_reminder_2_at',
        'warranty_reminder_3_at',
        'solar_panel_qty',
        'solar_panel_wp',
        'battery_kwh',

        // Tài chính / Finance
        'contract_amount',
        'contract_signed_at',
        'finance_note',
        'labor_cost',
        'transport_cost',
        'other_cost',
        'other_cost_note',

        // Báo giá / Quotation
        'quote_no',
        'quote_date',
        'quote_valid_until',
        'quote_status',
        'quote_customer_company',
        'quote_customer_email',
        'quote_customer_tax_code',
        'quote_config_summary',
        'quote_application_note',
        'quote_scope',
        'quote_commercial_terms',
        'quote_warranty_terms',
        'quote_om_terms',
        'quote_subtotal',
        'quote_discount_amount',
        'quote_vat_percent',
        'quote_vat_amount',
        'quote_grand_total',
        'quote_pdf_path',
        'quote_sent_at',
        'quote_approved_at',
    ];

    protected static function booted(): void
    {

        /* EGO_SITE_MODEL_CREATED_BY_START */
        static::creating(function ($site) {
            try {
                if (auth()->check()
                    && \Illuminate\Support\Facades\Schema::hasColumn($site->getTable(), 'created_by')
                    && empty($site->created_by)) {
                    $site->created_by = auth()->id();
                }
            } catch (\Throwable $e) {
                //
            }
        });
        /* EGO_SITE_MODEL_CREATED_BY_END */

        /* EGO_SITE_SALES_OWNER_START */
        static::creating(function ($site) {
            try {
                if (auth()->check()
                    && \Illuminate\Support\Facades\Schema::hasColumn($site->getTable(), 'created_by')
                    && empty($site->created_by)) {
                    $site->created_by = auth()->id();
                }
            } catch (\Throwable $e) {
                //
            }
        });

        static::addGlobalScope('ego_sales_only_own_sites', function (\Illuminate\Database\Eloquent\Builder $builder) {
            try {
                if (app()->runningInConsole() || ! auth()->check()) {
                    return;
                }

                $user = auth()->user();

                $roles = [];

                foreach (['role', 'type', 'position', 'department'] as $field) {
                    if (! empty($user->{$field})) {
                        $roles[] = strtolower((string) $user->{$field});
                    }
                }

                if (method_exists($user, 'getRoleNames')) {
                    foreach ($user->getRoleNames() as $roleName) {
                        $roles[] = strtolower((string) $roleName);
                    }
                }

                $roles = array_unique(array_filter($roles));

                $isAdmin = ((int) ($user->is_admin ?? 0) === 1)
                    || in_array('admin', $roles, true)
                    || in_array('administrator', $roles, true);

                $isAccounting = count(array_intersect($roles, [
                    'accounting', 'ketoan', 'ke_toan', 'kế toán', 'ketoan_truong',
                ])) > 0;

                $isTechnicalOrWarehouse = count(array_intersect($roles, [
                    'ky_thuat', 'kỹ thuật', 'technical', 'warehouse', 'kho', 'manager',
                ])) > 0;

                $isSales = count(array_intersect($roles, [
                    'sales', 'sale', 'sales_manager', 'kinh_doanh', 'kinh doanh', 'nhan_vien_kinh_doanh',
                ])) > 0;

                if ($isSales && ! $isAdmin && ! $isAccounting && ! $isTechnicalOrWarehouse) {
                    $table = $builder->getModel()->getTable();

                    if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'created_by')) {
                        $builder->where($table.'.created_by', $user->id);
                    }
                }
            } catch (\Throwable $e) {
                //
            }
        });
        /* EGO_SITE_SALES_OWNER_END */
    }
}
