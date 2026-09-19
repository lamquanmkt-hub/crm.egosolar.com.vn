<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr\Gifts;

use App\Http\Controllers\Controller;
use App\Models\Hr\Gift;
use App\Support\EgoCompanyLock;
use App\Support\GiftAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class GiftCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeStock($request);
        $companyId = EgoCompanyLock::id();

        $query = Gift::query()->where('company_id', $companyId);

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('sku', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('gift_type', 'like', '%'.$search.'%');
            });
        }

        if ($request->query('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->query('status') === 'inactive') {
            $query->where('is_active', false);
        }

        return view('hr.gifts.catalog', [
            'gifts' => $query->orderByDesc('is_active')->orderBy('name')->paginate(25)->withQueryString(),
            'canManage' => GiftAccess::canManage($request->user()),
            'canSeeCost' => GiftAccess::canSeeCost($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeStock($request);
        $companyId = EgoCompanyLock::id();

        $data = $request->validate([
            'sku' => ['required', 'string', 'max:80', Rule::unique('hr_gifts', 'sku')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'gift_type' => ['nullable', 'string', 'max:120'],
            'unit' => ['required', 'string', 'max:40'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        Gift::query()->create([
            ...$data,
            'company_id' => $companyId,
            'cost_price' => $data['cost_price'] ?? 0,
            'minimum_stock' => $data['minimum_stock'] ?? 0,
            'current_stock' => 0,
            'is_active' => true,
        ]);

        return back()->with('success', 'Đã thêm quà tặng vào danh mục.');
    }

    public function update(Request $request, Gift $gift): RedirectResponse
    {
        $this->authorizeStock($request);
        $this->guardCompany($gift);
        $companyId = EgoCompanyLock::id();

        $data = $request->validate([
            'sku' => [
                'required', 'string', 'max:80',
                Rule::unique('hr_gifts', 'sku')->where('company_id', $companyId)->ignore($gift->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'gift_type' => ['nullable', 'string', 'max:120'],
            'unit' => ['required', 'string', 'max:40'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $gift->update([
            ...$data,
            'cost_price' => $data['cost_price'] ?? 0,
            'minimum_stock' => $data['minimum_stock'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Đã cập nhật quà tặng '.$gift->sku.'.');
    }

    public function destroy(Request $request, Gift $gift): RedirectResponse
    {
        abort_unless(GiftAccess::canManage($request->user()), 403);
        $this->guardCompany($gift);

        if ((float) $gift->current_stock !== 0.0) {
            return back()->with('error', 'Không thể ngừng sử dụng khi quà vẫn còn tồn kho.');
        }

        $gift->update(['is_active' => false]);

        return back()->with('success', 'Đã ngừng sử dụng quà tặng '.$gift->sku.'.');
    }

    private function authorizeStock(Request $request): void
    {
        abort_unless(GiftAccess::canHandleStock($request->user()), 403);
    }

    private function guardCompany(Gift $gift): void
    {
        abort_unless((int) $gift->company_id === EgoCompanyLock::id(), 404);
    }
}
