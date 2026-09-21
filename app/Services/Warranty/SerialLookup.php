<?php

declare(strict_types=1);

namespace App\Services\Warranty;

use App\Support\SchemaCache;
use App\Support\Warranty\WarrantyFlow;
use Illuminate\Support\Facades\DB;

/**
 * Tra cứu SERIAL → tự truy ngược toàn bộ dữ liệu CRM: sản phẩm, khách hàng, đơn hàng gốc, công trình,
 * bảo hành, trạng thái/kho, lịch sử đổi/sửa và phiếu đang mở. Chỉ ĐỌC.
 */
final class SerialLookup
{
    /** @return array<string,mixed>|null null nếu serial không tồn tại trong CRM */
    public function describe(string $code): ?array
    {
        $code = trim($code);
        if ($code === '' || ! $this->ready()) {
            return null;
        }

        $q = DB::table('crm_serial_units as su')
            ->join('crm_serial_unit_identifiers as sui', function ($j): void {
                $j->on('sui.serial_unit_id', '=', 'su.id')->where('sui.is_primary', 1);
            })
            ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->leftJoin('crm_product_catalog as p', 'p.id', '=', 'su.product_id')
            ->leftJoin('crm_serial_unit_states as sus', 'sus.serial_unit_id', '=', 'su.id')
            ->leftJoin('crm_warehouses as w', 'w.id', '=', 'sus.warehouse_id')
            ->where('si.code', $code)
            ->select(['su.id as unit_id', 'su.product_id', 'si.code as serial_code', 'p.name as product_name', 'p.sku', 'sus.state', 'sus.company_id as state_company', 'w.name as warehouse_name']);

        $hasWarranty = SchemaCache::hasTable('crm_serial_warranties');
        if ($hasWarranty) {
            $q->leftJoin('crm_serial_warranties as wa', 'wa.serial_unit_id', '=', 'su.id')
                ->leftJoin('crm_orders as o', 'o.id', '=', 'wa.order_id')
                ->leftJoin('crm_leads as l', 'l.id', '=', 'o.lead_id')
                ->leftJoin('crm_customers as c', 'c.id', '=', DB::raw('COALESCE(wa.customer_id, l.customer_id)'))
                ->leftJoin('sites as s', 's.id', '=', 'wa.site_id')
                ->addSelect(['wa.status as w_status', 'wa.warranty_start_at', 'wa.warranty_end_at', 'wa.sold_at', 'wa.order_id', 'wa.site_id',
                    'o.order_code', 'o.order_date', 'o.company_id as order_company',
                    'c.id as customer_id', 'c.name as customer_name', 'c.phone as customer_phone', 'c.company_id as customer_company',
                    's.name as site_name', 's.project_code', 's.address as site_address']);
        }

        $r = $q->first();
        if (! $r) {
            return null;
        }

        $end = trim((string) ($r->warranty_end_at ?? ''));
        $status = strtolower((string) ($r->w_status ?? ''));
        $active = $status === 'active' && ($end === '' || $end >= now()->toDateString());
        $label = match (true) {
            $active => 'Còn bảo hành',
            $status === 'replaced' => 'Đã được thay thế',
            $status === '' => 'Chưa có hồ sơ bảo hành',
            default => 'Hết bảo hành',
        };

        $history = DB::table('crm_serial_warranty_claims')->where('serial_unit_id', $r->unit_id)->whereNull('deleted_at')
            ->orderByDesc('id')->limit(15)->get(['id', 'claim_code', 'claim_type', 'status', 'created_at'])
            ->map(fn ($h) => [
                'id' => (int) $h->id, 'code' => $h->claim_code, 'type' => $h->claim_type,
                'type_label' => ['replacement' => 'Đổi hàng bảo hành', 'paid_repair' => 'Sửa chữa tính phí', 'warranty' => 'Bảo hành', 'incident' => 'Sự cố', 'inspection' => 'Kiểm tra'][$h->claim_type] ?? $h->claim_type,
                'status' => $h->status, 'date' => substr((string) $h->created_at, 0, 10),
            ])->all();

        $open = DB::table('crm_serial_warranty_claims')->where('serial_unit_id', $r->unit_id)->whereNull('deleted_at')
            ->whereNotIn('status', WarrantyFlow::TERMINAL)->orderByDesc('id')->first(['id', 'claim_code', 'claim_type', 'status']);

        $replacedBy = SchemaCache::hasTable('warranty_serial_replacements')
            ? DB::table('warranty_serial_replacements')->where('old_serial_unit_id', $r->unit_id)->orderByDesc('id')->first(['new_serial_code', 'replaced_at', 'claim_id']) : null;
        $replaces = SchemaCache::hasTable('warranty_serial_replacements')
            ? DB::table('warranty_serial_replacements')->where('new_serial_unit_id', $r->unit_id)->orderByDesc('id')->first(['old_serial_code', 'replaced_at', 'claim_id']) : null;

        return [
            'serial_unit_id' => (int) $r->unit_id,
            'serial_code' => (string) $r->serial_code,
            'product_id' => (int) $r->product_id,
            'product_name' => (string) ($r->product_name ?: 'Chưa xác định sản phẩm'),
            'sku' => (string) ($r->sku ?? ''),
            'state' => (string) ($r->state ?: 'unknown'),
            'warehouse_name' => (string) ($r->warehouse_name ?? ''),
            'sold_at' => $r->sold_at ?? null,
            'customer_id' => ! empty($r->customer_id) ? (int) $r->customer_id : null,
            'customer_name' => (string) ($r->customer_name ?? ''),
            'customer_phone' => (string) ($r->customer_phone ?? ''),
            'order_id' => ! empty($r->order_id) ? (int) $r->order_id : null,
            'order_code' => (string) ($r->order_code ?? ''),
            'order_date' => $r->order_date ?? null,
            'site_id' => ! empty($r->site_id) ? (int) $r->site_id : null,
            'site_name' => (string) ($r->site_name ?? ''),
            'site_code' => (string) ($r->project_code ?? ''),
            'site_address' => (string) ($r->site_address ?? ''),
            'warranty_status' => $status,
            'warranty_start_at' => $r->warranty_start_at ?? null,
            'warranty_end_at' => $r->warranty_end_at ?? null,
            'warranty_active' => $active,
            'warranty_label' => $label,
            'company_ids' => array_values(array_unique(array_filter([
                (int) ($r->state_company ?? 0), (int) ($r->order_company ?? 0), (int) ($r->customer_company ?? 0),
            ]))),
            'history' => $history,
            'open_claim' => $open ? ['id' => (int) $open->id, 'code' => $open->claim_code, 'type' => $open->claim_type, 'status' => $open->status] : null,
            'replaced_by' => $replacedBy ? ['serial' => $replacedBy->new_serial_code, 'date' => substr((string) $replacedBy->replaced_at, 0, 10)] : null,
            'replaces' => $replaces ? ['serial' => $replaces->old_serial_code, 'date' => substr((string) $replaces->replaced_at, 0, 10)] : null,
        ];
    }

    /** Serial thuộc công ty khác công ty đang làm việc? */
    public static function belongsToOtherCompany(array $info, int $currentCompanyId): bool
    {
        if ($currentCompanyId <= 0) {
            return false;
        }

        foreach ($info['company_ids'] as $id) {
            if ((int) $id !== $currentCompanyId) {
                return true;
            }
        }

        return false;
    }

    private function ready(): bool
    {
        foreach (['crm_serial_units', 'crm_serial_unit_identifiers', 'crm_serial_identifiers'] as $t) {
            if (! SchemaCache::hasTable($t)) {
                return false;
            }
        }

        return true;
    }
}
