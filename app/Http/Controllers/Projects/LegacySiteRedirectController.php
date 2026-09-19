<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\ProjectTest\Project;
use Illuminate\Http\RedirectResponse;

class LegacySiteRedirectController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('project-test.index');
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('project-test.create');
    }

    public function show(int $id): RedirectResponse
    {
        $project = Project::query()
            ->where('legacy_site_id', $id)
            ->orWhereKey($id)
            ->first();

        if (! $project) {
            return redirect()->route('project-test.index')
                ->with('error', 'Không tìm thấy hồ sơ công trình đã chuyển từ dữ liệu cũ #'.$id.'.');
        }

        return redirect()->route('project-test.show', $project);
    }

    public function post(int $id): RedirectResponse
    {
        return $this->show($id)->with('warning', 'Chức năng Công trình cũ đã ngừng vận hành. Hồ sơ được mở trong module Công trình mới.');
    }
}
