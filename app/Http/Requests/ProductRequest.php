<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('crm_product_catalog')->ignore($productId)],
            'sku'  => ['required', 'string', 'max:100', Rule::unique('crm_product_catalog')->ignore($productId)],

            'unit'        => ['nullable', 'string', 'max:50'],
            'category_id' => ['nullable', 'integer', 'exists:crm_product_categories,id'],

            'price_agent'  => ['required', 'numeric', 'min:0'],
            'price_retail' => ['required', 'numeric', 'min:0'],

            'brand_id' => ['nullable', 'integer', 'exists:crm_brands,id'],

            'main_image_id' => ['nullable', 'integer', 'exists:media_files,id'],
            'gallery_ids'   => ['nullable', 'string'],
            'description'   => ['nullable', 'string'],
            'warehouse_note' => ['nullable', 'string', 'max:2000'],

            'is_serialized' => ['nullable', 'in:1'],

            // ✅ NEW SHAPE: stocks[companyId][warehouseId][qty|serials]
            'stocks' => ['nullable', 'array'],
            'stocks.*' => ['nullable', 'array'],
            'stocks.*.*' => ['nullable', 'array'],
            'stocks.*.*.qty' => ['nullable', 'integer', 'min:0'],
            'stocks.*.*.serials' => ['nullable', 'string'],

            'prices'   => ['nullable', 'array'],
            'prices.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $stocks = $this->input('stocks', []);

        if (is_array($stocks)) {
            foreach ($stocks as $companyId => $warehouses) {
                if (!is_array($warehouses)) continue;

                foreach ($warehouses as $warehouseId => $row) {
                    if (!is_array($row)) continue;

                    if (array_key_exists('serials', $row) && $row['serials'] !== null && $row['serials'] !== '') {
                        $stocks[$companyId][$warehouseId]['serials'] = (string) $row['serials'];
                    }
                }
            }
        }

        $this->merge(['stocks' => $stocks]);
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $stocks = $this->input('stocks', []);
            if (!is_array($stocks)) return;

            foreach ($stocks as $companyId => $warehouses) {
                if (!is_array($warehouses)) continue;

                foreach ($warehouses as $warehouseId => $row) {
                    if (!is_array($row)) continue;

                    $serials = $row['serials'] ?? null;
                    if ($serials === null || $serials === '') continue;

                    json_decode($serials, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $validator->errors()->add("stocks.$companyId.$warehouseId.serials", "Serial của công ty #$companyId kho #$warehouseId phải là JSON hợp lệ.");
                    }
                }
            }
        });
    }
}
