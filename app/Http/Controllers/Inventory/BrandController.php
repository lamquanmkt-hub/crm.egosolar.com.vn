<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Contracts\Services\BrandServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\BrandRequest;
use App\Models\Inventory\Catalog\Brand;
use Illuminate\Http\Request;

/**
 * Controller quản lý thương hiệu (Brand) — chỉ điều phối, nghiệp vụ nằm ở service.
 */
class BrandController extends Controller
{
    public function __construct(
        private readonly BrandServiceInterface $service
    ) {
        // nếu có policy:
        // $this->authorizeResource(Brand::class, 'brand');
    }

    public function index(Request $request)
    {
        $search = $request->string('search')->trim()->toString();
        $brands = $this->service->list($search);

        return view('brands.index', compact('brands', 'search'));
    }

    public function create()
    {
        return view('brands.create');
    }

    public function store(BrandRequest $request)
    {
        $this->service->create($request->validated());

        return redirect()->route('brands.index')->with('success', 'Tạo brand thành công');
    }

    public function edit(Brand $brand)
    {
        return view('brands.edit', compact('brand'));
    }

    public function update(BrandRequest $request, Brand $brand)
    {
        $this->service->update($brand, $request->validated());

        return redirect()->route('brands.index')->with('success', 'Cập nhật brand thành công');
    }

    public function destroy(Brand $brand)
    {
        $this->service->delete($brand);

        return redirect()->route('brands.index')->with('success', 'Xoá brand thành công');
    }
}
