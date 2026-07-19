<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CRM\Orders\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderDeleteController extends Controller
{
    public function destroy(
        Request $request,
        Order $order
    ): RedirectResponse {
        $data = $request->validate([
            'delete_reason' => [
                'required',
                'string',
                'min:3',
                'max:1000',
            ],
        ]);

        $user = $request->user();

        abort_unless(
            $this->canDeleteOrder($user),
            403,
            'Bạn không có quyền xóa đơn hàng.'
        );

        DB::transaction(function () use (
            $order,
            $user,
            $data
        ): void {
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->findOrFail($order->getKey());

            $updates = [];

            if (
                Schema::hasColumn(
                    $lockedOrder->getTable(),
                    'deleted_by'
                )
            ) {
                $updates['deleted_by'] = $user->id;
            }

            if (
                Schema::hasColumn(
                    $lockedOrder->getTable(),
                    'delete_reason'
                )
            ) {
                $updates['delete_reason'] = trim(
                    (string) $data['delete_reason']
                );
            }

            if ($updates !== []) {
                $lockedOrder->forceFill($updates)->save();
            }

            /*
             * Đây là xóa mềm:
             * - Không xóa sản phẩm trong đơn.
             * - Không đảo giao dịch kho.
             * - Không xóa thanh toán.
             * - Không xóa chứng từ và lịch sử.
             */
            $lockedOrder->delete();
        });

        return redirect()
            ->route('orders.index')
            ->with(
                'success',
                'Đã xóa đơn khỏi danh sách. '
                .'Dữ liệu nghiệp vụ vẫn được lưu để đối soát.'
            );
    }

    private function canDeleteOrder($user): bool
    {
        if (!$user) {
            return false;
        }

        if (
            method_exists($user, 'can')
            && $user->can('orders.delete')
        ) {
            return true;
        }

        $roles = [
            'admin',
            'super_admin',
            'management',
            'director',
        ];

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($roles);
        }

        if (method_exists($user, 'hasRole')) {
            foreach ($roles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        return in_array(
            (string) ($user->role ?? ''),
            $roles,
            true
        );
    }
}
