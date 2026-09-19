<?php

namespace App\Services\Hr;

use App\Models\User;

class AttendanceCorrectionAccessService
{
    /**
     * Chỉ tài khoản có role HR mới được xem hàng chờ và xử lý yêu cầu sửa công.
     *
     * Lưu ý: quyền này cố ý KHÔNG kế thừa từ admin hay permission rời.
     * Nhân viên thuộc mọi role vẫn có thể gửi yêu cầu sửa công của chính mình
     * thông qua route store (route đó chỉ yêu cầu đăng nhập).
     */
    public function canReview(?User $user): bool
    {
        if (! $user || ! method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole('hr');
    }
}
