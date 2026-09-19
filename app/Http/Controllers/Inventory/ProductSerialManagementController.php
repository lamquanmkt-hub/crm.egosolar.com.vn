<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Support\EgoCompanyLock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller quản lý và tra cứu serial/IMEI sản phẩm.
 */
class ProductSerialManagementController extends Controller
{
    private array $soldStates = ['sold', 'delivered', 'shipped', 'issued', 'out'];

    private array $stockStates = ['in_stock', 'returned', 'reserved', 'damaged'];

    private array $stateLabels = [
        'in_stock' => 'Đang tồn',
        'reserved' => 'Đã giữ',
        'sold' => 'Đã bán / đã xuất',
        'delivered' => 'Đã giao',
        'shipped' => 'Đã xuất',
        'issued' => 'Đã xuất',
        'returned' => 'Trả kho',
        'damaged' => 'Hư hỏng',
        'scrap' => 'Thanh lý',
        'unknown' => 'Chưa rõ',
    ];

    /**
     * Hiển thị trang quản lý serial với bộ lọc và thống kê.
     */
    public function index(Request $request)
    {
        $this->ensureSerialTables();

        $filters = $this->filters($request);

        $query = $this->baseQuery();
        $this->applyFilters($query, $filters);

        $serials = $query
            ->orderByDesc('su.id')
            ->paginate(50)
            ->withQueryString();

        $products = Schema::hasTable('crm_product_catalog')
            ? DB::table('crm_product_catalog')
                ->select('id', 'name', 'sku')
                ->orderBy('name')
                ->limit(2500)
                ->get()
            : collect();

        $warehouses = Schema::hasTable('crm_warehouses')
            ? DB::table('crm_warehouses')
                ->select('id', 'name', 'company_id')
                ->where('company_id', EgoCompanyLock::id())
                ->orderBy('name')
                ->get()
            : collect();

        $companies = Schema::hasTable('companies')
            ? DB::table('companies')
                ->select('id', 'name', 'code')
                ->where('id', EgoCompanyLock::id())
                ->orderBy('id')
                ->get()
            : collect();

        $stats = $this->stats();
        $stateLabels = $this->stateLabels;

        return view('products.serials', compact(
            'serials',
            'products',
            'warehouses',
            'companies',
            'stats',
            'filters',
            'stateLabels'
        ));
    }

    /**
     * Xuất danh sách serial theo bộ lọc ra file CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->ensureSerialTables();

        $filters = $this->filters($request);
        $query = $this->baseQuery();
        $this->applyFilters($query, $filters);

        $fileName = 'quan-ly-seri-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            echo "\xEF\xBB\xBF";

            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Serial / IMEI',
                'Sản phẩm',
                'SKU',
                'Trạng thái',
                'Kho hiện tại',
                'Công ty',
                'Khách hàng',
                'SĐT khách',
                'Đơn hàng',
                'Ngày bán',
                'Bảo hành từ',
                'Bảo hành đến',
                'Ghi chú',
            ]);

            $query->orderByDesc('su.id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->serial_code,
                        $row->product_name,
                        $row->product_sku,
                        $this->statusLabel((string) ($row->state ?? 'unknown')),
                        $row->warehouse_name,
                        $row->company_name,
                        $row->customer_name,
                        $row->customer_phone,
                        $row->order_code,
                        $row->sold_at,
                        $row->warranty_start_at,
                        $row->warranty_end_at,
                        $row->note ?: $row->warranty_note,
                    ]);
                }
            });

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Chặn 500 nếu thiếu bảng dữ liệu serial.
     */
    private function ensureSerialTables(): void
    {
        foreach (['crm_serial_units', 'crm_serial_identifiers', 'crm_serial_unit_identifiers', 'crm_serial_unit_states'] as $table) {
            abort_unless(Schema::hasTable($table), 500, 'Thiếu bảng dữ liệu serial: '.$table);
        }
    }

    /**
     * Đọc bộ lọc từ query string.
     */
    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'status' => trim((string) $request->query('status', '')),
            'product_id' => (int) $request->query('product_id', 0),
            'warehouse_id' => (int) $request->query('warehouse_id', 0),
            'company_id' => $request->query('company_id', '') === '' ? 0 : (int) $request->query('company_id', 0),
            'warranty' => trim((string) $request->query('warranty', '')),
        ];
    }

    /**
     * Dựng query gốc join serial với sản phẩm, kho, đơn hàng, khách và bảo hành.
     */
    private function baseQuery()
    {
        $primaryCodes = DB::table('crm_serial_unit_identifiers as sui')
            ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->where('sui.is_primary', 1)
            ->select('sui.serial_unit_id', 'si.code');

        $query = DB::table('crm_serial_units as su')
            ->leftJoinSub($primaryCodes, 'pc', fn ($join) => $join->on('pc.serial_unit_id', '=', 'su.id'))
            ->leftJoin('crm_product_catalog as p', 'p.id', '=', 'su.product_id')
            ->leftJoin('crm_serial_unit_states as st', 'st.serial_unit_id', '=', 'su.id')
            ->leftJoin('crm_warehouses as w', 'w.id', '=', 'st.warehouse_id')
            ->leftJoin('crm_order_item_serial_units as oisu', 'oisu.serial_unit_id', '=', 'su.id')
            ->leftJoin('crm_order_items as oi', 'oi.id', '=', 'oisu.order_item_id')
            ->leftJoin('crm_orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('crm_serial_warranties as wa', 'wa.serial_unit_id', '=', 'su.id');

        $companyParts = [];

        if (Schema::hasTable('companies')) {
            if (Schema::hasColumn('crm_serial_unit_states', 'company_id')) {
                $query->leftJoin('companies as cst', 'cst.id', '=', 'st.company_id');
                $companyParts[] = 'cst.name';
            }

            if (Schema::hasColumn('crm_product_catalog', 'company_id')) {
                $query->leftJoin('companies as cp', 'cp.id', '=', 'p.company_id');
                $companyParts[] = 'cp.name';
            }

            if (Schema::hasColumn('crm_warehouses', 'company_id')) {
                $query->leftJoin('companies as cw', 'cw.id', '=', 'w.company_id');
                $companyParts[] = 'cw.name';
            }
        }

        $leadJoined = false;
        if (Schema::hasTable('crm_leads') && Schema::hasColumn('crm_leads', 'customer_id')) {
            $query->leftJoin('crm_leads as l', 'l.id', '=', 'o.lead_id');
            $leadJoined = true;
        }

        $customerNameParts = [];
        $customerPhoneParts = [];

        if (Schema::hasTable('crm_customers')) {
            $query->leftJoin('crm_customers as cwa', 'cwa.id', '=', 'wa.customer_id');

            if (Schema::hasColumn('crm_customers', 'name')) {
                $customerNameParts[] = 'cwa.name';
            }

            if (Schema::hasColumn('crm_customers', 'phone')) {
                $customerPhoneParts[] = 'cwa.phone';
            }

            if ($leadJoined) {
                $query->leftJoin('crm_customers as clead', 'clead.id', '=', 'l.customer_id');

                if (Schema::hasColumn('crm_customers', 'name')) {
                    $customerNameParts[] = 'clead.name';
                }

                if (Schema::hasColumn('crm_customers', 'phone')) {
                    $customerPhoneParts[] = 'clead.phone';
                }
            }
        }

        if (Schema::hasColumn('crm_orders', 'receiver_name')) {
            $customerNameParts[] = 'o.receiver_name';
        }

        if (Schema::hasColumn('crm_orders', 'receiver_phone')) {
            $customerPhoneParts[] = 'o.receiver_phone';
        }

        $query->select([
            'su.id',
            'su.product_id',
            'su.warehouse_id as serial_warehouse_id',
            'su.created_at as serial_created_at',
            'pc.code as serial_code',
            'p.name as product_name',
            'p.sku as product_sku',
            'st.state',
            'st.warehouse_id',
            'st.synced_at',
            'st.note',
            'w.name as warehouse_name',
            'wa.customer_id as warranty_customer_id',
            'wa.order_id as warranty_order_id',
            'wa.order_item_id as warranty_order_item_id',
            'wa.sold_at',
            'wa.warranty_months',
            'wa.warranty_start_at',
            'wa.warranty_end_at',
            'wa.status as warranty_status',
            'wa.note as warranty_note',
            'o.id as order_id',
            'o.order_code',
            'o.order_date',
            'oi.id as order_item_id',
            'oisu.id as order_serial_link_id',
        ])
            ->selectRaw($companyParts ? 'COALESCE('.implode(',', $companyParts).') as company_name' : 'NULL as company_name')
            ->selectRaw($customerNameParts ? 'COALESCE('.implode(',', $customerNameParts).') as customer_name' : 'NULL as customer_name')
            ->selectRaw($customerPhoneParts ? 'COALESCE('.implode(',', $customerPhoneParts).') as customer_phone' : 'NULL as customer_phone');

        if (Schema::hasColumn('crm_serial_units', 'deleted_at')) {
            $query->whereNull('su.deleted_at');
        }

        return $query;
    }

    /**
     * Áp dụng bộ lọc tìm kiếm, trạng thái, kho, công ty, bảo hành vào query.
     */
    private function applyFilters($query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $q = $filters['q'];

            $query->where(function ($x) use ($q) {
                $x->where('pc.code', 'like', '%'.$q.'%')
                    ->orWhere('p.name', 'like', '%'.$q.'%')
                    ->orWhere('p.sku', 'like', '%'.$q.'%')
                    ->orWhere('w.name', 'like', '%'.$q.'%')
                    ->orWhere('o.order_code', 'like', '%'.$q.'%');

                if (Schema::hasTable('crm_customers')) {
                    if (Schema::hasColumn('crm_customers', 'name')) {
                        $x->orWhere('cwa.name', 'like', '%'.$q.'%');

                        if (Schema::hasTable('crm_leads') && Schema::hasColumn('crm_leads', 'customer_id')) {
                            $x->orWhere('clead.name', 'like', '%'.$q.'%');
                        }
                    }

                    if (Schema::hasColumn('crm_customers', 'phone')) {
                        $x->orWhere('cwa.phone', 'like', '%'.$q.'%');

                        if (Schema::hasTable('crm_leads') && Schema::hasColumn('crm_leads', 'customer_id')) {
                            $x->orWhere('clead.phone', 'like', '%'.$q.'%');
                        }
                    }
                }
            });
        }

        if ($filters['product_id'] > 0) {
            $query->where('su.product_id', $filters['product_id']);
        }

        if ($filters['warehouse_id'] > 0) {
            $query->where(function ($x) use ($filters) {
                $x->where('st.warehouse_id', $filters['warehouse_id'])
                    ->orWhere('su.warehouse_id', $filters['warehouse_id'])
                    ->orWhere('oi.warehouse_id', $filters['warehouse_id']);
            });
        }

        if ($filters['company_id'] > 0) {
            $companyId = (int) $filters['company_id'];

            $query->where(function ($x) use ($companyId) {
                $hasAnyCompanyColumn = false;

                if (Schema::hasColumn('crm_serial_unit_states', 'company_id')) {
                    $hasAnyCompanyColumn = true;
                    $x->orWhere('st.company_id', $companyId)
                        ->orWhereNull('st.company_id');
                }

                if (Schema::hasColumn('crm_product_catalog', 'company_id')) {
                    $hasAnyCompanyColumn = true;
                    $x->orWhere('p.company_id', $companyId)
                        ->orWhereNull('p.company_id');
                }

                if (Schema::hasColumn('crm_warehouses', 'company_id')) {
                    $hasAnyCompanyColumn = true;
                    $x->orWhere('w.company_id', $companyId)
                        ->orWhereNull('w.company_id');
                }

                if (Schema::hasColumn('crm_orders', 'company_id')) {
                    $hasAnyCompanyColumn = true;
                    $x->orWhere('o.company_id', $companyId)
                        ->orWhereNull('o.company_id');
                }

                // Nếu serial cũ chưa có cột/cấu trúc công ty thì cho hiện toàn bộ,
                // vì đây là dữ liệu nhập kho cũ.
                if (! $hasAnyCompanyColumn) {
                    $x->whereRaw('1 = 1');
                }
            });
        }

        match ($filters['status']) {
            'stock' => $query->where(function ($x) {
                $x->whereIn('st.state', $this->stockStates)
                    ->orWhere(function ($y) {
                        $y->whereNotNull('st.warehouse_id')
                            ->where(function ($z) {
                                $z->whereNull('st.state')->orWhere('st.state', '')->orWhere('st.state', 'unknown');
                            });
                    });
            }),
            'available' => $query->where(function ($x) {
                $x->where('st.state', 'in_stock')
                    ->orWhere(function ($y) {
                        $y->whereNotNull('st.warehouse_id')
                            ->where(function ($z) {
                                $z->whereNull('st.state')->orWhere('st.state', '')->orWhere('st.state', 'unknown');
                            });
                    });
            }),
            'sold' => $query->where(function ($x) {
                $x->whereIn('st.state', $this->soldStates)
                    ->orWhereNotNull('oisu.id')
                    ->orWhereNotNull('wa.order_id')
                    ->orWhereNotNull('wa.customer_id')
                    ->orWhereNotNull('wa.sold_at');
            }),
            'exported' => $query->where(function ($x) {
                $x->whereIn('st.state', $this->soldStates)
                    ->orWhereNotNull('oisu.id')
                    ->orWhereNotNull('wa.order_id')
                    ->orWhere(function ($y) {
                        $y->whereNull('st.warehouse_id')->whereNotNull('st.serial_unit_id');
                    });
            }),
            'unknown' => $query->where(function ($x) {
                $x->whereNull('st.state')->orWhere('st.state', '')->orWhere('st.state', 'unknown');
            }),
            default => null,
        };

        match ($filters['warranty']) {
            'active' => $query->whereNotNull('wa.warranty_end_at')->whereDate('wa.warranty_end_at', '>=', now()->toDateString()),
            'expired' => $query->whereNotNull('wa.warranty_end_at')->whereDate('wa.warranty_end_at', '<', now()->toDateString()),
            'none' => $query->where(function ($x) {
                $x->whereNull('wa.id')->orWhereNull('wa.warranty_end_at');
            }),
            default => null,
        };
    }

    /**
     * Tính số liệu thống kê tổng quan về serial.
     */
    private function stats(): array
    {
        $totalQuery = DB::table('crm_serial_units');

        if (Schema::hasColumn('crm_serial_units', 'deleted_at')) {
            $totalQuery->whereNull('deleted_at');
        }

        $soldByState = DB::table('crm_serial_unit_states')
            ->whereIn('state', $this->soldStates)
            ->count();

        $soldByOrder = Schema::hasTable('crm_order_item_serial_units')
            ? DB::table('crm_order_item_serial_units')->distinct('serial_unit_id')->count('serial_unit_id')
            : 0;

        return [
            'total' => (int) $totalQuery->count(),
            'in_stock' => (int) DB::table('crm_serial_unit_states')
                ->where('state', 'in_stock')
                ->whereNotNull('warehouse_id')
                ->count(),
            'stock_all' => (int) DB::table('crm_serial_unit_states')
                ->whereIn('state', $this->stockStates)
                ->whereNotNull('warehouse_id')
                ->count(),
            'sold' => max((int) $soldByState, (int) $soldByOrder),
            'unknown' => (int) DB::table('crm_serial_unit_states')
                ->where(function ($q) {
                    $q->whereNull('state')->orWhere('state', 'unknown');
                })
                ->count(),
            'warranty_active' => Schema::hasTable('crm_serial_warranties')
                ? (int) DB::table('crm_serial_warranties')
                    ->whereNotNull('warranty_end_at')
                    ->whereDate('warranty_end_at', '>=', now()->toDateString())
                    ->count()
                : 0,
        ];
    }

    /**
     * Lấy nhãn tiếng Việt của trạng thái serial.
     */
    private function statusLabel(string $state): string
    {
        return $this->stateLabels[$state] ?? ($state ?: 'Chưa rõ');
    }
}
