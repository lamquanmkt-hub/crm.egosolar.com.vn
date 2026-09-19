<?php

namespace App\Http\Controllers;

use App\Models\SalesQuotation;
use App\Models\SalesQuotationItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesQuotationController extends Controller
{
    public function index(Request $request)
    {
        $baseQuery = SalesQuotation::query();

        if ($request->filled('q')) {
            $q = trim($request->q);

            $baseQuery->where(function ($sub) use ($q) {
                $sub->where('quote_code', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('customer_phone', 'like', "%{$q}%")
                    ->orWhere('customer_email', 'like', "%{$q}%")
                    ->orWhere('project_name', 'like', "%{$q}%");
            });
        }

        if ($request->filled('date_from')) {
            $baseQuery->whereDate('quote_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $baseQuery->whereDate('quote_date', '<=', $request->date_to);
        }

        $summaryQuery = clone $baseQuery;

        $summary = [
            'total_count' => (clone $summaryQuery)->count(),
            'draft_count' => (clone $summaryQuery)->where('status', 'draft')->count(),
            'sent_count' => (clone $summaryQuery)->where('status', 'sent')->count(),
            'approved_count' => (clone $summaryQuery)->where('status', 'approved')->count(),
            'grand_total' => (clone $summaryQuery)->sum('grand_total'),
        ];

        $query = clone $baseQuery;

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $quotations = $query
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('sales_quotations.index', compact('quotations', 'summary'));
    }

    public function create()
    {
        $quotation = new SalesQuotation([
            'quote_date' => now(),
            'valid_until' => now()->addDays(15),
            'status' => 'draft',
            'payment_terms' => "Đợt 1: 40% tạm ứng khi hợp đồng có hiệu lực\nĐợt 2: 50% thanh toán khi thiết bị, vật tư tập kết tại công trình và thi công\nĐợt 3: 10% thanh toán khi hoàn thành lắp đặt, bàn giao hệ thống",
            'commercial_terms' => "1. Thời gian hoàn thành dự án: 2-4 ngày tuỳ điều kiện mặt bằng.\n2. Hàng hóa mới 100%, đúng chủng loại và thông số kỹ thuật.\n3. Công việc trọn gói, không tính phát sinh trừ khi có thay đổi thiết kế hoặc tăng công suất lắp đặt.",
            'warranty_terms' => "Bảo hành toàn bộ công trình 24 tháng miễn phí. Thiết bị bảo hành theo chính sách hãng sản xuất.",
            'om_terms' => "Kiểm tra định kỳ 6 tháng/lần trong 24 tháng. Vệ sinh tấm pin tối đa 3 lần/năm. Theo dõi hệ thống qua phần mềm giám sát.",
        ]);

        return view('sales_quotations.form', [
            'quotation' => $quotation,
            'items' => collect(),
            'products' => $this->products(),
            'customers' => $this->customers(),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request)
    {
        [$data, $itemsData, $totals] = $this->validatedData($request);

        DB::transaction(function () use (&$quotation, $data, $itemsData, $totals) {
            $quotation = SalesQuotation::create(array_merge($data, $totals, [
                'created_by' => Auth::id(),
            ]));

            $quotation->update([
                'quote_code' => 'BG-' . now()->format('Ymd') . '-' . str_pad((string) $quotation->id, 5, '0', STR_PAD_LEFT),
            ]);

            $this->syncItems($quotation, $itemsData);
        });

        return redirect()->route('sales-quotations.show', $quotation)->with('success', 'Đã tạo báo giá thành công.');
    }

    public function show(SalesQuotation $salesQuotation)
    {
        $salesQuotation->load('items');

        return view('sales_quotations.show', [
            'quotation' => $salesQuotation,
        ]);
    }

    public function edit(SalesQuotation $salesQuotation)
    {
        $salesQuotation->load('items');

        return view('sales_quotations.form', [
            'quotation' => $salesQuotation,
            'items' => $salesQuotation->items,
            'products' => $this->products(),
            'customers' => $this->customers(),
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, SalesQuotation $salesQuotation)
    {
        [$data, $itemsData, $totals] = $this->validatedData($request);

        DB::transaction(function () use ($salesQuotation, $data, $itemsData, $totals) {
            $salesQuotation->update(array_merge($data, $totals));
            $this->syncItems($salesQuotation, $itemsData);
        });

        return redirect()->route('sales-quotations.show', $salesQuotation)->with('success', 'Đã cập nhật báo giá thành công.');
    }

    public function destroy(SalesQuotation $salesQuotation)
    {
        $salesQuotation->delete();

        return redirect()->route('sales-quotations.index')->with('success', 'Đã xoá báo giá.');
    }

    public function markSent(SalesQuotation $salesQuotation)
    {
        $salesQuotation->update([
            'status' => 'sent',
            'sent_by' => Auth::id(),
            'sent_at' => now(),
        ]);

        return back()->with('success', 'Đã đánh dấu báo giá là đã gửi khách.');
    }

    public function pdf(SalesQuotation $salesQuotation)
    {
        $salesQuotation->load('items');

        return view('sales_quotations.pdf', [
            'quotation' => $salesQuotation,
            'printMode' => true,
        ]);
    }


    public function downloadPdf(SalesQuotation $salesQuotation)
    {
        $salesQuotation->load('items');

        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return redirect()
                ->route('sales-quotations.pdf', $salesQuotation)
                ->with('success', 'Máy chủ chưa cài gói xuất PDF tải về. Tạm thời dùng chức năng Xuất PDF bằng trình duyệt.');
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('sales_quotations.pdf', [
            'quotation' => $salesQuotation,
            'downloadMode' => true,
        ])->setPaper('a4', 'portrait');

        $pdf->setOptions([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'DejaVu Serif',
            'dpi' => 120,
            'fontHeightRatio' => 0.95,
        ]);

        $fileName = ($salesQuotation->quote_code ?: 'bao-gia') . '.pdf';

        return $pdf->download($fileName);
    }

    public function excel(SalesQuotation $salesQuotation)
    {
        $salesQuotation->load('items');

        $fileName = ($salesQuotation->quote_code ?: 'bao-gia') . '.xls';

        return response()
            ->view('sales_quotations.excel', ['quotation' => $salesQuotation])
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'quote_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'customer_id' => ['nullable', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'string', 'max:255'],
            'customer_address' => ['nullable', 'string'],
            'billing_company_name' => ['nullable', 'string', 'max:255'],
            'billing_tax_code' => ['nullable', 'string', 'max:50'],
            'billing_address' => ['nullable', 'string'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'project_address' => ['nullable', 'string'],
            'system_type' => ['nullable', 'string', 'max:100'],
            'system_kwp' => ['nullable'],
            'system_kw_ac' => ['nullable'],
            'battery_kwh' => ['nullable'],
            'config_summary' => ['nullable', 'string'],
            'application_note' => ['nullable', 'string'],
            'discount_amount' => ['nullable'],
            'vat_percent' => ['nullable'],
            'payment_terms' => ['nullable', 'string'],
            'commercial_terms' => ['nullable', 'string'],
            'warranty_terms' => ['nullable', 'string'],
            'om_terms' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
        ]);

        $items = $request->input('items', []);
        $subtotal = 0;
        $cleanItems = [];
        $sortOrder = 1;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = trim($item['product_name'] ?? '');

            if ($name === '') {
                continue;
            }

            $qty = max(0, $this->num($item['qty'] ?? 0));

            if ($qty <= 0) {
                $qty = 1;
            }

            $unitPrice = max(0, $this->num($item['unit_price'] ?? 0));
            $lineTotal = $unitPrice * $qty;
            $lineSubtotal = $lineTotal;
            $lineVat = 0;
            $itemVatPercent = 0;

            $subtotal += $lineTotal;

            $cleanItems[] = [
                'sort_order' => $sortOrder++,
                'section_key' => $item['section_key'] ?? 'other',
                'section_title' => $item['section_title'] ?? 'Các hạng mục khác',
                'product_id' => $item['product_id'] ?? null,
                'product_name' => $name,
                'sku' => $item['sku'] ?? null,
                'brand' => $item['brand'] ?? null,
                'model' => $item['model'] ?? null,
                'unit' => $item['unit'] ?? null,
                'specs_text' => $item['specs_text'] ?? null,
                'image_url' => $item['image_url'] ?? null,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'vat_percent' => $itemVatPercent,
                'line_subtotal' => $lineSubtotal,
                'line_vat' => $lineVat,
                'line_total' => $lineTotal,
                'note' => $item['note'] ?? null,
            ];
        }

        $discount = max(0, $this->num($data['discount_amount'] ?? 0));
        $vatPercent = 0;

        $grandTotal = max($subtotal - $discount, 0);
        $vatAmount = 0;

        $data['status'] = $data['status'] ?? 'draft';
        $data['quote_date'] = $data['quote_date'] ?? now()->toDateString();
        $data['valid_until'] = $data['valid_until'] ?? now()->addDays(15)->toDateString();
        $data['system_kwp'] = $this->num($data['system_kwp'] ?? 0);
        $data['system_kw_ac'] = 0;
        $data['battery_kwh'] = $this->num($data['battery_kwh'] ?? 0);
        $data['discount_amount'] = $discount;
        $data['vat_percent'] = $vatPercent;

        $totals = [
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'grand_total' => $grandTotal,
        ];

        return [$data, $cleanItems, $totals];
    }

    private function syncItems(SalesQuotation $quotation, array $items): void
    {
        SalesQuotationItem::where('quotation_id', $quotation->id)->delete();

        foreach ($items as $item) {
            $quotation->items()->create($item);
        }
    }

    private function products()
    {
        if (!Schema::hasTable('crm_product_catalog')) {
            return collect();
        }

        return DB::table('crm_product_catalog')
            ->select('*')
            ->where(function ($q) {
                if (Schema::hasColumn('crm_product_catalog', 'is_active')) {
                    $q->where('is_active', 1);
                }
            })
            ->orderBy('name')
            ->limit(1000)
            ->get()
            ->map(function ($p) {
                $p->description = $p->description ?? $p->note ?? '';
                $p->image_url = $p->image_url ?? '';
                $p->unit = $p->unit ?? 'Bộ';
                $p->price_retail_vat = $p->price_retail_vat ?? 0;
                $p->price_agent_vat = $p->price_agent_vat ?? 0;
                $p->price_retail = $p->price_retail ?? 0;
                $p->price_agent = $p->price_agent ?? 0;
                $p->vat_percent = $p->vat_percent ?? 0;

                return $p;
            });
    }

    private function customers()
    {
        $table = Schema::hasTable('crm_customers') ? 'crm_customers' : (Schema::hasTable('customers') ? 'customers' : null);

        if (!$table) {
            return collect();
        }

        return DB::table($table)
            ->orderByDesc('id')
            ->limit(1000)
            ->get();
    }

    private function num($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        $value = str_replace(["\xc2\xa0", ' ', 'đ', 'Đ', '₫'], '', $value);
        $value = preg_replace('/[^\d,\.\-]/u', '', $value);

        if ($value === '' || $value === '-' || $value === ',' || $value === '.') {
            return 0.0;
        }

        if (preg_match('/^\-?\d{1,3}(\.\d{3})+$/', $value)) {
            $value = str_replace('.', '', $value);
        } elseif (str_contains($value, '.') && str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = rtrim($value, ',');
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }


    private function normalizeSalesQuotationNumbers(\Illuminate\Http\Request $request): void
    {
        $merge = [];

        foreach ([
            'system_kwp',
            'system_kw_ac',
            'battery_kwh',
            'discount_amount',
            'vat_percent',
        ] as $field) {
            if ($request->has($field)) {
                $merge[$field] = $this->normalizeSalesQuotationNumber($request->input($field));
            }
        }

        $items = $request->input('items', []);

        if (is_array($items)) {
            foreach ($items as $key => $item) {
                if (!is_array($item)) {
                    continue;
                }

                foreach ([
                    'qty',
                    'unit_price',
                    'vat_percent',
                    'line_total',
                    'discount_amount',
                    'discount_percent',
                ] as $field) {
                    if (array_key_exists($field, $item)) {
                        $items[$key][$field] = $this->normalizeSalesQuotationNumber($item[$field]);
                    }
                }
            }

            $merge['items'] = $items;
        }

        if ($merge) {
            $request->merge($merge);
        }
    }

    private function normalizeSalesQuotationNumber($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);

        $value = str_replace(["\xc2\xa0", ' ', 'đ', 'Đ', '₫'], '', $value);
        $value = preg_replace('/[^\d,\.\-]/u', '', $value);

        if ($value === '' || $value === '-' || $value === ',' || $value === '.') {
            return 0;
        }

        $hasComma = str_contains($value, ',');
        $hasDot = str_contains($value, '.');

        if ($hasComma && $hasDot) {
            // Dạng Việt Nam: 37.000.000,5
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif ($hasComma) {
            // Dạng 8, hoặc 1,5
            $value = rtrim($value, ',');
            $value = str_replace(',', '.', $value);
        } elseif ($hasDot && preg_match('/^\-?\d{1,3}(\.\d{3})+$/', $value)) {
            // Dạng 37.000.000
            $value = str_replace('.', '', $value);
        }

        return is_numeric($value) ? (float) $value : 0;
    }



    private function forceCleanQuoteRequestNumbers(\Illuminate\Http\Request $request): void
    {
        $merge = [];

        foreach ([
            'system_kwp',
            'system_kw_ac',
            'battery_kwh',
            'discount_amount',
            'vat_percent',
            'subtotal',
            'vat_amount',
            'grand_total',
        ] as $field) {
            if ($request->has($field)) {
                $merge[$field] = $this->forceCleanQuoteNumber($request->input($field));
            }
        }

        $items = $request->input('items', []);

        if (is_array($items)) {
            foreach ($items as $key => $item) {
                if (!is_array($item)) {
                    continue;
                }

                foreach ([
                    'qty',
                    'unit_price',
                    'vat_percent',
                    'line_total',
                    'discount_amount',
                    'discount_percent',
                ] as $field) {
                    if (array_key_exists($field, $item)) {
                        $items[$key][$field] = $this->forceCleanQuoteNumber($item[$field]);
                    }
                }
            }

            $merge['items'] = $items;
        }

        if ($merge) {
            $request->merge($merge);
        }
    }

    private function forceCleanQuoteValidatedData(array $data): array
    {
        foreach ([
            'system_kwp',
            'system_kw_ac',
            'battery_kwh',
            'discount_amount',
            'vat_percent',
            'subtotal',
            'vat_amount',
            'grand_total',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->forceCleanQuoteNumber($data[$field]);
            }
        }

        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $key => $item) {
                if (!is_array($item)) {
                    continue;
                }

                foreach ([
                    'qty',
                    'unit_price',
                    'vat_percent',
                    'line_total',
                    'discount_amount',
                    'discount_percent',
                ] as $field) {
                    if (array_key_exists($field, $item)) {
                        $data['items'][$key][$field] = $this->forceCleanQuoteNumber($item[$field]);
                    }
                }
            }
        }

        return $data;
    }

    private function forceCleanQuoteNumber($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);

        // bỏ ký tự tiền tệ / khoảng trắng / nbsp
        $value = str_replace(["\xc2\xa0", ' ', 'đ', 'Đ', '₫'], '', $value);

        // chỉ giữ số, dấu phẩy, dấu chấm, dấu âm
        $value = preg_replace('/[^\d,\.\-]/u', '', $value);

        if ($value === '' || $value === '-' || $value === ',' || $value === '.') {
            return 0.0;
        }

        // xử lý các case nhập kiểu Việt Nam
        // 37.000.000 => 37000000
        if (preg_match('/^\-?\d{1,3}(\.\d{3})+$/', $value)) {
            $value = str_replace('.', '', $value);
        }
        // 37.000.000,5 => 37000000.5
        elseif (str_contains($value, '.') && str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        // 8, hoặc 1,5 => 8 hoặc 1.5
        elseif (str_contains($value, ',')) {
            $value = rtrim($value, ',');
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

}