<?php

declare(strict_types=1);

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Support\SchemaCache;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Xoá đơn vật tư (`DELETE /don-vat-tu/{materialRequest}`).
 *
 * Trước đây là closure 80 dòng trong `routes/projects.php`.
 *
 * Luật nghiệp vụ quan trọng: đơn ĐÃ xuất kho / đã hoàn thành thì KHÔNG được xoá,
 * vì xoá sẽ làm lệch tồn kho — muốn điều chỉnh phải lập phiếu hoàn/điều chỉnh.
 */
final class MaterialRequestDeletionController extends Controller
{
    /**
     * Các trạng thái coi như đã hoàn tất, viết hoa để so khớp.
     *
     * Nhiều đời code đặt tên trạng thái khác nhau (Anh lẫn Việt không dấu) nên
     * danh sách phải phủ hết; sót một giá trị là cho phép xoá nhầm đơn đã xuất kho.
     *
     * @var list<string>
     */
    private const COMPLETED_STATUSES = [
        'EXPORTED',
        'COMPLETED',
        'COMPLETE',
        'DONE',
        'FINISHED',
        'DA_XUAT_KHO',
        'HOAN_THANH',
    ];

    /**
     * Bảng con bị xoá kèm khi xoá đơn.
     *
     * @var list<string>
     */
    private const CHILD_TABLES = [
        'material_request_items',
        'material_request_edit_histories',
    ];

    /**
     * Vai trò được phép xoá đơn vật tư.
     *
     * @var list<string>
     */
    private const ALLOWED_ROLES = ['admin', 'technical', 'ky_thuat'];

    public function __invoke(Request $request, int $materialRequest): RedirectResponse
    {
        // Route đã có middleware `role:technical|admin`, nhưng vẫn kiểm lại ở đây.
        // KHÔNG thừa: middleware RoleWeb còn một nhánh dự phòng theo permission
        // (`canSatisfyLegacyRole`), nên có user qua được middleware mà KHÔNG thật
        // sự mang vai trò. Bản closure cũ chặn những user đó — bỏ phép kiểm này
        // là lặng lẽ nới quyền xoá đơn vật tư.
        abort_unless($this->hasAllowedRole($request->user()), 403);

        abort_unless(SchemaCache::hasTable('material_requests'), 404);

        $row = DB::table('material_requests')->where('id', $materialRequest)->first();

        abort_if($row === null, 404);

        if ($this->isCompleted($row)) {
            return back()->with(
                'error',
                'Đơn đã hoàn thành / đã xuất kho nên không được xóa để tránh lệch tồn kho. '.
                'Hãy tạo phiếu hoàn/điều chỉnh kho nếu cần.',
            );
        }

        DB::transaction(function () use ($materialRequest): void {
            foreach (self::CHILD_TABLES as $table) {
                if (SchemaCache::hasTable($table)) {
                    DB::table($table)->where('material_request_id', $materialRequest)->delete();
                }
            }

            DB::table('material_requests')->where('id', $materialRequest)->delete();
        });

        return redirect('/don-vat-tu')->with('success', 'Đã xóa đơn vật tư #'.$materialRequest.'.');
    }

    private function isCompleted(object $row): bool
    {
        $status = strtoupper(trim((string) ($row->status ?? '')));

        return in_array($status, self::COMPLETED_STATUSES, true);
    }

    /**
     * Kiểm vai trò theo đúng thứ tự dự phòng của bản cũ: hasAnyRole -> hasRole
     * -> cột `role` trên bảng users (dữ liệu đời đầu chưa chuyển sang Spatie).
     */
    private function hasAllowedRole(?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            return (bool) $user->hasAnyRole(self::ALLOWED_ROLES);
        }

        if (method_exists($user, 'hasRole')) {
            foreach (self::ALLOWED_ROLES as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        return isset($user->role) && in_array((string) $user->role, self::ALLOWED_ROLES, true);
    }
}
