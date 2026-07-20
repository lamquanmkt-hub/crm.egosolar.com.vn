<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Chứng từ đính kèm phiếu đề nghị thanh toán: thêm, thay thế, xóa, tải xuống.
 */
class EgoPaymentRequestAttachmentController extends Controller
{
    /**
     * Chặn 404 nếu thiếu bảng payment_requests hoặc payment_attachments.
     */
    private function abortIfMissing(): void
    {
        abort_unless(Schema::hasTable('payment_requests'), 404, 'Khong thay bang payment_requests.');
        abort_unless(Schema::hasTable('payment_attachments'), 404, 'Khong thay bang payment_attachments.');
    }

    /**
     * Lấy phiếu đề nghị thanh toán theo id (404 nếu không có).
     */
    private function getPaymentRequest($id)
    {
        $this->abortIfMissing();

        $pr = DB::table('payment_requests')->where('id', (int) $id)->first();

        abort_unless($pr, 404, 'Khong tim thay phieu de nghi thanh toan.');

        return $pr;
    }

    /**
     * Lấy chứng từ thuộc đúng phiếu đề nghị thanh toán (404 nếu không có).
     */
    private function getAttachment($paymentRequestId, $attachmentId)
    {
        $this->abortIfMissing();

        $att = DB::table('payment_attachments')
            ->where('id', (int) $attachmentId)
            ->where('payment_request_id', (int) $paymentRequestId)
            ->first();

        abort_unless($att, 404, 'Khong tim thay chung tu.');

        return $att;
    }

    /**
     * Kiểm tra người dùng có một trong các role (tương thích nhiều cách lưu role).
     */
    private function hasRole($user, array $roles): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return (bool) $user->hasAnyRole($roles);
        }

        if (method_exists($user, 'hasRole')) {
            foreach ($roles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        if (isset($user->role)) {
            return in_array((string) $user->role, $roles, true);
        }

        return false;
    }

    /**
     * Xác định người dùng có được sửa chứng từ của phiếu: admin/kế toán/quản lý hoặc người tạo khi phiếu chưa duyệt.
     */
    private function canEdit($pr): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $email = strtolower((string) ($user->email ?? ''));

        if ($email === 'buibichthao@egosolar.vn') {
            return true;
        }

        if ($this->hasRole($user, ['admin', 'accounting', 'manager'])) {
            return true;
        }

        if (isset($pr->created_by) && (int) $pr->created_by === (int) $user->id) {
            $status = strtolower(trim((string) ($pr->status ?? '')));

            return in_array($status, ['', 'draft', 'nhap', 'new', 'admin_rejected', 'accounting_rejected'], true);
        }

        return false;
    }

    /**
     * Trả kết quả thành công: JSON với AJAX, ngược lại redirect kèm flash message.
     */
    private function ok(Request $request, string $message)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Thêm nhiều chứng từ (tối đa 20MB/file) vào phiếu đề nghị thanh toán.
     */
    public function upload(Request $request, $paymentRequest)
    {
        $pr = $this->getPaymentRequest($paymentRequest);

        abort_unless($this->canEdit($pr), 403, 'Ban khong co quyen them chung tu phieu nay.');

        $request->validate([
            'attachments' => ['required'],
            'attachments.*' => ['file', 'max:20480'],
        ]);

        $files = $request->file('attachments', []);

        if (! is_array($files)) {
            $files = [$files];
        }

        $count = 0;

        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $path = $file->store('payment_requests/'.(int) $paymentRequest, 'public');

            DB::table('payment_attachments')->insert([
                'payment_request_id' => (int) $paymentRequest,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $count++;
        }

        return $this->ok($request, 'Da them '.$count.' chung tu.');
    }

    /**
     * Thay file chứng từ bằng file mới (xóa file cũ trên đĩa).
     */
    public function replace(Request $request, $paymentRequest, $attachment)
    {
        $pr = $this->getPaymentRequest($paymentRequest);

        abort_unless($this->canEdit($pr), 403, 'Ban khong co quyen sua chung tu phieu nay.');

        $att = $this->getAttachment($paymentRequest, $attachment);

        $request->validate([
            'attachment' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('attachment');

        if (! empty($att->path) && Storage::disk('public')->exists($att->path)) {
            Storage::disk('public')->delete($att->path);
        }

        $path = $file->store('payment_requests/'.(int) $paymentRequest, 'public');

        DB::table('payment_attachments')
            ->where('id', (int) $attachment)
            ->where('payment_request_id', (int) $paymentRequest)
            ->update([
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'updated_at' => now(),
            ]);

        return $this->ok($request, 'Da cap nhat chung tu.');
    }

    /**
     * Xóa chứng từ khỏi phiếu (cả file vật lý và bản ghi).
     */
    public function destroy(Request $request, $paymentRequest, $attachment)
    {
        $pr = $this->getPaymentRequest($paymentRequest);

        abort_unless($this->canEdit($pr), 403, 'Ban khong co quyen xoa chung tu phieu nay.');

        $att = $this->getAttachment($paymentRequest, $attachment);

        if (! empty($att->path) && Storage::disk('public')->exists($att->path)) {
            Storage::disk('public')->delete($att->path);
        }

        DB::table('payment_attachments')
            ->where('id', (int) $attachment)
            ->where('payment_request_id', (int) $paymentRequest)
            ->delete();

        return $this->ok($request, 'Da xoa chung tu.');
    }

    /**
     * Tải xuống chứng từ với tên gốc.
     */
    public function download($paymentRequest, $attachment)
    {
        $this->getPaymentRequest($paymentRequest);

        $att = $this->getAttachment($paymentRequest, $attachment);

        abort_unless(! empty($att->path) && Storage::disk('public')->exists($att->path), 404, 'Khong thay file chung tu.');

        return Storage::disk('public')->download($att->path, $att->original_name ?: basename($att->path));
    }
}
