<?php
namespace App\Http\Controllers;
use App\Http\Requests\ProductCategoryRequest;
use App\Models\Inventory\Catalog\ProductCategory;
use App\Services\ProductCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductCategoryController extends Controller
{
	protected ProductCategoryService $service;
	/**
	 * Dependency Injection - Constructor Injection
	 * @param ProductCategoryService $service
	 */
	public function __construct(ProductCategoryService $service)
	{
		$this->authorizeResource(ProductCategory::class, 'category');
		$this->service = $service;
	}
    /**
     * Hiển thị danh sách categories
     * Display a listing of the resource.
     */
	public function index(Request $request): View
	{
		$perPage = $request->get('per_page', 15);
		$categories = $this->service->paginate($perPage);
		return view('product-categories.index', compact('categories'));
	}
    /**
     * Show the form for creating a new resource.
     * Hiển thị form tạo category mới
     */
    public function create()
    {
	    $categories = $this->service->getAllWithParent();
	    return view('product-categories.create', compact('categories'));
    }
    /**
     * Store a newly created resource in storage.
     * Lưu category mới
     * @param ProductCategoryRequest $request
     * @return RedirectResponse
     */
    public function store(ProductCategoryRequest $request): RedirectResponse
    {
	    try {
		    $this->service->create($request->validated());
		    return redirect()
			    ->route('categories.index')
			    ->with('success', 'Tạo danh mục sản phẩm thành công');
	    } catch (\Exception $e) {
		    return redirect()
			    ->back()
			    ->withInput()
			    ->with('error', $e->getMessage());
	    }
    }
	/**
	 * Hiển thị chi tiết category
	 * @param ProductCategory $category
	 * @return View
	 */
	public function show(ProductCategory $category): View
	{
		$category = $this->service->findWithProducts($category->id);
		return view('product-categories.show', compact('category'));
	}
	/**
	 * Hiển thị form chỉnh sửa category
	 * @param ProductCategory $category
	 * @return View
	 */
	public function edit(ProductCategory $category): View
	{
		$categories = $this->service->getAllWithParent();
		return view('product-categories.edit', compact('category', 'categories'));
	}
	/**
	 * Cập nhật category
	 * @param ProductCategoryRequest $request
	 * @param ProductCategory $category
	 * @return RedirectResponse
	 */
	public function update(ProductCategoryRequest $request, ProductCategory $category): RedirectResponse
	{
		try {
			$this->service->update($category->id, $request->validated());
			return redirect()
				->route('categories.index')
				->with('success', 'Cập nhật danh mục sản phẩm thành công');
		} catch (\Exception $e) {
			return redirect()
				->back()
				->withInput()
				->with('error', $e->getMessage());
		}
	}
	/**
	 * Xóa category
	 * @param ProductCategory $category
	 * @return RedirectResponse
	 */
	public function destroy(ProductCategory $category): RedirectResponse
	{
		try {
			$this->service->delete($category->id);
			return redirect()
				->route('categories.index')
				->with('success', 'Xóa danh mục sản phẩm thành công');
		} catch (\Exception $e) {
			return redirect()
				->back()
				->with('error', $e->getMessage());
		}
	}
}
