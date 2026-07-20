<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Contracts\Services\PaymentMethodServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentMethodRequest;
use Illuminate\Http\Request;

/**
 * Controller quản lý phương thức thanh toán (PaymentMethod) — chỉ điều phối, nghiệp vụ nằm ở service.
 */
class PaymentMethodController extends Controller
{
    // Inject Service vào Controller
    public function __construct(
        protected PaymentMethodServiceInterface $service
    ) {}

    public function index(Request $request)
    {
        $methods = $this->service->getList($request->all());

        return view('payment_methods.index', compact('methods'));
    }

    public function create()
    {
        return view('payment_methods.create');
    }

    public function store(PaymentMethodRequest $request)
    {
        try {
            $this->service->store($request->validated());

            return redirect()
                ->route('payment-methods.index')
                ->with('success', 'Thêm phương thức thành công!');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function edit($id)
    {
        try {
            $method = $this->service->getDetail((int) $id);

            return view('payment_methods.edit', compact('method'));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function update(PaymentMethodRequest $request, $id)
    {
        try {
            $this->service->update((int) $id, $request->validated());

            return redirect()
                ->route('payment-methods.index')
                ->with('success', 'Cập nhật thành công!');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->service->delete((int) $id);

            return redirect()
                ->route('payment-methods.index')
                ->with('success', 'Đã xóa phương thức thanh toán.');
        } catch (\Exception $e) {
            return back()->with('error', 'Lỗi khi xóa: '.$e->getMessage());
        }
    }
}
