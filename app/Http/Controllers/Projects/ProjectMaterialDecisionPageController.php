<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\ProjectTest\MaterialRequest;
use App\Models\ProjectTest\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ProjectMaterialDecisionPageController extends Controller
{
    public function show(
        Request $request,
        Project $project,
        MaterialRequest $materialRequest
    ): View {
        abort_unless(
            (int) $materialRequest->project_id === (int) $project->id,
            404,
            'Phiếu vật tư không thuộc công trình này.'
        );

        $user = $request->user();
        abort_unless($user, 401, 'Phiên đăng nhập đã hết hạn.');

        $roles = collect();
        try {
            if (method_exists($user, 'getRoleNames')) {
                $roles = $user->getRoleNames()
                    ->map(fn ($role) => mb_strtolower(trim((string) $role)))
                    ->values();
            } elseif (method_exists($user, 'roles')) {
                $roles = $user->roles()
                    ->pluck('name')
                    ->map(fn ($role) => mb_strtolower(trim((string) $role)))
                    ->values();
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        $allowedRoles = collect([
            'admin', 'administrator', 'super admin', 'super_admin',
            'management', 'manager', 'technical manager', 'technical_manager',
            'technical leader', 'technical_leader', 'quản lý', 'quan ly',
            'giám đốc', 'giam doc',
        ]);

        $allowed = $roles->intersect($allowedRoles)->isNotEmpty()
            || (bool) data_get($user, 'is_admin', false)
            || (bool) data_get($user, 'is_super_admin', false)
            || (int) data_get($user, 'level', 0) === 1;

        try {
            $allowed = $allowed || $user->can('project-test.admin');
        } catch (\Throwable $exception) {
            report($exception);
        }

        abort_unless($allowed, 403, 'Tài khoản chưa có quyền Quản lý phê duyệt vật tư.');

        $materialRequest->refresh()->load([
            'items.allocations.product',
            'items.product',
            'project',
        ]);

        abort_unless(
            (string) $materialRequest->status === 'pending_manager',
            422,
            'Phiếu không còn ở trạng thái chờ Quản lý phê duyệt.'
        );

        $selectedDecision = (string) $request->query('decision', 'return_technical');
        if (! in_array($selectedDecision, ['approve', 'return_warehouse', 'return_technical'], true)) {
            $selectedDecision = 'return_technical';
        }

        return view('project-test.material-decision', [
            'project' => $project,
            'materialRequest' => $materialRequest,
            'selectedDecision' => $selectedDecision,
        ]);
    }
}
