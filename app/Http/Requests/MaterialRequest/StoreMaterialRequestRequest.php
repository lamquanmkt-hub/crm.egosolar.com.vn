<?php

declare(strict_types=1);

namespace App\Http\Requests\MaterialRequest;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest validate dữ liệu tạo phiếu yêu cầu vật tư.
 */
class StoreMaterialRequestRequest extends FormRequest
{
    /**
     * Xác định quyền thực hiện request (hiện cho phép tất cả).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Quy tắc validate dữ liệu phiếu yêu cầu vật tư.
     */
    public function rules(): array
    {
        return [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'note' => ['nullable', 'string', 'max:5000'],

            /*
            |--------------------------------------------------------------------------
            | Vật tư trong kho
            |--------------------------------------------------------------------------
            | Kỹ thuật chọn kho + vật tư + số lượng.
            | Không nhập giá vốn ở đây.
            | Giá vốn sẽ tự lấy từ kho/catalog khi tính chi phí.
            */
            'items' => ['nullable', 'array'],
            'items.*.warehouse_id' => ['nullable', 'integer'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],

            /*
            |--------------------------------------------------------------------------
            | Vật tư ngoài kho / vật tư phụ
            |--------------------------------------------------------------------------
            | Kỹ thuật chỉ nhập tên + số lượng + đơn vị + ghi chú.
            | Không nhập giá vốn ở đây.
            | Giá vốn sẽ do warehouse nhập khi kho duyệt/xuất.
            */
            'extra_items' => ['nullable', 'array'],
            'extra_items.*.name' => ['nullable', 'string', 'max:255'],
            'extra_items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'extra_items.*.unit' => ['nullable', 'string', 'max:50'],
            'extra_items.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Thông báo lỗi validate tuỳ chỉnh bằng tiếng Việt.
     */
    public function messages(): array
    {
        return [
            'site_id.required' => 'Vui lòng chọn công trình.',
            'site_id.exists' => 'Công trình không tồn tại.',

            'items.array' => 'Danh sách vật tư trong kho không hợp lệ.',
            'items.*.warehouse_id.integer' => 'Kho không hợp lệ.',
            'items.*.product_id.integer' => 'Vật tư trong kho không hợp lệ.',
            'items.*.qty.numeric' => 'Số lượng vật tư trong kho phải là số.',
            'items.*.qty.min' => 'Số lượng vật tư trong kho không được âm.',

            'extra_items.array' => 'Danh sách vật tư phụ / ngoài kho không hợp lệ.',
            'extra_items.*.name.max' => 'Tên vật tư phụ không được quá :max ký tự.',
            'extra_items.*.qty.numeric' => 'Số lượng vật tư phụ phải là số.',
            'extra_items.*.qty.min' => 'Số lượng vật tư phụ không được âm.',
            'extra_items.*.unit.max' => 'Đơn vị tính không được quá :max ký tự.',
            'extra_items.*.note.max' => 'Ghi chú vật tư phụ không được quá :max ký tự.',
        ];
    }

    /**
     * Chuẩn hoá danh sách vật tư trong kho, vật tư phụ và ghi chú trước khi validate.
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);
        $extraItems = $this->input('extra_items', []);

        if (! is_array($items)) {
            $items = [];
        }

        if (! is_array($extraItems)) {
            $extraItems = [];
        }

        /*
        |--------------------------------------------------------------------------
        | Dọn dòng vật tư trong kho rỗng
        |--------------------------------------------------------------------------
        */
        $items = array_values(array_filter($items, function ($row) {
            if (! is_array($row)) {
                return false;
            }

            $warehouseId = $row['warehouse_id'] ?? null;
            $productId = $row['product_id'] ?? null;
            $qty = (float) ($row['qty'] ?? 0);
            $note = trim((string) ($row['note'] ?? ''));

            return ! empty($warehouseId)
                || ! empty($productId)
                || $qty > 0
                || $note !== '';
        }));

        /*
        |--------------------------------------------------------------------------
        | Dọn dòng vật tư ngoài kho rỗng
        |--------------------------------------------------------------------------
        */
        $extraItems = array_values(array_filter($extraItems, function ($row) {
            if (! is_array($row)) {
                return false;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $qty = (float) ($row['qty'] ?? 0);
            $unit = trim((string) ($row['unit'] ?? ''));
            $note = trim((string) ($row['note'] ?? ''));

            return $name !== ''
                || $qty > 0
                || $unit !== ''
                || $note !== '';
        }));

        $this->merge([
            'items' => $items,
            'extra_items' => $extraItems,
            'note' => $this->note !== null ? trim((string) $this->note) : null,
        ]);
    }

    /**
     * Kiểm tra bổ sung: bắt buộc có ít nhất 1 vật tư hợp lệ và validate từng dòng vật tư.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            $extraItems = $this->input('extra_items', []);

            $hasStockItem = false;
            foreach ($items as $row) {
                $warehouseId = $row['warehouse_id'] ?? null;
                $productId = $row['product_id'] ?? null;
                $qty = (float) ($row['qty'] ?? 0);

                if (! empty($warehouseId) && ! empty($productId) && $qty > 0) {
                    $hasStockItem = true;
                    break;
                }
            }

            $hasExtraItem = false;
            foreach ($extraItems as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                $qty = (float) ($row['qty'] ?? 0);

                if ($name !== '' && $qty > 0) {
                    $hasExtraItem = true;
                    break;
                }
            }

            if (! $hasStockItem && ! $hasExtraItem) {
                $validator->errors()->add(
                    'items',
                    'Vui lòng nhập ít nhất 1 vật tư trong kho hoặc 1 vật tư phụ / ngoài kho.'
                );
            }

            foreach ($items as $index => $row) {
                $hasAny = ! empty($row['warehouse_id'])
                    || ! empty($row['product_id'])
                    || ((float) ($row['qty'] ?? 0) > 0)
                    || trim((string) ($row['note'] ?? '')) !== '';

                if (! $hasAny) {
                    continue;
                }

                if (empty($row['warehouse_id'])) {
                    $validator->errors()->add("items.$index.warehouse_id", 'Vui lòng chọn kho.');
                }

                if (empty($row['product_id'])) {
                    $validator->errors()->add("items.$index.product_id", 'Vui lòng chọn vật tư trong kho.');
                }

                if ((float) ($row['qty'] ?? 0) <= 0) {
                    $validator->errors()->add("items.$index.qty", 'Số lượng vật tư trong kho phải lớn hơn 0.');
                }
            }

            foreach ($extraItems as $index => $row) {
                $hasAny = trim((string) ($row['name'] ?? '')) !== ''
                    || ((float) ($row['qty'] ?? 0) > 0)
                    || trim((string) ($row['unit'] ?? '')) !== ''
                    || trim((string) ($row['note'] ?? '')) !== '';

                if (! $hasAny) {
                    continue;
                }

                if (trim((string) ($row['name'] ?? '')) === '') {
                    $validator->errors()->add("extra_items.$index.name", 'Vui lòng nhập tên vật tư phụ / ngoài kho.');
                }

                if ((float) ($row['qty'] ?? 0) <= 0) {
                    $validator->errors()->add("extra_items.$index.qty", 'Số lượng vật tư phụ / ngoài kho phải lớn hơn 0.');
                }
            }
        });
    }
}
