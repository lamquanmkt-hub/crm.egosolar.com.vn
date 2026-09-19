<?php
namespace App\Http\Controllers;
use App\Models\Projects\MaterialRequest;

class MaterialRequestApprovalController extends Controller
{
public function accountingApprove(MaterialRequest $materialRequest)
{
    // 1) Check đăng nhập + role kế toán
    if (!\Auth::check() || !\Auth::user()->hasRole('accounting')) {
        abort(403, 'Bạn không có quyền kế toán duyệt.');
    }
    // 2) Nếu đã duyệt rồi thì không duyệt lại (tránh bấm double)
    if ($materialRequest->accounting_approved_at) {
        if (request()->expectsJson()) {
            return response()->json(['message' => 'Đơn đã được kế toán duyệt trước đó.']);
        }
        return back()->with('info', 'Đơn đã được kế toán duyệt trước đó.');
    }
    // 3) Transaction cho an toàn
    \DB::transaction(function () use ($materialRequest) {
        $materialRequest->accounting_approved_at = now();
        $materialRequest->accounting_approved_by = \Auth::id();
        // status tiếp theo (bạn đang dùng 'cho_xuat_kho')
$materialRequest->status = 'ACC_APPROVED';
        $materialRequest->save();
    });
    // 4) Trả về: AJAX thì JSON, thường thì redirect back
    if (request()->expectsJson()) {
        return response()->json(['message' => 'Kế toán đã duyệt thành công.']);
    }
    return back()->with('success', 'Kế toán đã duyệt thành công.');
}
}
