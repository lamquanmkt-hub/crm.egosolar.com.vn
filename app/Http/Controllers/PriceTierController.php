<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\PriceTierServiceInterface;
use App\Http\Requests\PriceTierRequest;
use App\Models\Inventory\Pricing\PriceTier;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Controller quản lý loại giá (PriceTier) — chỉ điều phối, nghiệp vụ nằm ở service.
 */
class PriceTierController extends Controller
{
    public function __construct(
        private readonly PriceTierServiceInterface $service
    ) {
        // nếu có policy:
        // $this->authorizeResource(PriceTier::class, 'price_tier');
    }

    public function index(Request $request)
    {
        $search = $request->string('search')->trim()->toString();
        $tiers = $this->service->list($search);

        return view('price_tiers.index', compact('tiers', 'search'));
    }

    public function create()
    {
        return view('price_tiers.create');
    }

    public function store(PriceTierRequest $request)
    {
        $this->service->create($request->validated());

        return redirect()->route('price-tiers.index')->with('success', 'Tạo loại giá thành công');
    }

    public function edit(PriceTier $price_tier)
    {
        return view('price_tiers.edit', ['tier' => $price_tier]);
    }

    public function update(PriceTierRequest $request, PriceTier $price_tier)
    {
        $this->service->update($price_tier, $request->validated());

        return redirect()->route('price-tiers.index')->with('success', 'Cập nhật loại giá thành công');
    }

    public function destroy(PriceTier $price_tier)
    {
        try {
            $this->service->delete($price_tier);

            return redirect()->route('price-tiers.index')->with('success', 'Xoá loại giá thành công');
        } catch (ValidationException $e) {
            return redirect()->route('price-tiers.index')->withErrors($e->errors());
        }
    }
}
