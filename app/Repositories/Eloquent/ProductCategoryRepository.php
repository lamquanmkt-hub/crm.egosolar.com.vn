<?php
namespace App\Repositories\Eloquent;
use App\Models\Inventory\Catalog\ProductCategory;
use App\Repositories\Interfaces\ProductCategoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductCategoryRepository implements ProductCategoryRepositoryInterface
{
	public function all(): iterable
	{
		return ProductCategory::orderBy('name')->get();
	}
	public function paginate(int $perPage = 15): LengthAwarePaginator
	{
		return ProductCategory::with(['parent', 'children', 'products'])
		                      ->withCount(['children', 'products'])
		                      ->orderBy('name')
		                      ->paginate($perPage);
	}
	public function find(int $id): ?ProductCategory
	{
		return ProductCategory::find($id);
	}
	public function create(array $data): ProductCategory
	{
		return ProductCategory::create($data);
	}
	public function update(int $id, array $data): bool
	{
		$category = ProductCategory::findOrFail($id);
		return $category->update($data);
	}
	public function delete(int $id): bool
	{
		$category = ProductCategory::findOrFail($id);
		return $category->delete();
	}
	public function findWithProducts(int $id): ?ProductCategory
	{
		return ProductCategory::with('products')->find($id);
	}
	public function allOrdered()
	{
		return ProductCategory::orderBy('name')->get();
	}
}
