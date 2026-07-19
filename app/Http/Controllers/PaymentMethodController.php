<?php
namespace App\Http\Controllers;

use App\Http\Requests\PaymentMethodRequest;
use App\Services\PaymentMethodService;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    // Inject Service vào Controller
    public function __construct(
        protected PaymentMethodService $service
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
            $method = $this->service->getDetail($id);
            return view('payment_methods.edit', compact('method'));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
    public function update(PaymentMethodRequest $request, $id)
    {
        try {
            $this->service->update($id, $request->validated());
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
            $this->service->delete($id);
            return redirect()
                ->route('payment-methods.index')
                ->with('success', 'Đã xóa phương thức thanh toán.');
        } catch (\Exception $e) {
            return back()->with('error', 'Lỗi khi xóa: ' . $e->getMessage());
        }
    }
}