<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\ProjectTest\MaterialRequest;
use App\Models\ProjectTest\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProjectMaterialDecisionController extends Controller
{
    public function __invoke(
        Request $request,
        Project $project,
        MaterialRequest $materialRequest,
        string $decision
    ): RedirectResponse {
        abort_unless(
            in_array($decision, ['approve', 'return_warehouse', 'return_technical'], true),
            404
        );

        abort_unless(
            (int) $materialRequest->project_id === (int) $project->id,
            404,
            'Phiếu vật tư không thuộc công trình này.'
        );

        $request->merge(['decision' => $decision]);

        Log::info('EGO material workflow decision received', [
            'project_id' => (int) $project->id,
            'material_request_id' => (int) $materialRequest->id,
            'decision' => $decision,
            'user_id' => optional($request->user())->id,
            'request_status' => (string) $materialRequest->status,
        ]);

        return app(ProjectTestController::class)->reviewMaterials(
            $request,
            $project,
            $materialRequest
        );
    }
}
