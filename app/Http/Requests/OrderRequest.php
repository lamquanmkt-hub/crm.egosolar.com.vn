<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Inventory\Catalog\Product;
use App\Models\Inventory\Stock\ProductStock;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Form Request cho tạo/cập nhật đơn hàng.
 *
 * Xử lý:
 * - Validate dữ liệu đầu vào (customer, items, pricing)
 * - Chuẩn hóa dữ liệu tiền tệ (bỏ dấu chấm ngàn)
 * - Kiểm tra tồn kho
 * - Kiểm tra trùng sản phẩm cùng kho
 * - Tính tổng tiền đơn hàng
 */
class OrderRequest extends FormRequest
{
    /**
     * Authorize: luôn cho phép (kiểm tra quyền ở Controller/Policy).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Quy tắc validate.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_id'             => ['required', 'integer', Rule::exists('crm_customers', 'id')],
            'lead_id'                 => ['nullable', 'integer', Rule::exists('crm_leads', 'id')],
            'order_date'              => ['required', 'date', 'before_or_equal:today'],
            'items'                   => ['required', 'array', 'min:1'],
            'price_tier_id'           => ['nullable', 'integer', 'exists:crm_price_tiers,id'],
            'items.*.warehouse_id'    => ['required', 'integer', 'exists:crm_warehouses,id'],
            'items.*.product_id'      => ['required', 'integer', 'exists:crm_product_catalog,id'],
            'items.*.price_tier_id'   => ['nullable', 'integer', 'exists:crm_price_tiers,id'],
            'items.*.unit_price'      => ['required', 'numeric', 'min:0'],
            'items.*.quantity'        => ['required', 'integer', 'min:1'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'action'                  => ['nullable', 'string', Rule::in(['save_draft', 'submit'])],
        ];
    }

    /**
     * Thông báo lỗi tiếng Việt.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required'         => 'Vui lòng chọn khách hàng.',
            'customer_id.exists'           => 'Khách hàng không tồn tại.',
            'lead_id.exists'               => 'Lead không tồn tại.',
            'order_date.required'          => 'Vui lòng chọn ngày đặt hàng.',
            'order_date.date'              => 'Ngày đặt hàng không hợp lệ.',
            'order_date.before_or_equal'   => 'Ngày đặt hàng không được là ngày tương lai.',
            'items.required'               => 'Vui lòng thêm ít nhất một sản phẩm.',
            'items.min'                    => 'Đơn hàng phải có ít nhất một sản phẩm.',
            'items.*.warehouse_id.required' => 'Vui lòng chọn kho cho dòng sản phẩm.',
            'items.*.warehouse_id.exists'  => 'Kho không tồn tại.',
            'items.*.product_id.required'  => 'Vui lòng chọn sản phẩm.',
            'items.*.product_id.exists'    => 'Sản phẩm không tồn tại.',
            'items.*.quantity.required'    => 'Vui lòng nhập số lượng.',
            'items.*.quantity.min'         => 'Số lượng phải lớn hơn 0.',
            'items.*.unit_price.required'  => 'Vui lòng nhập đơn giá.',
            'items.*.unit_price.min'       => 'Đơn giá không được âm.',
            'items.*.discount_percent.max' => 'Giảm giá không được vượt quá 100%.',
        ];
    }

    /**
     * Chuẩn hóa dữ liệu trước khi validate.
     *
     * - Làm sạch items: parse số tiền (bỏ dấu chấm ngàn), ép kiểu
     * - Tự động tìm lead_id từ customer_id nếu chưa có
     */
    protected function prepareForValidation(): void
    {
        $this->sanitizeItems();
        $this->autoResolveLeadId();
    }

    /**
     * Validate bổ sung sau khi rules() pass.
     *
     * - Kiểm tra trùng sản phẩm cùng kho
     * - Kiểm tra tồn kho đủ không
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->hasAny(['items', 'items.*.warehouse_id', 'items.*.product_id', 'items.*.quantity', 'items.*.unit_price'])) {
                return;
            }

            $this->validateDuplicateProductsByWarehouse($validator);

            if ($this->isMethod('post')) {
                $this->validateCreateStockAvailability($validator);
            }
        });
    }

    /**
     * Lấy dữ liệu đã validate kèm tính toán tổng tiền.
     *
     * @return array
     */
    public function validatedWithProcessing(): array
    {
        $validated = $this->validated();

        $totalAmount = collect($validated['items'])->sum(function ($item) {
            return $this->calculateItemLineTotal($item);
        });

        $validated['total_amount'] = max(0, round($totalAmount, 2));

        return $validated;
    }

    /**
     * Override: throw ValidationException (không redirect).
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException($validator);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Chuẩn hóa mảng items: ép kiểu, parse tiền tệ.
     */
    private function sanitizeItems(): void
    {
        if (!$this->has('items') || !is_array($this->input('items'))) {
            return;
        }

        $items = collect($this->input('items'))
            ->filter(fn ($it) => !empty($it['product_id']) || !empty($it['warehouse_id']))
            ->values()
            ->map(fn ($it) => $this->sanitizeItem($it))
            ->toArray();

        $this->merge(['items' => $items]);
    }

    /**
     * Chuẩn hóa 1 item.
     *
     * @param array $item
     * @return array
     */
    private function sanitizeItem(array $item): array
    {
        $item['warehouse_id']    = (int) ($item['warehouse_id'] ?? 0);
        $item['product_id']      = (int) ($item['product_id'] ?? 0);
        $item['quantity']        = (int) ($item['quantity'] ?? 0);
        $item['unit_price']      = $this->parseMoney($item['unit_price'] ?? 0);
        $item['discount_amount'] = $this->parseMoney($item['discount_amount'] ?? 0);

        $disc = $item['discount_percent'] ?? 0;
        $item['discount_percent'] = max(0, min(100, ($disc === '' || $disc === null) ? 0 : (float) $disc));

        $item['price_tier_id'] = isset($item['price_tier_id']) && $item['price_tier_id'] !== ''
            ? (int) $item['price_tier_id']
            : null;

        return $item;
    }

    /**
     * Parse chuỗi tiền tệ (VD: "2.000.000" → 2000000).
     *
     * @param mixed $value
     * @return float
     */
    private function parseMoney($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $value = str_replace(['.', ',', ' '], '', (string) $value);

        return is_numeric($value) ? (float) $value : 0;
    }

    /**
     * Tự động tìm lead_id từ customer_id nếu chưa có.
     */
    private function autoResolveLeadId(): void
    {
        $leadId     = (int) $this->input('lead_id', 0);
        $customerId = (int) $this->input('customer_id', 0);

        if ($leadId > 0 || $customerId <= 0) {
            return;
        }

        $latestLeadId = (int) DB::table('crm_leads')
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at')
            ->value('id');

        if ($latestLeadId > 0) {
            $this->merge(['lead_id' => $latestLeadId]);
        }
    }

    /**
     * Kiểm tra trùng sản phẩm trong cùng kho.
     *
     * @param \Illuminate\Validation\Validator $validator
     */
    private function validateDuplicateProductsByWarehouse($validator): void
    {
        $seen = [];

        foreach ($this->input('items', []) as $index => $item) {
            $w = (int) ($item['warehouse_id'] ?? 0);
            $p = (int) ($item['product_id'] ?? 0);

            if ($w <= 0 || $p <= 0) {
                continue;
            }

            $key = "{$w}|{$p}";

            if (isset($seen[$key])) {
                $productName = Product::where('id', $p)->value('name') ?? "Sản phẩm #{$p}";
                $validator->errors()->add(
                    "items.{$index}.product_id",
                    "Sản phẩm {$productName} bị trùng trong cùng kho. Vui lòng gộp số lượng."
                );
            } else {
                $seen[$key] = true;
            }
        }
    }

    /**
     * Kiểm tra tồn kho có đủ không.
     *
     * @param \Illuminate\Validation\Validator $validator
     */
    /**
     * Khi tạo đơn mới, kho đã chọn phải còn đủ tồn khả dụng.
     * Màn sửa đơn cũ không áp dụng để tránh khóa các đơn lịch sử.
     */
    private function validateCreateStockAvailability($validator): void
    {
        foreach ($this->input('items', []) as $index => $item) {
            $warehouseId = (int) ($item['warehouse_id'] ?? 0);
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($warehouseId <= 0 || $productId <= 0 || $quantity <= 0) {
                continue;
            }

            $product = DB::table('crm_product_catalog')
                ->where('id', $productId)
                ->select([
                    'id',
                    'name',
                    'sku',
                    DB::raw(
                        DB::getSchemaBuilder()->hasColumn('crm_product_catalog', 'is_serialized')
                            ? 'COALESCE(is_serialized, 0) as is_serialized'
                            : '0 as is_serialized'
                    ),
                ])
                ->first();

            if (!$product) {
                continue;
            }

            $available = 0;

            if (
                (bool) ($product->is_serialized ?? false)
                && DB::getSchemaBuilder()->hasTable('crm_serial_unit_states')
                && DB::getSchemaBuilder()->hasTable('crm_serial_units')
                && DB::getSchemaBuilder()->hasColumn('crm_serial_unit_states', 'state')
            ) {
                $available = (int) DB::table('crm_serial_unit_states as state')
                    ->join('crm_serial_units as unit', 'unit.id', '=', 'state.serial_unit_id')
                    ->where('unit.product_id', $productId)
                    ->where('state.warehouse_id', $warehouseId)
                    ->where('state.state', 'in_stock')
                    ->count();
            } elseif (DB::getSchemaBuilder()->hasTable('crm_product_stock')) {
                $available = max(0, (int) DB::table('crm_product_stock')
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->sum('qty'));
            } elseif (DB::getSchemaBuilder()->hasTable('crm_product_stock_lots')) {
                $available = max(0, (int) DB::table('crm_product_stock_lots')
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->sum('qty_remaining'));
            }

            $label = trim((string) ($product->name ?? ('Sản phẩm #' . $productId)));
            $sku = trim((string) ($product->sku ?? ''));
            if ($sku !== '') {
                $label .= ' (' . $sku . ')';
            }

            if ($available <= 0) {
                $validator->errors()->add(
                    "items.{$index}.warehouse_id",
                    "Kho đã chọn hiện không còn {$label}. Vui lòng chọn kho khác."
                );
                continue;
            }

            if ($quantity > $available) {
                $validator->errors()->add(
                    "items.{$index}.quantity",
                    "{$label} chỉ còn {$available} tại kho đã chọn, nhưng bạn đang đặt {$quantity}."
                );
            }
        }
    }

    private function validateStockAvailability($validator): void
    {
        foreach ($this->input('items', []) as $index => $item) {
            $warehouseId = (int) ($item['warehouse_id'] ?? 0);
            $productId   = (int) ($item['product_id'] ?? 0);
            $qty         = (int) ($item['quantity'] ?? 0);

            if ($warehouseId <= 0 || $productId <= 0 || $qty <= 0) {
                continue;
            }

            $available = (int) (ProductStock::where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->value('qty') ?? 0);

            if ($qty > $available) {
                $productName = Product::where('id', $productId)->value('name') ?? "Sản phẩm #{$productId}";
                $validator->errors()->add(
                    "items.{$index}.quantity",
                    "Không đủ tồn kho cho {$productName}. Tồn kho: {$available}, yêu cầu: {$qty}."
                );
            }
        }
    }

    /**
     * Tính thành tiền cho 1 dòng sản phẩm.
     *
     * @param array $item
     * @return float
     */
    private function calculateItemLineTotal(array $item): float
    {
        $qty         = max(1, (int) ($item['quantity'] ?? 1));
        $price       = (float) ($item['unit_price'] ?? 0);
        $discPercent = max(0, min(100, (float) ($item['discount_percent'] ?? 0)));
        $discPerUnit = max(0, (float) ($item['discount_amount'] ?? 0));
        $lineTotal   = $qty * $price;

        $discount = ($discPerUnit > 0)
            ? $discPerUnit * $qty
            : $lineTotal * $discPercent / 100;

        return max(0, $lineTotal - min($discount, $lineTotal));
    }
}
