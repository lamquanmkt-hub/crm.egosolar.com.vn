<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Controller tải lên chứng từ đính kèm cho phiếu đề nghị thanh toán.
 */
class PaymentAttachmentController extends Controller
{
    /**
     * Tải lên và lưu chứng từ cho phiếu đề nghị thanh toán, luôn trả về JSON.
     */
    public function store(Request $request, $paymentRequest)
    {
        @set_time_limit(180);

        $paymentRequestId = (int) $paymentRequest;
        $storedPaths = [];

        try {
            $user = auth()->user();

            if (! $user) {
                return response()->json([
                    'ok' => false,
                    'success' => false,
                    'message' => 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
                ], 401);
            }

            if (
                ! Schema::hasTable('payment_requests') ||
                ! Schema::hasTable('payment_attachments')
            ) {
                return response()->json([
                    'ok' => false,
                    'success' => false,
                    'message' => 'Thiếu bảng dữ liệu chứng từ.',
                ], 500);
            }

            $paymentRequestRow = DB::table('payment_requests')
                ->where('id', $paymentRequestId)
                ->first();

            if (! $paymentRequestRow) {
                return response()->json([
                    'ok' => false,
                    'success' => false,
                    'message' => 'Không tìm thấy phiếu đề nghị thanh toán.',
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | Kiểm tra quyền
            |--------------------------------------------------------------------------
            */

            $isPrivileged = false;

            if (method_exists($user, 'hasAnyRole')) {
                $isPrivileged = $user->hasAnyRole([
                    'admin',
                    'accounting',
                    'ketoan',
                    'ke_toan',
                ]);
            } elseif (method_exists($user, 'hasRole')) {
                foreach (
                    ['admin', 'accounting', 'ketoan', 'ke_toan'] as $role
                ) {
                    if ($user->hasRole($role)) {
                        $isPrivileged = true;
                        break;
                    }
                }
            }

            $rawRole = strtolower(
                trim((string) ($user->role ?? ''))
            );

            if (
                in_array(
                    $rawRole,
                    ['admin', 'accounting', 'ketoan', 'ke_toan'],
                    true
                )
            ) {
                $isPrivileged = true;
            }

            if (
                (int) ($paymentRequestRow->created_by ?? 0) !==
                    (int) $user->id &&
                ! $isPrivileged
            ) {
                return response()->json([
                    'ok' => false,
                    'success' => false,
                    'message' => 'Bạn không có quyền tải chứng từ cho phiếu này.',
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | Lấy file từ request
            |--------------------------------------------------------------------------
            */

            $files = $request->file('attachments', []);

            if ($files instanceof UploadedFile) {
                $files = [$files];
            }

            if (! is_array($files)) {
                $files = [];
            }

            $files = array_values(
                array_filter(
                    $files,
                    fn ($file) => $file instanceof UploadedFile
                )
            );

            if (count($files) > 15) {
                return response()->json([
                    'ok' => false,
                    'success' => false,
                    'message' => 'Mỗi lần chỉ được tải tối đa 15 chứng từ.',
                ], 422);
            }

            if (count($files) === 0) {
                $uploadErrors = [];

                foreach ((array) ($_FILES['attachments']['error'] ?? []) as $error) {
                    $uploadErrors[] = (int) $error;
                }

                return response()->json([
                    'ok' => false,
                    'success' => false,
                    'message' => 'Máy chủ không nhận được file tải lên.',
                    'upload_errors' => $uploadErrors,
                    'content_length' => $request->server('CONTENT_LENGTH'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size'),
                ], 422);
            }

            $allowedExtensions = [
                'jpg',
                'jpeg',
                'png',
                'webp',
                'pdf',
                'doc',
                'docx',
                'xls',
                'xlsx',
            ];

            $maximumBytes = 20 * 1024 * 1024;

            foreach ($files as $index => $file) {
                if (! $file->isValid()) {
                    return response()->json([
                        'ok' => false,
                        'success' => false,
                        'message' => 'File "'.
                            $file->getClientOriginalName().
                            '" không hợp lệ. Mã upload: '.
                            $file->getError(),
                    ], 422);
                }

                if ((int) $file->getSize() <= 0) {
                    return response()->json([
                        'ok' => false,
                        'success' => false,
                        'message' => 'File "'.
                            $file->getClientOriginalName().
                            '" không có dữ liệu.',
                    ], 422);
                }

                if ((int) $file->getSize() > $maximumBytes) {
                    return response()->json([
                        'ok' => false,
                        'success' => false,
                        'message' => 'File "'.
                            $file->getClientOriginalName().
                            '" vượt quá 20MB.',
                    ], 422);
                }

                $extension = strtolower(
                    trim(
                        (string) $file->getClientOriginalExtension()
                    )
                );

                if (
                    $extension === '' ||
                    ! in_array($extension, $allowedExtensions, true)
                ) {
                    return response()->json([
                        'ok' => false,
                        'success' => false,
                        'message' => 'File "'.
                            $file->getClientOriginalName().
                            '" không đúng định dạng cho phép.',
                    ], 422);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Lưu file và database
            |--------------------------------------------------------------------------
            */

            $attachmentColumns = Schema::getColumnListing(
                'payment_attachments'
            );

            $directory =
                'payment_requests/'.$paymentRequestId;

            Storage::disk('public')->makeDirectory($directory);

            $savedFiles = [];

            DB::beginTransaction();

            foreach ($files as $file) {
                $extension = strtolower(
                    (string) $file->getClientOriginalExtension()
                );

                $storedName =
                    now()->format('YmdHis').
                    '-'.
                    Str::uuid()->toString().
                    '.'.
                    $extension;

                $path = Storage::disk('public')->putFileAs(
                    $directory,
                    $file,
                    $storedName
                );

                if (
                    ! $path ||
                    ! Storage::disk('public')->exists($path)
                ) {
                    throw new \RuntimeException(
                        'Không ghi được file vào storage: '.
                        $file->getClientOriginalName()
                    );
                }

                $storedPaths[] = $path;

                $attachmentData = [
                    'payment_request_id' => $paymentRequestId,
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getClientMimeType() ?:
                        $file->getMimeType(),
                    'size' => (int) $file->getSize(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $attachmentData = array_intersect_key(
                    $attachmentData,
                    array_flip($attachmentColumns)
                );

                $attachmentId = DB::table(
                    'payment_attachments'
                )->insertGetId($attachmentData);

                $savedFiles[] = [
                    'id' => (int) $attachmentId,
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => (int) $file->getSize(),
                ];
            }

            DB::commit();

            Log::info('PAYMENT_ATTACHMENT_UPLOAD_SUCCESS', [
                'payment_request_id' => $paymentRequestId,
                'user_id' => $user->id,
                'files' => $savedFiles,
            ]);

            /*
             * Luôn trả JSON.
             * Không dùng return back() vì JavaScript đang chờ JSON.
             */
            return response()->json([
                'ok' => true,
                'success' => true,
                'message' => 'Đã tải '.count($savedFiles).' chứng từ thành công.',
                'payment_request_id' => $paymentRequestId,
                'files' => $savedFiles,
            ], 201);
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            foreach ($storedPaths as $path) {
                try {
                    Storage::disk('public')->delete($path);
                } catch (Throwable $ignored) {
                }
            }

            $errorId =
                'ATT-'.
                now()->format('YmdHis').
                '-'.
                Str::upper(Str::random(5));

            Log::error('PAYMENT_ATTACHMENT_UPLOAD_FAILED', [
                'error_id' => $errorId,
                'payment_request_id' => $paymentRequestId,
                'user_id' => optional(auth()->user())->id,
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'ok' => false,
                'success' => false,
                'message' => 'Không tải được chứng từ. Mã lỗi: '.
                    $errorId.
                    ' — '.
                    $exception->getMessage(),
                'error_id' => $errorId,
            ], 500);
        }
    }
}
