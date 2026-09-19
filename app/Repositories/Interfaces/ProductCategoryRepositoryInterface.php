<?php
namespace App\Repositories\Interfaces;
use App\Models\Inventory\Catalog\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductCategoryRepositoryInterface
{
	/**
	 * Get all categories (non-paginated)
	 */
	public function all(): iterable;

	/**
	 * Paginate product categories
	 */
	public function paginate(int $perPage = 15): LengthAwarePaginator;

	/**
	 * Find one category by ID
	 */
	public function find(int $id): ?ProductCategory;

	/**
	 * Create a new category
	 */
	public function create(array $data): ProductCategory;

	/**
	 * Update category by ID
	 */
	public function update(int $id, array $data): bool;

	/**
	 * Delete a category by ID
	 */
	public function delete(int $id): bool;

	/**
	 * Get category with products (optional)
	 */
	public function findWithProducts(int $id): ?ProductCategory;
	public function allOrdered();
}
