<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Workspace\WorkspaceContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class WorkspaceContextController extends Controller
{
    public function __construct(private readonly WorkspaceContextService $context)
    {
        $this->middleware('auth');
    }

    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'workspace' => ['required', 'string', 'max:60'],
            'redirect_to' => ['nullable', 'in:workspace,dashboard'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $active = $this->context->activate($user, (string) $validated['workspace']);
        $label = (string) ($this->context->available($user)[$active]['label'] ?? $active);
        $route = ($validated['redirect_to'] ?? 'dashboard') === 'workspace'
            ? 'workspace.index'
            : 'dashboard';

        return redirect()->route($route)->with('success', 'Đã chuyển sang Workspace '.$label.'.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'redirect_to' => ['nullable', 'in:workspace,dashboard'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $active = $this->context->reset($user);
        $label = (string) ($this->context->available($user)[$active]['label'] ?? $active);
        $route = ($validated['redirect_to'] ?? 'dashboard') === 'workspace'
            ? 'workspace.index'
            : 'dashboard';

        return redirect()->route($route)->with('success', 'Đã trở về Workspace mặc định: '.$label.'.');
    }
}
