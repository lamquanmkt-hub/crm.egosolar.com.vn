<?php
namespace App\Http\Requests;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
	    return true;
    }
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
	    $categoryId = $this->route('category')?->id;
	    return [
		    'name' => [
			    'required',
			    'string',
			    'max:255',
			    Rule::unique('crm_product_categories', 'name')->ignore($categoryId)
		    ],
		    'description' => 'nullable|string|max:1000',
		    'parent_id' => [
			    'nullable',
			    'integer',
			    'exists:crm_product_categories,id',
			    Rule::notIn([$categoryId]) // Không thể chọn chính nó làm parent
		    ],
	    ];
    }
	/**
	 * Custom validation messages
	 * @return array<string, string>
	 */
	public function messages(): array
	{
		return [
			'name.required' => 'Tên danh mục là bắt buộc',
			'name.unique' => 'Tên danh mục đã tồn tại',
			'name.max' => 'Tên danh mục không được vượt quá 255 ký tự',
			'description.max' => 'Mô tả không được vượt quá 1000 ký tự',
			'parent_id.exists' => 'Danh mục cha không tồn tại',
			'parent_id.not_in' => 'Danh mục không thể là parent của chính nó',
		];
	}

	/**
	 * Custom validation attributes
	 * @return array<string, string>
	 */
	public function attributes(): array
	{
		return [
			'name' => 'tên danh mục',
			'description' => 'mô tả',
			'parent_id' => 'danh mục cha',
		];
	}

	/**
	 * Additional validation logic
	 * @param Validator $validator
	 * @return void
	 */
	public function withValidator(Validator $validator): void
	{
		$validator->after(function ($validator) {
			$parentId = $this->input('parent_id');
			$categoryId = $this->route('category')?->id;

			// Kiểm tra không được chọn category con làm parent
			if ($parentId && $categoryId) {
				$category = \App\Models\Inventory\Catalog\ProductCategory::find($categoryId);
				if ($category) {
					$descendants = $this->getDescendants($category);
					if ($descendants->contains('id', $parentId)) {
						$validator->errors()->add(
							'parent_id',
							'Không thể chọn danh mục con làm danh mục cha'
						);
					}
				}
			}
		});
	}

	/**
	 * Lấy tất cả descendants của category
	 * @param \App\Models\Inventory\Catalog\ProductCategory $category
	 * @return \Illuminate\Support\Collection
	 */
	protected function getDescendants(\App\Models\Inventory\Catalog\ProductCategory $category): \Illuminate\Support\Collection
	{
		$descendants = collect();
		$children = $category->children;

		foreach ($children as $child) {
			$descendants->push($child);
			$descendants = $descendants->merge($this->getDescendants($child));
		}

		return $descendants;
	}
}
