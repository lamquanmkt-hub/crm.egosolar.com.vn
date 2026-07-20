<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controller quản lý serial sản phẩm và bảo hành (nhập/xuất kho, chuyển kho, tra cứu, claim).
 */
class SerialWarrantyController extends Controller
{
    /**
     * Trang danh sách serial đã bán/giao kèm bộ lọc, thống kê, sự kiện và claim gần đây.
     */
    public function index(Request $request)
    {
        $this->ensureBaseTables();

        $q = trim((string) $request->query('q', ''));
        $state = trim((string) $request->query('state', ''));
        $productId = (int) $request->query('product_id', 0);

        $primaryCodes = DB::table('crm_serial_unit_identifiers as sui')
            ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->where('sui.is_primary', 1)
            ->select('sui.serial_unit_id', 'si.code');

        $serials = DB::table('crm_serial_units as su')
            ->leftJoinSub($primaryCodes, 'pc', fn ($join) => $join->on('pc.serial_unit_id', '=', 'su.id'))
            ->leftJoin('crm_product_catalog as p', 'p.id', '=', 'su.product_id')
            ->leftJoin('crm_serial_unit_states as st', 'st.serial_unit_id', '=', 'su.id')
            ->whereIn('st.state', ['sold', 'delivered'])
            ->leftJoin('crm_warehouses as w', 'w.id', '=', 'st.warehouse_id')
            ->leftJoin('crm_serial_warranties as wa', 'wa.serial_unit_id', '=', 'su.id')
            ->leftJoin('crm_customers as c', 'c.id', '=', 'wa.customer_id')
            ->leftJoin('crm_orders as o', 'o.id', '=', 'wa.order_id')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($x) use ($q) {
                    $x->where('pc.code', 'like', '%'.$q.'%')
                        ->orWhere('p.name', 'like', '%'.$q.'%')
                        ->orWhere('p.sku', 'like', '%'.$q.'%')
                        ->orWhere('c.name', 'like', '%'.$q.'%')
                        ->orWhere('c.phone', 'like', '%'.$q.'%')
                        ->orWhere('o.order_code', 'like', '%'.$q.'%');
                });
            })
            ->when($state !== '', fn ($query) => $query->where('st.state', $state))
            ->when($productId > 0, fn ($query) => $query->where('su.product_id', $productId))
            ->select([
                'su.id',
                'su.product_id',
                'pc.code as serial_code',
                'p.name as product_name',
                'p.sku as product_sku',
                'st.state',
                'st.warehouse_id',
                'w.name as warehouse_name',
                'wa.customer_id',
                'c.name as customer_name',
                'c.phone as customer_phone',
                'wa.order_id',
                'wa.site_id', // EGO_SERIAL_SITE_SAVE_PATCH
                'o.order_code',
                'wa.sold_at',
                'wa.warranty_months',
                'wa.warranty_start_at',
                'wa.warranty_end_at',
                'wa.note',
            ])
            ->orderByDesc('su.id')
            ->paginate(30)
            ->withQueryString();

        $products = DB::table('crm_product_catalog')
            ->select('id', 'name', 'sku', 'is_serialized')
            ->orderBy('name')
            ->limit(1500)
            ->get();

        $warehouses = DB::table('crm_warehouses')
            ->select('id', 'name', 'company_id')
            ->orderBy('name')
            ->get();

        $customers = DB::table('crm_customers')
            ->select('id', 'name', 'phone')
            ->orderByDesc('id')
            ->limit(800)
            ->get();

        $orders = DB::table('crm_orders')
            ->select('id', 'order_code', 'order_date', 'lead_id')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $events = Schema::hasTable('crm_serial_warranty_events')
            ? DB::table('crm_serial_warranty_events as e')
                ->leftJoin('crm_warehouses as fw', 'fw.id', '=', 'e.from_warehouse_id')
                ->leftJoin('crm_warehouses as tw', 'tw.id', '=', 'e.to_warehouse_id')
                ->leftJoin('crm_customers as c', 'c.id', '=', 'e.customer_id')
                ->leftJoin('crm_orders as o', 'o.id', '=', 'e.order_id')
                ->select('e.*', 'fw.name as from_warehouse_name', 'tw.name as to_warehouse_name', 'c.name as customer_name', 'o.order_code')
                ->orderByDesc('e.id')
                ->limit(20)
                ->get()
            : collect();

        $claims = Schema::hasTable('crm_serial_warranty_claims')
            ? DB::table('crm_serial_warranty_claims as cl')
                ->leftJoin('crm_serial_units as su', 'su.id', '=', 'cl.serial_unit_id')
                ->leftJoin('crm_product_catalog as p', 'p.id', '=', 'su.product_id')
                ->leftJoin('crm_customers as c', 'c.id', '=', 'cl.customer_id')
                ->select('cl.*', 'p.name as product_name', 'c.name as customer_name')
                ->orderByDesc('cl.id')
                ->limit(20)
                ->get()
            : collect();

        $stats = [
            'total' => DB::table('crm_serial_unit_states')->whereIn('state', ['sold', 'delivered'])->count(),
            'in_stock' => DB::table('crm_serial_unit_states')->where('state', 'in_stock')->count(),
            'sold' => DB::table('crm_serial_unit_states')->where('state', 'sold')->count(),
            'returned' => DB::table('crm_serial_unit_states')->where('state', 'returned')->count(),
            'warranty_active' => DB::table('crm_serial_warranties')
                ->whereNotNull('warranty_end_at')
                ->whereDate('warranty_end_at', '>=', now()->toDateString())
                ->count(),
        ];

        return view('serial-warranty.index', compact(
            'serials',
            'products',
            'warehouses',
            'customers',
            'orders',
            'events',
            'claims',
            'stats',
            'q',
            'state',
            'productId'
        ));
    }

    /**
     * Nhập kho danh sách serial mới cho một sản phẩm.
     */
    public function receive(Request $request)
    {
        $this->ensureBaseTables();

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'warehouse_id' => ['required', 'integer', 'min:1'],
            'serials' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $product = DB::table('crm_product_catalog')->where('id', $data['product_id'])->first();
        $warehouse = DB::table('crm_warehouses')->where('id', $data['warehouse_id'])->first();

        if (! $product || ! $warehouse) {
            return back()->withInput()->with('error', 'Sản phẩm hoặc kho không hợp lệ.');
        }

        $codes = $this->parseSerials($data['serials']);
        if (! $codes) {
            return back()->withInput()->with('error', 'Vui lòng nhập ít nhất 1 mã serial.');
        }

        $existing = DB::table('crm_serial_identifiers')->whereIn('code', $codes)->pluck('code')->all();
        if ($existing) {
            return back()->withInput()->with('error', 'Không nhập được vì serial đã tồn tại: '.implode(', ', $existing));
        }

        DB::transaction(function () use ($codes, $data, $warehouse) {
            if (Schema::hasColumn('crm_product_catalog', 'is_serialized')) {
                DB::table('crm_product_catalog')->where('id', $data['product_id'])->update([
                    'is_serialized' => 1,
                    'updated_at' => now(),
                ]);
            }

            foreach ($codes as $code) {
                $unitId = DB::table('crm_serial_units')->insertGetId([
                    'product_id' => $data['product_id'],
                    'warehouse_id' => $data['warehouse_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $identifierId = DB::table('crm_serial_identifiers')->insertGetId([
                    'type' => 'serial',
                    'code' => $code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('crm_serial_unit_identifiers')->insert([
                    'serial_unit_id' => $unitId,
                    'serial_identifier_id' => $identifierId,
                    'is_primary' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $stateData = [
                    'warehouse_id' => $data['warehouse_id'],
                    'state' => 'in_stock',
                    'synced_at' => now(),
                    'note' => request()->input('note', request()->input('notes', request()->input('ghi_chu'))), // EGO_SERIAL_NOTE_SAVE_PATCH
                ];

                if (Schema::hasColumn('crm_serial_unit_states', 'company_id')) {
                    $stateData['company_id'] = $warehouse->company_id ?? null;
                }

                DB::table('crm_serial_unit_states')->updateOrInsert(
                    ['serial_unit_id' => $unitId],
                    $stateData
                );

                $this->logEvent($unitId, $code, 'receive', null, 'in_stock', null, $data['warehouse_id'], null, null, $data['note'] ?? null);
            }
        });

        return redirect()->route('serial-warranty.index')->with('success', 'Đã nhập kho '.count($codes).' serial.');
    }

    /**
     * Xuất bán serial: đổi trạng thái sold, gán đơn hàng và kích hoạt bảo hành.
     */
    public function issue(Request $request)
    {
        $this->ensureBaseTables();

        $data = $request->validate([
            'serials' => ['required', 'string'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'order_id' => ['nullable', 'integer', 'min:1'],
            'site_id' => ['nullable', 'integer', 'min:1'], // EGO_SERIAL_SITE_SAVE_PATCH
            'sold_at' => ['nullable', 'date'],
            'warranty_months' => ['required', 'integer', 'min:1', 'max:240'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $codes = $this->parseSerials($data['serials']);
        if (! $codes) {
            return back()->withInput()->with('error', 'Vui lòng nhập serial cần xuất.');
        }

        $rows = $this->serialRows($codes);
        $foundCodes = $rows->pluck('serial_code')->all();
        $missing = array_values(array_diff($codes, $foundCodes));

        if ($missing) {
            return back()->withInput()->with('error', 'Không tìm thấy serial trong hệ thống: '.implode(', ', $missing));
        }

        $bad = $rows->filter(fn ($r) => $r->state !== 'in_stock')->map(fn ($r) => $r->serial_code.' ('.($r->state ?: 'unknown').')')->values()->all();
        if ($bad) {
            return back()->withInput()->with('error', 'Chỉ được xuất serial đang trong kho. Serial không hợp lệ: '.implode(', ', $bad));
        }

        $orderId = (int) ($data['order_id'] ?? 0);
        $customerId = (int) ($data['customer_id'] ?? 0);
        $siteId = (int) ($data['site_id'] ?? 0); // EGO_SERIAL_SITE_SAVE_PATCH

        if ($orderId > 0) {
            $check = $this->validateSerialsAgainstOrder($orderId, $rows);
            if ($check !== true) {
                return back()->withInput()->with('error', $check);
            }

            if ($customerId <= 0) {
                $customerId = $this->customerIdFromOrder($orderId);
            }
        }

        $soldAt = ! empty($data['sold_at'])
            ? Carbon::parse($data['sold_at'])->startOfDay()
            : now()->startOfDay();

        DB::transaction(function () use ($rows, $data, $soldAt, $customerId, $orderId, $siteId) {
            foreach ($rows as $row) {
                DB::table('crm_serial_unit_states')->updateOrInsert(
                    ['serial_unit_id' => $row->serial_unit_id],
                    [
                        'warehouse_id' => null,
                        'state' => 'sold',
                        'synced_at' => now(),
                        'note' => request()->input('note', request()->input('notes', request()->input('ghi_chu'))), // EGO_SERIAL_NOTE_SAVE_PATCH
                    ]
                );

                if (Schema::hasColumn('crm_serial_units', 'warehouse_id')) {
                    DB::table('crm_serial_units')->where('id', $row->serial_unit_id)->update([
                        'warehouse_id' => null,
                        'updated_at' => now(),
                    ]);
                }

                $orderItemId = null;

                if ($orderId > 0 && Schema::hasTable('crm_order_items')) {
                    $orderItemId = DB::table('crm_order_items')
                        ->where('order_id', $orderId)
                        ->where('product_id', $row->product_id)
                        ->orderBy('id')
                        ->value('id');

                    if ($orderItemId && Schema::hasTable('crm_order_item_serial_units')) {
                        DB::table('crm_order_item_serial_units')->updateOrInsert(
                            ['serial_unit_id' => $row->serial_unit_id],
                            [
                                'order_item_id' => $orderItemId,
                                'updated_at' => now(),
                                'created_at' => now(),
                            ]
                        );
                    }
                }

                DB::table('crm_serial_warranties')->updateOrInsert(
                    ['serial_unit_id' => $row->serial_unit_id],
                    [
                        'customer_id' => $customerId > 0 ? $customerId : null,
                        'order_id' => $orderId > 0 ? $orderId : null,
                        'site_id' => isset($siteId) && $siteId > 0 ? $siteId : null, // EGO_SERIAL_SITE_SAVE_PATCH
                        'order_item_id' => $orderItemId,
                        'sold_at' => $soldAt->toDateString(),
                        'warranty_months' => (int) $data['warranty_months'],
                        'warranty_start_at' => $soldAt->toDateString(),
                        'warranty_end_at' => $soldAt->copy()->addMonths((int) $data['warranty_months'])->toDateString(),
                        'status' => 'active',
                        'note' => $data['note'] ?? null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                $this->logEvent($row->serial_unit_id, $row->serial_code, 'issue', 'in_stock', 'sold', $row->warehouse_id, null, $customerId, $orderId, $data['note'] ?? null);
            }
        });

        return redirect()->route('serial-warranty.index')->with('success', 'Đã xuất '.count($codes).' serial và kích hoạt bảo hành.');
    }

    /**
     * Chuyển kho các serial đang tồn kho sang kho khác.
     */
    public function transfer(Request $request)
    {
        $data = $request->validate([
            'serials' => ['required', 'string'],
            'to_warehouse_id' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $codes = $this->parseSerials($data['serials']);
        $rows = $this->serialRows($codes);

        $missing = array_values(array_diff($codes, $rows->pluck('serial_code')->all()));
        if ($missing) {
            return back()->withInput()->with('error', 'Không tìm thấy serial: '.implode(', ', $missing));
        }

        $bad = $rows->filter(fn ($r) => $r->state !== 'in_stock')->pluck('serial_code')->all();
        if ($bad) {
            return back()->withInput()->with('error', 'Chỉ chuyển kho serial đang trong kho. Sai: '.implode(', ', $bad));
        }

        DB::transaction(function () use ($rows, $data) {
            foreach ($rows as $row) {
                DB::table('crm_serial_unit_states')->updateOrInsert(
                    ['serial_unit_id' => $row->serial_unit_id],
                    [
                        'warehouse_id' => $data['to_warehouse_id'],
                        'state' => 'in_stock',
                        'synced_at' => now(),
                        'note' => request()->input('note', request()->input('notes', request()->input('ghi_chu'))), // EGO_SERIAL_NOTE_SAVE_PATCH
                    ]
                );

                if (Schema::hasColumn('crm_serial_units', 'warehouse_id')) {
                    DB::table('crm_serial_units')->where('id', $row->serial_unit_id)->update([
                        'warehouse_id' => $data['to_warehouse_id'],
                        'updated_at' => now(),
                    ]);
                }

                $this->logEvent($row->serial_unit_id, $row->serial_code, 'transfer', 'in_stock', 'in_stock', $row->warehouse_id, $data['to_warehouse_id'], null, null, $data['note'] ?? null);
            }
        });

        return back()->with('success', 'Đã chuyển kho '.count($codes).' serial.');
    }

    /**
     * Nhận trả hàng: đưa serial về kho với trạng thái returned.
     */
    public function returnStock(Request $request)
    {
        $data = $request->validate([
            'serials' => ['required', 'string'],
            'warehouse_id' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $codes = $this->parseSerials($data['serials']);
        $rows = $this->serialRows($codes);

        $missing = array_values(array_diff($codes, $rows->pluck('serial_code')->all()));
        if ($missing) {
            return back()->withInput()->with('error', 'Không tìm thấy serial: '.implode(', ', $missing));
        }

        DB::transaction(function () use ($rows, $data) {
            foreach ($rows as $row) {
                DB::table('crm_serial_unit_states')->updateOrInsert(
                    ['serial_unit_id' => $row->serial_unit_id],
                    [
                        'warehouse_id' => $data['warehouse_id'],
                        'state' => 'returned',
                        'synced_at' => now(),
                        'note' => request()->input('note', request()->input('notes', request()->input('ghi_chu'))), // EGO_SERIAL_NOTE_SAVE_PATCH
                    ]
                );

                if (Schema::hasColumn('crm_serial_units', 'warehouse_id')) {
                    DB::table('crm_serial_units')->where('id', $row->serial_unit_id)->update([
                        'warehouse_id' => $data['warehouse_id'],
                        'updated_at' => now(),
                    ]);
                }

                DB::table('crm_serial_warranties')->where('serial_unit_id', $row->serial_unit_id)->update([
                    'status' => 'returned',
                    'updated_at' => now(),
                ]);

                $this->logEvent($row->serial_unit_id, $row->serial_code, 'return', $row->state, 'returned', $row->warehouse_id, $data['warehouse_id'], $row->customer_id ?? null, $row->order_id ?? null, $data['note'] ?? null);
            }
        });

        return back()->with('success', 'Đã nhận trả hàng '.count($codes).' serial.');
    }

    /**
     * Tiếp nhận yêu cầu bảo hành (claim) cho một serial.
     */
    public function claim(Request $request)
    {
        $data = $request->validate([
            'serial_code' => ['required', 'string', 'max:255'],
            'issue_description' => ['required', 'string', 'max:5000'],
            'status' => ['nullable', 'string', 'max:40'],
        ]);

        $row = $this->serialRows([$data['serial_code']])->first();
        if (! $row) {
            return back()->withInput()->with('error', 'Serial không tồn tại trong hệ thống.');
        }

        DB::table('crm_serial_warranty_claims')->insert([
            'serial_unit_id' => $row->serial_unit_id,
            'serial_code' => $row->serial_code,
            'customer_id' => $row->customer_id ?? null,
            'order_id' => $row->order_id ?? null,
            'status' => $data['status'] ?: 'received',
            'received_at' => now()->toDateString(),
            'issue_description' => $data['issue_description'],
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logEvent($row->serial_unit_id, $row->serial_code, 'warranty_claim', $row->state, $row->state, $row->warehouse_id, $row->warehouse_id, $row->customer_id ?? null, $row->order_id ?? null, $data['issue_description']);

        return back()->with('success', 'Đã tiếp nhận bảo hành cho serial '.$row->serial_code.'.');
    }

    /**
     * Chuyển hướng tra cứu serial về trang danh sách với từ khóa.
     */
    public function lookup(Request $request)
    {
        return redirect()->route('serial-warranty.index', ['q' => trim((string) $request->input('code', ''))]);
    }

    /**
     * Truy vấn thông tin serial (trạng thái, kho, bảo hành) theo danh sách mã.
     */
    private function serialRows(array $codes)
    {
        $primaryCodes = DB::table('crm_serial_unit_identifiers as sui')
            ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->where('sui.is_primary', 1)
            ->select('sui.serial_unit_id', 'si.code');

        return DB::table('crm_serial_units as su')
            ->joinSub($primaryCodes, 'pc', fn ($join) => $join->on('pc.serial_unit_id', '=', 'su.id'))
            ->leftJoin('crm_serial_unit_states as st', 'st.serial_unit_id', '=', 'su.id')
            ->leftJoin('crm_serial_warranties as wa', 'wa.serial_unit_id', '=', 'su.id')
            ->whereIn('pc.code', $codes)
            ->select('su.id as serial_unit_id', 'su.product_id', 'pc.code as serial_code', 'st.state', 'st.warehouse_id', 'wa.customer_id', 'wa.order_id')
            ->get();
    }

    /**
     * Kiểm tra serial xuất có khớp sản phẩm và số lượng của đơn hàng không.
     *
     * @return true|string true nếu hợp lệ, chuỗi thông báo lỗi nếu không.
     */
    private function validateSerialsAgainstOrder(int $orderId, $rows)
    {
        if (! Schema::hasTable('crm_order_items')) {
            return true;
        }

        $orderProducts = DB::table('crm_order_items')
            ->where('order_id', $orderId)
            ->select('product_id', DB::raw('SUM(quantity) as qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        foreach ($rows->groupBy('product_id') as $productId => $productRows) {
            if (! isset($orderProducts[$productId])) {
                return 'Serial sản phẩm ID '.$productId.' không thuộc đơn hàng đã chọn.';
            }

            $allowed = (float) $orderProducts[$productId];

            $orderItemIds = DB::table('crm_order_items')
                ->where('order_id', $orderId)
                ->where('product_id', $productId)
                ->pluck('id');

            $already = Schema::hasTable('crm_order_item_serial_units')
                ? DB::table('crm_order_item_serial_units')->whereIn('order_item_id', $orderItemIds)->count()
                : 0;

            if ($already + $productRows->count() > $allowed) {
                return 'Số serial sản phẩm ID '.$productId.' vượt số lượng trong đơn. Đơn có '.$allowed.', đã gán '.$already.', đang xuất thêm '.$productRows->count().'.';
            }
        }

        return true;
    }

    /**
     * Tách chuỗi nhập thành mảng mã serial duy nhất (phân tách bởi xuống dòng, phẩy, chấm phẩy).
     */
    private function parseSerials(string $text): array
    {
        $parts = preg_split('/[\r\n,;]+/', $text);
        $codes = [];
        foreach ($parts as $part) {
            $code = trim((string) $part);
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * Lấy customer_id từ đơn hàng (trực tiếp hoặc qua lead).
     */
    private function customerIdFromOrder(int $orderId): int
    {
        $order = DB::table('crm_orders')->where('id', $orderId)->first();
        if (! $order) {
            return 0;
        }

        if (isset($order->customer_id) && (int) $order->customer_id > 0) {
            return (int) $order->customer_id;
        }

        if (! empty($order->lead_id) && Schema::hasTable('crm_leads')) {
            return (int) DB::table('crm_leads')->where('id', (int) $order->lead_id)->value('customer_id');
        }

        return 0;
    }

    /**
     * Ghi log sự kiện serial vào bảng crm_serial_warranty_events nếu bảng tồn tại.
     */
    private function logEvent($unitId, $code, $type, $fromState, $toState, $fromWh, $toWh, $customerId, $orderId, $note): void
    {
        if (! Schema::hasTable('crm_serial_warranty_events')) {
            return;
        }

        DB::table('crm_serial_warranty_events')->insert([
            'serial_unit_id' => $unitId,
            'serial_code' => $code,
            'event_type' => $type,
            'from_state' => $fromState,
            'to_state' => $toState,
            'from_warehouse_id' => $fromWh,
            'to_warehouse_id' => $toWh,
            'customer_id' => $customerId ?: null,
            'order_id' => $orderId ?: null,
            'created_by' => auth()->id(),
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Đảm bảo các bảng serial/bảo hành cốt lõi tồn tại, thiếu thì abort 500.
     */
    private function ensureBaseTables(): void
    {
        foreach (['crm_product_catalog', 'crm_warehouses', 'crm_serial_units', 'crm_serial_identifiers', 'crm_serial_unit_identifiers', 'crm_serial_unit_states', 'crm_serial_warranties'] as $table) {
            abort_unless(Schema::hasTable($table), 500, 'Thiếu bảng hệ thống: '.$table);
        }
    }

    /**
     * API thêm serial mới cho sản phẩm từ màn sửa sản phẩm (trả JSON).
     */
    public function productAddSerials(\Illuminate\Http\Request $request, $product)
    {
        $data = $request->validate([
            'serials' => ['required', 'string'],
            'warehouse_id' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $productId = (int) $product;

        $codes = collect(preg_split('/[\r\n,;]+/', (string) $data['serials']))
            ->map(fn ($x) => trim((string) $x))
            ->filter()
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'Vui lòng nhập serial.'], 422);
        }

        $exists = \Illuminate\Support\Facades\DB::table('crm_serial_identifiers')
            ->whereIn('code', $codes)
            ->pluck('code')
            ->all();

        if (! empty($exists)) {
            return response()->json(['ok' => false, 'message' => 'Serial đã tồn tại: '.implode(', ', $exists)], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($codes, $productId, $data) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('crm_product_catalog', 'is_serialized')) {
                \Illuminate\Support\Facades\DB::table('crm_product_catalog')
                    ->where('id', $productId)
                    ->update([
                        'is_serialized' => 1,
                        'updated_at' => now(),
                    ]);
            }

            foreach ($codes as $code) {
                $unitData = [
                    'product_id' => $productId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (\Illuminate\Support\Facades\Schema::hasColumn('crm_serial_units', 'warehouse_id')) {
                    $unitData['warehouse_id'] = (int) $data['warehouse_id'];
                }

                $unitId = \Illuminate\Support\Facades\DB::table('crm_serial_units')->insertGetId($unitData);

                $identifierId = \Illuminate\Support\Facades\DB::table('crm_serial_identifiers')->insertGetId([
                    'type' => 'serial',
                    'code' => $code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                \Illuminate\Support\Facades\DB::table('crm_serial_unit_identifiers')->insert([
                    'serial_unit_id' => $unitId,
                    'serial_identifier_id' => $identifierId,
                    'is_primary' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                \Illuminate\Support\Facades\DB::table('crm_serial_unit_states')->updateOrInsert(
                    ['serial_unit_id' => $unitId],
                    [
                        'warehouse_id' => (int) $data['warehouse_id'],
                        'state' => 'in_stock',
                        'synced_at' => now(),
                        'note' => request()->input('note', request()->input('notes', request()->input('ghi_chu'))), // EGO_SERIAL_NOTE_SAVE_PATCH
                    ]
                );

                if (\Illuminate\Support\Facades\Schema::hasTable('crm_serial_warranty_events')) {
                    \Illuminate\Support\Facades\DB::table('crm_serial_warranty_events')->insert([
                        'serial_unit_id' => $unitId,
                        'serial_code' => $code,
                        'event_type' => 'receive',
                        'from_state' => null,
                        'to_state' => 'in_stock',
                        'from_warehouse_id' => null,
                        'to_warehouse_id' => (int) $data['warehouse_id'],
                        'customer_id' => null,
                        'order_id' => null,
                        'created_by' => auth()->id(),
                        'note' => $data['note'] ?? 'Thêm serial từ màn sửa sản phẩm',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        return response()->json(['ok' => true, 'message' => 'Đã thêm '.$codes->count().' serial.']);
    }

    /**
     * API cập nhật mã serial và kho (nếu chưa bán) từ màn sản phẩm.
     */
    public function productUpdateSerial(\Illuminate\Http\Request $request, $serialUnit)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
            'warehouse_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $unitId = (int) $serialUnit;
        $newCode = trim((string) $data['code']);

        $row = \Illuminate\Support\Facades\DB::table('crm_serial_unit_identifiers as sui')
            ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->leftJoin('crm_serial_unit_states as st', 'st.serial_unit_id', '=', 'sui.serial_unit_id')
            ->where('sui.serial_unit_id', $unitId)
            ->where('sui.is_primary', 1)
            ->select('sui.serial_identifier_id', 'si.code as old_code', 'st.state', 'st.warehouse_id')
            ->first();

        if (! $row) {
            return response()->json(['ok' => false, 'message' => 'Không tìm thấy serial.'], 404);
        }

        $duplicated = \Illuminate\Support\Facades\DB::table('crm_serial_identifiers')
            ->where('code', $newCode)
            ->where('id', '!=', (int) $row->serial_identifier_id)
            ->exists();

        if ($duplicated) {
            return response()->json(['ok' => false, 'message' => 'Mã serial này đã tồn tại.'], 422);
        }

        $state = (string) ($row->state ?? 'in_stock');
        $locked = in_array($state, ['sold', 'delivered', 'warranty', 'warranty_claim'], true);

        \Illuminate\Support\Facades\DB::transaction(function () use ($row, $unitId, $newCode, $data, $locked, $state) {
            \Illuminate\Support\Facades\DB::table('crm_serial_identifiers')
                ->where('id', (int) $row->serial_identifier_id)
                ->update([
                    'code' => $newCode,
                    'updated_at' => now(),
                ]);

            if (! $locked && ! empty($data['warehouse_id'])) {
                $warehouseId = (int) $data['warehouse_id'];

                \Illuminate\Support\Facades\DB::table('crm_serial_unit_states')->updateOrInsert(
                    ['serial_unit_id' => $unitId],
                    [
                        'warehouse_id' => $warehouseId,
                        'state' => $state ?: 'in_stock',
                        'synced_at' => now(),
                        'note' => request()->input('note', request()->input('notes', request()->input('ghi_chu'))), // EGO_SERIAL_NOTE_SAVE_PATCH
                    ]
                );

                if (\Illuminate\Support\Facades\Schema::hasColumn('crm_serial_units', 'warehouse_id')) {
                    \Illuminate\Support\Facades\DB::table('crm_serial_units')
                        ->where('id', $unitId)
                        ->update([
                            'warehouse_id' => $warehouseId,
                            'updated_at' => now(),
                        ]);
                }
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('crm_serial_warranty_events')) {
                \Illuminate\Support\Facades\DB::table('crm_serial_warranty_events')
                    ->where('serial_unit_id', $unitId)
                    ->update(['serial_code' => $newCode]);
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('crm_serial_warranty_claims')) {
                \Illuminate\Support\Facades\DB::table('crm_serial_warranty_claims')
                    ->where('serial_unit_id', $unitId)
                    ->update(['serial_code' => $newCode]);
            }
        });

        return response()->json(['ok' => true, 'message' => 'Đã cập nhật serial.']);
    }

    /**
     * API xóa serial chưa bán cùng toàn bộ dữ liệu liên quan.
     */
    public function productDeleteSerial(\Illuminate\Http\Request $request, $serialUnit)
    {
        $unitId = (int) $serialUnit;

        $row = \Illuminate\Support\Facades\DB::table('crm_serial_units as su')
            ->leftJoin('crm_serial_unit_states as st', 'st.serial_unit_id', '=', 'su.id')
            ->leftJoin('crm_serial_unit_identifiers as sui', function ($join) {
                $join->on('sui.serial_unit_id', '=', 'su.id')->where('sui.is_primary', 1);
            })
            ->leftJoin('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->where('su.id', $unitId)
            ->select('su.id', 'st.state', 'si.code')
            ->first();

        if (! $row) {
            return response()->json(['ok' => false, 'message' => 'Không tìm thấy serial.'], 404);
        }

        if (in_array((string) $row->state, ['sold', 'delivered', 'warranty', 'warranty_claim'], true)) {
            return response()->json(['ok' => false, 'message' => 'Serial đã bán / đã kích hoạt bảo hành nên không được xóa.'], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($unitId) {
            $identifierIds = \Illuminate\Support\Facades\DB::table('crm_serial_unit_identifiers')
                ->where('serial_unit_id', $unitId)
                ->pluck('serial_identifier_id')
                ->all();

            if (\Illuminate\Support\Facades\Schema::hasTable('crm_serial_warranties')) {
                \Illuminate\Support\Facades\DB::table('crm_serial_warranties')->where('serial_unit_id', $unitId)->delete();
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('crm_serial_warranty_claims')) {
                \Illuminate\Support\Facades\DB::table('crm_serial_warranty_claims')->where('serial_unit_id', $unitId)->delete();
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('crm_serial_warranty_events')) {
                \Illuminate\Support\Facades\DB::table('crm_serial_warranty_events')->where('serial_unit_id', $unitId)->delete();
            }

            \Illuminate\Support\Facades\DB::table('crm_serial_unit_states')->where('serial_unit_id', $unitId)->delete();
            \Illuminate\Support\Facades\DB::table('crm_serial_unit_identifiers')->where('serial_unit_id', $unitId)->delete();

            if (! empty($identifierIds)) {
                \Illuminate\Support\Facades\DB::table('crm_serial_identifiers')->whereIn('id', $identifierIds)->delete();
            }

            \Illuminate\Support\Facades\DB::table('crm_serial_units')->where('id', $unitId)->delete();
        });

        return response()->json(['ok' => true, 'message' => 'Đã xóa serial.']);
    }

    /**
     * Tách chuỗi thành mảng mã serial duy nhất (bản dùng cho API sản phẩm).
     */
    private function egoSerialParseCodes($text): array
    {
        $parts = preg_split('/[\r\n,;]+/', (string) $text);
        $codes = [];

        foreach ($parts as $part) {
            $code = trim((string) $part);
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * Trả kết quả JSON hoặc redirect kèm flash message tùy loại request.
     */
    private function egoSerialApiResponse(bool $ok, string $message)
    {
        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'ok' => $ok,
                'message' => $message,
            ], $ok ? 200 : 422);
        }

        return back()->with($ok ? 'success' : 'error', $message);
    }

    /**
     * Ghi log sự kiện serial (bản dùng cho API sản phẩm).
     */
    private function egoSerialLogEvent($unitId, $code, $type, $fromState, $toState, $fromWh, $toWh, $customerId, $orderId, $note): void
    {
        if (! Schema::hasTable('crm_serial_warranty_events')) {
            return;
        }

        DB::table('crm_serial_warranty_events')->insert([
            'serial_unit_id' => $unitId,
            'serial_code' => $code,
            'event_type' => $type,
            'from_state' => $fromState,
            'to_state' => $toState,
            'from_warehouse_id' => $fromWh,
            'to_warehouse_id' => $toWh,
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'created_by' => auth()->id(),
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Bổ sung serial bảo hành thủ công (quên nhập kho): tạo/cập nhật serial và kích hoạt bảo hành.
     */
    public function manualAddSerialWarranty(\Illuminate\Http\Request $request)
    {
        $user = auth()->user();

        $roleText = strtolower(implode(' ', array_filter([
            $user->role ?? null,
            $user->role_name ?? null,
            $user->department ?? null,
            $user->current_department ?? null,
            $user->position ?? null,
            $user->type ?? null,
            $user->permission ?? null,
            $user->email ?? null,
            $user->name ?? null,
        ])));

        $isAllowed = $user && (
            ! empty($user->is_admin)
            || ! empty($user->is_super_admin)
            || str_contains($roleText, 'admin')
            || str_contains($roleText, 'kho')
            || str_contains($roleText, 'warehouse')
            || str_contains($roleText, 'lamquanmkt')
            || str_contains($roleText, 'lâm quân')
            || str_contains($roleText, 'lam quân')
        );

        if (! $isAllowed) {
            return back()->with('error', 'Bạn không có quyền thêm serial bảo hành thủ công.');
        }

        $this->ensureBaseTables();

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'serials' => ['required', 'string'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'order_id' => ['nullable', 'integer', 'min:1'],
            'site_id' => ['nullable', 'integer', 'min:1'], // EGO_SERIAL_SITE_SAVE_PATCH
            'sold_at' => ['nullable', 'date'],
            'warranty_start_at' => ['nullable', 'date'],
            'warranty_months' => ['required', 'integer', 'min:1', 'max:240'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $product = DB::table('crm_product_catalog')->where('id', (int) $data['product_id'])->first();

        if (! $product) {
            return back()->withInput()->with('error', 'Sản phẩm không hợp lệ.');
        }

        $codes = $this->parseSerials($data['serials']);

        if (! $codes) {
            return back()->withInput()->with('error', 'Vui lòng nhập ít nhất 1 serial.');
        }

        $orderId = ! empty($data['order_id']) ? (int) $data['order_id'] : 0;
        $customerId = ! empty($data['customer_id']) ? (int) $data['customer_id'] : 0;
        $siteId = ! empty($data['site_id']) ? (int) $data['site_id'] : 0; // EGO_SERIAL_SITE_SAVE_PATCH

        if ($orderId > 0 && $customerId <= 0) {
            $customerId = (int) $this->customerIdFromOrder($orderId);
        }

        $soldAt = ! empty($data['sold_at'])
            ? \Carbon\Carbon::parse($data['sold_at'])->startOfDay()
            : now()->startOfDay();

        $startAt = ! empty($data['warranty_start_at'])
            ? \Carbon\Carbon::parse($data['warranty_start_at'])->startOfDay()
            : $soldAt->copy();

        $months = (int) $data['warranty_months'];
        $endAt = $startAt->copy()->addMonths($months);

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($codes, $data, $customerId, $orderId, $siteId, $soldAt, $startAt, $months, $endAt, &$created, &$updated) {
            foreach ($codes as $code) {
                $identifier = DB::table('crm_serial_identifiers')->where('code', $code)->first();

                if ($identifier) {
                    $identifierId = (int) $identifier->id;

                    $link = DB::table('crm_serial_unit_identifiers')
                        ->where('serial_identifier_id', $identifierId)
                        ->first();

                    if ($link) {
                        $unitId = (int) $link->serial_unit_id;

                        DB::table('crm_serial_units')
                            ->where('id', $unitId)
                            ->update([
                                'product_id' => (int) $data['product_id'],
                                'warehouse_id' => null,
                                'updated_at' => now(),
                            ]);

                        $updated++;
                    } else {
                        $unitId = DB::table('crm_serial_units')->insertGetId([
                            'product_id' => (int) $data['product_id'],
                            'warehouse_id' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        DB::table('crm_serial_unit_identifiers')->insert([
                            'serial_unit_id' => $unitId,
                            'serial_identifier_id' => $identifierId,
                            'is_primary' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $created++;
                    }
                } else {
                    $unitId = DB::table('crm_serial_units')->insertGetId([
                        'product_id' => (int) $data['product_id'],
                        'warehouse_id' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $identifierId = DB::table('crm_serial_identifiers')->insertGetId([
                        'type' => 'serial',
                        'code' => $code,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('crm_serial_unit_identifiers')->insert([
                        'serial_unit_id' => $unitId,
                        'serial_identifier_id' => $identifierId,
                        'is_primary' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $created++;
                }

                $stateData = [
                    'warehouse_id' => null,
                    'state' => 'sold',
                    'synced_at' => now(),
                    'note' => request()->input('note', request()->input('notes', request()->input('ghi_chu'))), // EGO_SERIAL_NOTE_SAVE_PATCH
                ];

                if (Schema::hasColumn('crm_serial_unit_states', 'company_id')) {
                    $stateData['company_id'] = null;
                }

                DB::table('crm_serial_unit_states')->updateOrInsert(
                    ['serial_unit_id' => $unitId],
                    $stateData
                );

                $orderItemId = null;

                if ($orderId > 0 && Schema::hasTable('crm_order_items')) {
                    $orderItemId = DB::table('crm_order_items')
                        ->where('order_id', $orderId)
                        ->where('product_id', (int) $data['product_id'])
                        ->orderBy('id')
                        ->value('id');

                    if ($orderItemId && Schema::hasTable('crm_order_item_serial_units')) {
                        DB::table('crm_order_item_serial_units')->updateOrInsert(
                            ['serial_unit_id' => $unitId],
                            [
                                'order_item_id' => $orderItemId,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                }

                DB::table('crm_serial_warranties')->updateOrInsert(
                    ['serial_unit_id' => $unitId],
                    [
                        'customer_id' => $customerId > 0 ? $customerId : null,
                        'order_id' => $orderId > 0 ? $orderId : null,
                        'site_id' => isset($siteId) && $siteId > 0 ? $siteId : null, // EGO_SERIAL_SITE_SAVE_PATCH
                        'order_item_id' => $orderItemId,
                        'sold_at' => $soldAt->toDateString(),
                        'warranty_months' => $months,
                        'warranty_start_at' => $startAt->toDateString(),
                        'warranty_end_at' => $endAt->toDateString(),
                        'status' => 'active',
                        'note' => $data['note'] ?? 'Bổ sung serial bảo hành thủ công do quên nhập kho',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $this->logEvent(
                    $unitId,
                    $code,
                    'manual_add_warranty',
                    null,
                    'sold',
                    null,
                    null,
                    $customerId > 0 ? $customerId : null,
                    $orderId > 0 ? $orderId : null,
                    $data['note'] ?? 'Bổ sung serial bảo hành thủ công do quên nhập kho'
                );
            }
        });

        return redirect()
            ->route('serial-warranty.index')
            ->with('success', 'Đã thêm/cập nhật bảo hành cho '.count($codes).' serial. Tạo mới: '.$created.', cập nhật: '.$updated.'.');
    }

    /**
     * Cập nhật đầy đủ thông tin bảo hành của một serial (sản phẩm, khách, thời hạn).
     */
    public function updateSerialWarranty(\Illuminate\Http\Request $request, $serialUnit)
    {
        $user = auth()->user();

        $roleText = strtolower(implode(' ', array_filter([
            $user->role ?? null,
            $user->role_name ?? null,
            $user->department ?? null,
            $user->current_department ?? null,
            $user->position ?? null,
            $user->type ?? null,
            $user->permission ?? null,
            $user->email ?? null,
            $user->name ?? null,
        ])));

        $isAllowed = $user && (
            ! empty($user->is_admin)
            || ! empty($user->is_super_admin)
            || str_contains($roleText, 'admin')
            || str_contains($roleText, 'kho')
            || str_contains($roleText, 'warehouse')
            || str_contains($roleText, 'lamquanmkt')
            || str_contains($roleText, 'lâm quân')
            || str_contains($roleText, 'lam quân')
        );

        if (! $isAllowed) {
            return back()->with('error', 'Bạn không có quyền sửa thông tin bảo hành.');
        }

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'order_id' => ['nullable', 'integer', 'min:1'],
            'sold_at' => ['nullable', 'date'],
            'warranty_start_at' => ['nullable', 'date'],
            'warranty_months' => ['required', 'integer', 'min:1', 'max:240'],
            'warranty_end_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $unitId = (int) $serialUnit;

        $serial = DB::table('crm_serial_units as su')
            ->leftJoin('crm_serial_unit_identifiers as sui', function ($join) {
                $join->on('sui.serial_unit_id', '=', 'su.id')->where('sui.is_primary', 1);
            })
            ->leftJoin('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->leftJoin('crm_serial_warranties as wa', 'wa.serial_unit_id', '=', 'su.id')
            ->where('su.id', $unitId)
            ->select(
                'su.id',
                'su.product_id',
                'si.code',
                'wa.customer_id',
                'wa.order_id',
                'wa.order_item_id',
                'wa.sold_at',
                'wa.site_id'
            )
            ->first();

        if (! $serial) {
            return back()->with('error', 'Không tìm thấy serial.');
        }

        $productId = (int) ($data['product_id'] ?? 0);

        $productExists = DB::table('crm_product_catalog')->where('id', $productId)->exists();

        if (! $productExists) {
            return back()->withInput()->with('error', 'Sản phẩm không hợp lệ.');
        }

        $customerId = ! empty($data['customer_id'])
            ? (int) $data['customer_id']
            : ($serial->customer_id ? (int) $serial->customer_id : null);

        $orderId = ! empty($data['order_id'])
            ? (int) $data['order_id']
            : ($serial->order_id ? (int) $serial->order_id : null);

        if ($orderId && ! $customerId) {
            $fromOrder = (int) $this->customerIdFromOrder($orderId);
            $customerId = $fromOrder > 0 ? $fromOrder : null;
        }

        $soldAt = ! empty($data['sold_at'])
            ? \Carbon\Carbon::parse($data['sold_at'])->startOfDay()
            : ($serial->sold_at ? \Carbon\Carbon::parse($serial->sold_at)->startOfDay() : now()->startOfDay());

        $start = ! empty($data['warranty_start_at'])
            ? \Carbon\Carbon::parse($data['warranty_start_at'])->startOfDay()
            : $soldAt->copy();

        $months = (int) $data['warranty_months'];

        $end = ! empty($data['warranty_end_at'])
            ? \Carbon\Carbon::parse($data['warranty_end_at'])->startOfDay()
            : $start->copy()->addMonths($months);

        DB::transaction(function () use ($unitId, $serial, $productId, $customerId, $orderId, $soldAt, $start, $months, $end, $data) {
            DB::table('crm_serial_units')
                ->where('id', $unitId)
                ->update([
                    'product_id' => $productId,
                    'updated_at' => now(),
                ]);

            $warrantyData = [
                'customer_id' => $customerId,
                'order_id' => $orderId,
                'sold_at' => $soldAt->toDateString(),
                'warranty_months' => $months,
                'warranty_start_at' => $start->toDateString(),
                'warranty_end_at' => $end->toDateString(),
                'status' => 'active',
                'note' => $data['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('crm_serial_warranties', 'order_item_id')) {
                $warrantyData['order_item_id'] = $serial->order_item_id ?? null;
            }

            if (Schema::hasColumn('crm_serial_warranties', 'site_id')) {
                $warrantyData['site_id'] = $serial->site_id ?? null;
            }

            DB::table('crm_serial_warranties')->updateOrInsert(
                ['serial_unit_id' => $unitId],
                $warrantyData
            );

            if (Schema::hasTable('crm_serial_warranty_events')) {
                DB::table('crm_serial_warranty_events')->insert([
                    'serial_unit_id' => $unitId,
                    'serial_code' => $serial->code ?? null,
                    'event_type' => 'warranty_update',
                    'from_state' => null,
                    'to_state' => null,
                    'from_warehouse_id' => null,
                    'to_warehouse_id' => null,
                    'customer_id' => $customerId,
                    'order_id' => $orderId,
                    'created_by' => auth()->id(),
                    'note' => 'Cập nhật đầy đủ thông tin bảo hành: '.$months.' tháng, từ '.$start->toDateString().' đến '.$end->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('success', 'Đã cập nhật thông tin bảo hành cho serial '.($serial->code ?? ('#'.$unitId)).'.');
    }

    /**
     * Ẩn serial khỏi trang tra cứu bảo hành (chuyển trạng thái removed).
     */
    public function removeSerialFromLookup(\Illuminate\Http\Request $request, $serialUnit)
    {
        $user = auth()->user();

        $roleText = strtolower(implode(' ', array_filter([
            $user->role ?? null,
            $user->role_name ?? null,
            $user->department ?? null,
            $user->current_department ?? null,
            $user->position ?? null,
            $user->type ?? null,
            $user->permission ?? null,
            $user->email ?? null,
            $user->name ?? null,
        ])));

        $isAllowed = $user && (
            ! empty($user->is_admin)
            || ! empty($user->is_super_admin)
            || str_contains($roleText, 'admin')
            || str_contains($roleText, 'kho')
            || str_contains($roleText, 'warehouse')
            || str_contains($roleText, 'lamquanmkt')
            || str_contains($roleText, 'lâm quân')
            || str_contains($roleText, 'lam quân')
        );

        if (! $isAllowed) {
            return back()->with('error', 'Bạn không có quyền xóa serial khỏi trang tra cứu.');
        }

        $unitId = (int) $serialUnit;

        $serial = \Illuminate\Support\Facades\DB::table('crm_serial_units as su')
            ->leftJoin('crm_serial_unit_identifiers as sui', function ($join) {
                $join->on('sui.serial_unit_id', '=', 'su.id')->where('sui.is_primary', 1);
            })
            ->leftJoin('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->where('su.id', $unitId)
            ->select('su.id', 'si.code')
            ->first();

        if (! $serial) {
            return back()->with('error', 'Không tìm thấy serial.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($unitId, $serial) {
            \Illuminate\Support\Facades\DB::table('crm_serial_unit_states')->updateOrInsert(
                ['serial_unit_id' => $unitId],
                [
                    'warehouse_id' => null,
                    'state' => 'removed',
                    'synced_at' => now(),
                    'note' => request()->input('note', request()->input('notes', request()->input('ghi_chu'))), // EGO_SERIAL_NOTE_SAVE_PATCH
                ]
            );

            if (\Illuminate\Support\Facades\Schema::hasTable('crm_serial_warranty_events')) {
                \Illuminate\Support\Facades\DB::table('crm_serial_warranty_events')->insert([
                    'serial_unit_id' => $unitId,
                    'serial_code' => $serial->code ?? null,
                    'event_type' => 'remove_from_lookup',
                    'from_state' => 'sold',
                    'to_state' => 'removed',
                    'from_warehouse_id' => null,
                    'to_warehouse_id' => null,
                    'customer_id' => null,
                    'order_id' => null,
                    'created_by' => auth()->id(),
                    'note' => 'Admin/Kho xóa khỏi trang tra cứu bảo hành',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('success', 'Đã xóa serial '.($serial->code ?? ('#'.$unitId)).' khỏi trang tra cứu.');
    }
}
