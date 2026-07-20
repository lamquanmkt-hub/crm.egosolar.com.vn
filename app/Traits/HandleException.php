<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Trait xử lý exception một cách nhất quán.
 *
 * Cung cấp:
 * - Log lỗi có context (user, request)
 * - Chuyển đổi exception sang thông báo thân thiện cho user
 * - Hỗ trợ cả throw & no-throw patterns
 *
 * @used-by \App\Http\Controllers\OrderController
 * @used-by \App\Http\Controllers\CustomerController
 */
trait HandleException
{
    /**
     * Log lỗi và throw lại exception.
     *
     * @param  string  $context  Tên method/action đang thực hiện
     *
     * @throws \Exception
     */
    protected function logAndThrow(\Exception $exception, string $context = ''): void
    {
        $this->logError($exception, $context);
        throw $exception;
    }

    /**
     * Log lỗi mà không throw.
     *
     * Ghi đầy đủ thông tin: class, message, file, line, trace, user_id.
     *
     * @param  string  $context  Tên method/action đang thực hiện
     */
    public function logError(Throwable $exception, string $context = ''): void
    {
        Log::error("Application error [{$context}]", [
            'context' => $context,
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Chuyển exception sang thông báo thân thiện cho user.
     *
     * Ẩn chi tiết kỹ thuật, chỉ hiển thị thông báo có ý nghĩa:
     * - ModelNotFoundException → "Không tìm thấy bản ghi"
     * - ValidationException → "Dữ liệu đầu vào không hợp lệ"
     * - Exception có message ngắn (< 200 chars) → hiển thị trực tiếp
     * - Còn lại → "Có lỗi hệ thống xảy ra"
     */
    protected function getUserFriendlyMessage(Throwable $exception): string
    {
        if (method_exists($exception, 'getUserMessage')) {
            return $exception->getUserMessage();
        }

        return match (true) {
            $exception instanceof ModelNotFoundException => 'Không tìm thấy bản ghi yêu cầu.',
            $exception instanceof ValidationException => 'Dữ liệu đầu vào không hợp lệ.',
            mb_strlen($exception->getMessage()) < 200 => $exception->getMessage(),
            default => 'Có lỗi hệ thống xảy ra. Vui lòng thử lại.',
        };
    }
}
