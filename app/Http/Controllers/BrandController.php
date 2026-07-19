<?php
namespace App\Http\Controllers;
use App\Http\Requests\BrandRequest;
use App\Models\Inventory\Catalog\Brand;
use App\Services\BrandService;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct(
        private readonly BrandService $service
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
