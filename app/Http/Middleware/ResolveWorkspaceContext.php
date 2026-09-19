<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Workspace\WorkspaceContextService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

final class ResolveWorkspaceContext
{
    public function __construct(private readonly WorkspaceContextService $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            $active = $this->context->current($user);
            $request->attributes->set('ego_workspace', $active);

            View::share([
                'egoActiveWorkspace' => $active,
                'egoActiveWorkspaceLabel' => $this->context->labelFor($active),
                'egoWorkspaceDeniedMenuPermissions' => $this->context->deniedMenuPermissions($user),
            ]);
        }

        return $next($request);
    }
}
